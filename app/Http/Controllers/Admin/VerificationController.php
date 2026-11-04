<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\House;
use App\Services\AuditWriter;
use App\Services\VillageBoundaryValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function index(): View
    {
        $houses = House::with('familyCards.members', 'creator')
            ->whereNull('archived_at')
            ->where(function ($query): void {
                $query->where('verification_status', 'pending')->orWhere('verification_status', 'rejected')->orWhere(function ($q): void {
                    $q->where('verification_status', 'verified')->whereIn('publication_status', ['draft', 'published']);
                });
            })
            ->latest()->paginate(15);

        return view('admin.verification.index', compact('houses'));
    }

    public function verify(Request $request, House $house): RedirectResponse
    {
        abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);

        DB::transaction(function () use ($request, $house): void {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);
            $feature = $house->mapFeature()->lockForUpdate()->first();
            $before = AuditWriter::houseSnapshot($house);

            $house->update([
                'verification_status' => 'verified',
                'publication_status' => 'draft',
                'published_by' => null,
                'published_at' => null,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'rejection_note' => null,
            ]);
            $feature?->update(['verification_status' => 'verified', 'publication_status' => 'draft']);

            AuditWriter::write('house.verified', $house, $before, AuditWriter::houseSnapshot($house));
        });

        return back()->with('success', "{$house->house_code} berhasil diverifikasi. Publikasi masih merupakan langkah terpisah.");
    }

    public function reject(Request $request, House $house): RedirectResponse
    {
        abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);
        $data = $request->validate([
            'rejection_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $house, $data): void {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);
            $feature = $house->mapFeature()->lockForUpdate()->first();
            $before = AuditWriter::houseSnapshot($house);

            $house->update([
                'verification_status' => 'rejected',
                'publication_status' => 'draft',
                'published_by' => null,
                'published_at' => null,
                'public_visible' => false,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'rejection_note' => $data['rejection_note'],
            ]);
            $feature?->update([
                'verification_status' => 'rejected',
                'publication_status' => 'draft',
            ]);

            AuditWriter::write('house.rejected', $house, $before, AuditWriter::houseSnapshot($house) + ['reason' => $data['rejection_note']]);
        });

        return back()->with('success', 'Data ditolak dan tidak ditampilkan di peta publik.');
    }

    public function publish(Request $request, House $house): RedirectResponse
    {
        abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);
        $request->validate([
            'publication_declaration' => ['required', 'accepted'],
        ], [
            'publication_declaration.required' => 'Pernyataan tanggung jawab wajib disetujui sebelum publikasi.',
            'publication_declaration.accepted' => 'Pernyataan tanggung jawab wajib disetujui sebelum publikasi.',
        ]);
        if ($house->verification_status !== 'verified') {
            return back()->withErrors(['publication' => 'Verifikasi harus selesai sebelum publikasi.']);
        }
        if (! $house->public_visible) {
            return back()->withErrors(['publication' => 'Centang izin menampilkan titik rumah pada form rumah. Nama dan data keluarga tetap privat.']);
        }

        DB::transaction(function () use ($request, $house): void {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            $feature = $house->mapFeature()->lockForUpdate()->first();
            abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);
            $latitude = $house->latitude;
            $longitude = $house->longitude;
            if (! is_numeric($latitude) || ! is_numeric($longitude)
                || ! is_finite((float) $latitude) || ! is_finite((float) $longitude)
                || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
                || ! app(VillageBoundaryValidator::class)->contains((float) $longitude, (float) $latitude)) {
                throw ValidationException::withMessages([
                    'publication' => 'Koordinat rumah wajib valid dan berada di dalam batas referensi Desa Pilangsari sebelum publikasi.',
                ]);
            }

            abort_if($house->verification_status !== 'verified', 409);
            abort_if(! $house->public_visible, 409);
            $before = AuditWriter::houseSnapshot($house);

            $house->update([
                'publication_status' => 'published',
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);
            $feature?->update([
                'verification_status' => 'verified',
                'publication_status' => 'published',
            ]);

            AuditWriter::write('house.published', $house, $before, AuditWriter::houseSnapshot($house) + [
                'publication_declaration' => 'Super Admin menyatakan verifikasi data, izin penayangan titik generik, dan perlindungan data pribadi telah diperiksa.',
                'publication_declaration_accepted' => true,
            ]);
        });

        return back()->with('success', 'Publikasi individual rumah tersimpan. Titik rumah dapat tampil pada peta publik; data keluarga tetap privat.');
    }

    public function unpublish(Request $request, House $house): RedirectResponse
    {
        abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);

        DB::transaction(function () use ($house): void {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            abort_if($house->archived_at !== null || $house->publication_status === 'archived', 404);
            $feature = $house->mapFeature()->lockForUpdate()->first();
            $before = AuditWriter::houseSnapshot($house);

            $house->update([
                'publication_status' => 'draft',
                'published_by' => null,
                'published_at' => null,
            ]);
            $feature?->update(['publication_status' => 'draft']);

            AuditWriter::write('house.unpublished', $house, $before, AuditWriter::houseSnapshot($house));
        });

        return back()->with('success', 'Titik rumah ditarik dari peta publik.');
    }
}
