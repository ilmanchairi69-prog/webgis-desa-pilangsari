<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Services\VillageBoundaryValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HouseController extends Controller
{
    public function create(): View
    {
        return view('admin.houses.form');
    }

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'string', 'max:24']]);
        $keyword = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $user = $request->user();

        $query = House::query()
            ->whereNull('archived_at')
            ->with(['mapFeature', 'familyCards.familyMembers'])
            ->orderBy('house_code');

        // Batas data mengikuti RT tugas. Data legacy tanpa RT hanya terlihat
        // oleh admin pembuat sampai RT ditetapkan oleh Super Admin.
        $query->accessibleTo($user);

        if ($keyword !== '') {
            if (preg_match('/\ART\s*([0-9]{1,2})\z/iu', $keyword, $matches) === 1) {
                $query->where('rt_code', str_pad((string) ((int) $matches[1]), 2, '0', STR_PAD_LEFT));
            } else {
                $escaped = addcslashes($keyword, '%_\\');
                $query->where(function ($builder) use ($escaped) {
                    $like = "%{$escaped}%";
                    $builder->where('house_code', 'like', $like)
                        ->orWhere('house_label', 'like', $like)
                        ->orWhere('rt_code', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhereHas('familyCards', function ($cards) use ($like) {
                            $cards->where('head_of_family_name', 'like', $like)
                                ->orWhere('head_name', 'like', $like);
                        })
                        ->orWhereHas('familyCards.familyMembers', function ($members) use ($like) {
                            $members->where('full_name', 'like', $like);
                        });
                });
            }
        }

        // Sumber kebenaran status verifikasi adalah tabel houses.
        // Jangan memakai whereHas(mapFeature): relasi lama dapat null atau
        // status MapFeature dapat tertinggal dari status rumah.
        if (in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $query->where('houses.verification_status', $status);
        }

        $houses = $query->paginate(15)->withQueryString();

        foreach ($houses as $house) {
            $house->setAttribute(
                'display_head_names',
                $house->familyCards
                    ->map(fn ($card) => $card->head_name ?: $card->head_of_family_name)
                    ->filter()
                    ->implode(', ')
            );
            $house->setAttribute(
                'display_member_count',
                $house->familyCards->sum(fn ($card) => $card->familyMembers->count())
            );
        }

        return view('admin.houses.index', [
            'houses' => $houses,
            'keyword' => $keyword,
            'selectedStatus' => $status,
        ]);
    }

    public function store(
        Request $request,
        VillageBoundaryValidator $boundaryValidator
    ): RedirectResponse {
        $validated = $request->validate([
            'house_label' => ['nullable', 'string', 'max:200'],
            'rt_code' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'public_visible' => ['nullable', 'boolean'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            'households' => ['required', 'array', 'min:1', 'max:20'],
            'households.*.head_of_family_name' => [
                'required', 'string', 'max:160',
            ],
            'households.*.members' => [
                'nullable', 'array', 'max:30',
            ],
            'households.*.members.*.full_name' => [
                'required', 'string', 'max:200',
            ],
            'households.*.members.*.relationship' => ['nullable', 'string', 'max:50'],
            'households.*.members.*.date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'households.*.members.*.gender' => ['nullable', 'in:Laki-laki,Perempuan'],
        ], [
            'households.required' => 'Tambahkan minimal satu kartu keluarga.',
            'households.min' => 'Tambahkan minimal satu kartu keluarga.',
            'households.*.head_of_family_name.required' => 'Nama kepala keluarga wajib diisi.',
            'households.*.members.*.full_name.required' => 'Nama anggota keluarga yang ditambahkan wajib diisi.',
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        try {
            $insideBoundary = $boundaryValidator->contains(
                $longitude,
                $latitude
            );
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'latitude' => 'Validasi batas desa gagal. Data tidak disimpan sampai batas referensi dapat diperiksa.',
            ]);
        }

        if (! $insideBoundary) {
            try {
                AuditLog::create([
                    'user_id' => $request->user()?->id,
                    'action' => 'house.boundary_rejected',
                    'entity_type' => 'House',
                    'entity_id' => null,
                    'changes' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'reason' => 'Koordinat di luar batas referensi desa.',
                    ],
                    'created_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }

            throw ValidationException::withMessages([
                'latitude' => 'Titik bangunan berada di luar batas referensi Desa Pilangsari. Periksa kembali koordinat GPS.',
            ]);
        }

        $userId = $request->user()->id;

        $house = DB::transaction(function () use (
            $validated,
            $latitude,
            $longitude,
            $userId,
            $request
        ): House {
            $sourceId = PublicationCategory::whereKey(PublicationCategory::Houses)
                ->lockForUpdate()->value('source_layer_id');
            $layer = $sourceId ? MapLayer::whereKey($sourceId)->lockForUpdate()->first() : null;
            if ($layer && ($layer->layer_type !== 'point' || $layer->status === 'archived')) {
                $layer = null;
            }
            // Kode dibuat oleh sistem; pengguna tidak memasukkannya sendiri.
            do {
                $houseCode = 'BGN-'.now()->format('ymd')
                    .'-'.Str::upper(Str::random(5));
            } while (House::where('house_code', $houseCode)->exists());

            // Relasi GIS lama tetap disinkronkan ketika sumber kompatibel tersedia.
            // Jangan simpan nama keluarga atau data pribadi di marker.
            $feature = $layer ? MapFeature::create([
                'layer_id' => $layer->id,
                'name' => $houseCode,
                'description' => null,
                'category' => 'rumah',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$longitude, $latitude],
                ],
                'properties' => [
                    'house_code' => $houseCode,
                ],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'source_name' => 'Survei GPS',
                'verification_status' => 'pending',
                'publication_status' => 'draft',
                'created_by' => $userId,
            ]) : null;

            $house = House::create([
                'map_feature_id' => $feature?->id,
                'house_code' => $houseCode,
                'house_label' => $validated['house_label'] ?? null,
                'rt_code' => $request->user()->isRtAdmin() ? $request->user()->rt_code : ($validated['rt_code'] ?? null),
                'address' => $validated['address'],
                'public_visible' => $request->boolean('public_visible'),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'created_by' => $userId,
                'updated_by' => null,
            ]);

            $totalMembers = 0;
            $familyCardIds = [];

            foreach ($validated['households'] as $householdData) {
                $card = FamilyCard::create([
                    'house_id' => $house->id,
                    'head_of_family_name' => trim($householdData['head_of_family_name']),
                    'created_by' => $userId,
                    'updated_by' => null,
                ]);

                $familyCardIds[] = $card->id;
                $memberCount = 0;

                foreach ($householdData['members'] ?? [] as $memberData) {
                    FamilyMember::create([
                        'family_card_id' => $card->id,
                        'full_name' => trim($memberData['full_name']),
                        'relationship' => $memberData['relationship'] ?? null,
                        'date_of_birth' => $memberData['date_of_birth'] ?? null,
                        'gender' => $memberData['gender'] ?? null,
                        'created_by' => $userId,
                        'updated_by' => null,
                    ]);

                    $memberCount++;
                    $totalMembers++;
                }

                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'family_card.created',
                    'entity_type' => 'FamilyCard',
                    'entity_id' => $card->id,
                    'changes' => [
                        'house_id' => $house->id,
                        'member_count' => $memberCount,
                    ],
                    'created_at' => now(),
                ]);
            }

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'house.created',
                'entity_type' => 'House',
                'entity_id' => $house->id,
                'changes' => [
                    'house_code' => $houseCode,
                    'map_feature_id' => $feature?->id,
                    'family_card_ids' => $familyCardIds,
                    'family_card_count' => count($familyCardIds),
                    'family_member_count' => $totalMembers,
                    'verification_status' => 'pending',
                    'publication_status' => 'draft',
                ],
                'created_at' => now(),
            ]);

            return $house;
        });

        return redirect()
            ->route('admin.houses.index')
            ->with(
                'success',
                "Bangunan {$house->house_code} berhasil disimpan bersama data KK dan anggota. Titik menunggu verifikasi."
            );
    }

    private function authorizeHouseAccess(Request $request, House $house): void
    {
        $user = $request->user();
        abort_unless($user && House::accessibleTo($user)->whereKey($house->id)->exists(), 403);
        abort_if($house->archived_at !== null, 404);
    }

    public function show(Request $request, House $house): View
    {
        $this->authorizeHouseAccess($request, $house);

        $house->load(['mapFeature', 'familyCards.familyMembers', 'creator', 'verifier']);

        return view('admin.houses.show', compact('house'));
    }

    public function edit(Request $request, House $house): View
    {
        $this->authorizeHouseAccess($request, $house);
        $house->load(['mapFeature', 'familyCards.familyMembers']);

        return view('admin.houses.edit', compact('house'));
    }

    public function update(
        Request $request,
        House $house,
        VillageBoundaryValidator $boundaryValidator
    ): RedirectResponse {
        $this->authorizeHouseAccess($request, $house);

        $validated = $request->validate([
            'house_label' => ['nullable', 'string', 'max:200'],
            'rt_code' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'public_visible' => ['nullable', 'boolean'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'family_cards' => ['nullable', 'array', 'max:20'],
            'family_cards.*' => ['array:head_name,members'],
            'family_cards.*.head_name' => ['sometimes', 'required', 'string', 'max:160'],
            'family_cards.*.members' => ['sometimes', 'array', 'max:30'],
            'family_cards.*.members.*' => ['array:full_name,relationship,date_of_birth,gender'],
            'family_cards.*.members.*.full_name' => ['required', 'string', 'max:200'],
            'family_cards.*.members.*.relationship' => ['nullable', 'string', 'max:50'],
            'family_cards.*.members.*.date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'family_cards.*.members.*.gender' => ['nullable', 'in:Laki-laki,Perempuan'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        try {
            if (! $boundaryValidator->contains($longitude, $latitude)) {
                throw ValidationException::withMessages([
                    'latitude' => 'Koordinat berada di luar batas referensi Desa Pilangsari.',
                ]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'latitude' => 'Validasi batas desa gagal. Perubahan tidak disimpan.',
            ]);
        }

        $user = $request->user();

        DB::transaction(function () use ($house, $validated, $latitude, $longitude, $user, $request): void {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            $this->authorizeHouseAccess($request, $house);
            $before = [
                'rt_code' => $house->rt_code,
                'address_changed' => $house->address !== $validated['address'],
                'latitude' => $house->latitude,
                'longitude' => $house->longitude,
                'house_label' => $house->house_label,
            ];

            $house->house_label = $validated['house_label'] ?? null;
            $house->rt_code = $user->isRtAdmin()
                ? $user->rt_code
                : ($validated['rt_code'] ?? null);
            $house->address = $validated['address'];
            $house->latitude = $latitude;
            $house->longitude = $longitude;
            $house->updated_by = $user->id;

            // Setiap perubahan data lokasi/alamat kembali ke antrean pemeriksaan.
            $house->verification_status = 'pending';
            $house->publication_status = 'draft';
            $house->public_visible = $request->boolean('public_visible');
            $house->rejection_note = null;
            $house->verified_by = null;
            $house->verified_at = null;
            $house->published_by = null;
            $house->published_at = null;
            $house->save();

            if ($house->map_feature_id) {
                $feature = MapFeature::lockForUpdate()->find($house->map_feature_id);
                if ($feature) {
                    $feature->latitude = $latitude;
                    $feature->longitude = $longitude;
                    $feature->geometry = [
                        'type' => 'Point',
                        'coordinates' => [$longitude, $latitude],
                    ];
                    $feature->name = $house->house_code;
                    $feature->verification_status = 'pending';
                    $feature->publication_status = 'draft';
                    $feature->save();
                }
            }

            $updatedCardIds = [];
            $updatedMemberIds = [];
            foreach ($validated['family_cards'] ?? [] as $cardId => $cardData) {
                abort_unless(ctype_digit((string) $cardId) && (int) $cardId > 0, 403);
                $card = $house->familyCards()->whereKey($cardId)->lockForUpdate()->first();
                abort_unless($card, 403);
                if (array_key_exists('head_name', $cardData)) {
                    $card->head_name = trim($cardData['head_name']);
                    $card->head_of_family_name = trim($cardData['head_name']);
                    $card->updated_by = $user->id;
                    if ($card->isDirty()) {
                        $updatedCardIds[] = $card->id;
                    }
                    $card->save();
                }
                foreach ($cardData['members'] ?? [] as $memberId => $memberData) {
                    abort_unless(ctype_digit((string) $memberId) && (int) $memberId > 0, 403);
                    $member = $card->familyMembers()->whereKey($memberId)->lockForUpdate()->first();
                    abort_unless($member, 403);
                    $member->full_name = trim($memberData['full_name']);
                    foreach (['relationship', 'date_of_birth', 'gender'] as $field) {
                        if (array_key_exists($field, $memberData)) {
                            $member->{$field} = $memberData[$field];
                        }
                    }
                    $member->updated_by = $user->id;
                    if ($member->isDirty()) {
                        $updatedMemberIds[] = $member->id;
                    }
                    $member->save();
                }
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'house.updated',
                'entity_type' => 'House',
                'entity_id' => $house->id,
                'changes' => [
                    'before' => $before,
                    'updated_family_card_ids' => $updatedCardIds,
                    'updated_family_member_ids' => $updatedMemberIds,
                    'updated_family_member_count' => count($updatedMemberIds),
                    'after' => [
                        'rt_code' => $house->rt_code,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'verification_status' => 'pending',
                        'publication_status' => 'draft',
                    ],
                ],
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.houses.show', $house)
            ->with('success', 'Data rumah diperbarui. Status kembali menunggu verifikasi.');
    }

    public function archiveConfirmation(Request $request, House $house): View
    {
        $this->authorizeHouseAccess($request, $house);
        $house->load('familyCards');

        return view('admin.houses.archive', compact('house'));
    }

    public function archive(Request $request, House $house): RedirectResponse
    {
        $user = $request->user();

        $this->authorizeHouseAccess($request, $house);

        DB::transaction(function () use ($house, $user, $request): void {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            $this->authorizeHouseAccess($request, $house);
            $beforeStatus = $house->publication_status;
            $house->archived_at = now();
            $house->publication_status = 'archived';
            $house->updated_by = $user->id;
            $house->save();

            $house->mapFeature()->update(['publication_status' => 'archived']);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'house.archived',
                'entity_type' => 'House',
                'entity_id' => $house->id,
                'changes' => [
                    'house_code' => $house->house_code,
                    'archived' => true,
                    'before_publication_status' => $beforeStatus,
                    'publication_status' => 'archived',
                ],
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.houses.index')
            ->with('success', 'Bangunan berhasil diarsipkan.');
    }
}
