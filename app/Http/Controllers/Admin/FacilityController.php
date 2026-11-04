<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Services\AuditWriter;
use App\Services\MapCategoryCatalog;
use App\Services\VillageBoundaryValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class FacilityController extends Controller
{
    public function index(): View
    {
        $facilities = Facility::whereNull('archived_at')->latest()->paginate(15);

        return view('admin.facilities.index', compact('facilities'));
    }

    public function create(): View
    {
        return view('admin.facilities.form', [
            'facility' => new Facility,
            'categories' => MapCategoryCatalog::choices(facilitiesOnly: true)->pluck('value')->all(),
            'mode' => 'create',
        ]);
    }

    public function edit(Facility $facility): View
    {
        abort_if($facility->archived_at !== null, 404);

        return view('admin.facilities.form', [
            'facility' => $facility,
            'categories' => MapCategoryCatalog::choices(facilitiesOnly: true)->pluck('value')->push($facility->category)->unique()->all(),
            'mode' => 'edit',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return DB::transaction(function () use ($request): RedirectResponse {
            $data = $this->validateFacility($request);
            $this->assertCoordinatesWithinVillage(
                (float) $data['longitude'],
                (float) $data['latitude']
            );

            $facility = Facility::create($data + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'status' => 'draft',
            ]);

            AuditWriter::write('facility.created', $facility, [], $this->snapshot($facility));

            return redirect()->route('admin.facilities.index')
                ->with('success', 'Fasilitas disimpan sebagai draf. Ubah ke status publikasi setelah data diperiksa.');
        });
    }

    public function update(Request $request, Facility $facility): RedirectResponse
    {
        return DB::transaction(function () use ($request, $facility): RedirectResponse {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            abort_if($facility->archived_at !== null, 404);

            $data = $this->validateFacility($request);
            $this->assertCoordinatesWithinVillage(
                (float) $data['longitude'],
                (float) $data['latitude']
            );

            $before = $this->snapshot($facility);
            $facility->update($data + [
                'status' => 'draft',
                'updated_by' => $request->user()->id,
            ]);

            AuditWriter::write('facility.updated', $facility, $before, $this->snapshot($facility));

            return redirect()->route('admin.facilities.index')
                ->with('success', 'Fasilitas diperbarui dan kembali menjadi draf hingga ditinjau.');
        });
    }

    public function publish(Request $request, Facility $facility): RedirectResponse
    {
        return DB::transaction(function () use ($request, $facility): RedirectResponse {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            abort_if($facility->archived_at !== null, 404);
            Validator::make($facility->only(['name', 'category', 'latitude', 'longitude']), [
                'name' => ['required', 'string', 'max:160'],
                'category' => ['required', 'string', 'max:80'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ])->validate();
            $this->assertCoordinatesWithinVillage((float) $facility->longitude, (float) $facility->latitude);
            $before = $this->snapshot($facility);
            $facility->update(['status' => 'published', 'updated_by' => $request->user()->id]);
            AuditWriter::write('facility.published', $facility, $before, $this->snapshot($facility));

            return back()->with('success', 'Publikasi individual fasilitas tersimpan. Fasilitas dapat tampil pada peta publik.');
        });
    }

    public function unpublish(Request $request, Facility $facility): RedirectResponse
    {
        return DB::transaction(function () use ($request, $facility): RedirectResponse {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            abort_if($facility->archived_at !== null, 404);
            $before = $this->snapshot($facility);
            $facility->update(['status' => 'draft', 'updated_by' => $request->user()->id]);
            AuditWriter::write('facility.unpublished', $facility, $before, $this->snapshot($facility));

            return back()->with('success', 'Fasilitas ditarik dari peta publik.');
        });
    }

    public function archive(Request $request, Facility $facility): RedirectResponse
    {
        return DB::transaction(function () use ($request, $facility): RedirectResponse {
            $facility = Facility::query()->lockForUpdate()->findOrFail($facility->id);
            $before = $this->snapshot($facility);
            $facility->update([
                'status' => 'archived',
                'archived_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
            AuditWriter::write('facility.archived', $facility, $before, $this->snapshot($facility));

            return back()->with('success', 'Fasilitas diarsipkan.');
        });
    }

    private function validateFacility(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
    }

    /**
     * Fasilitas menggunakan validator batas yang sama dengan rumah dan fitur peta.
     * Resolver diletakkan di dalam try agar kegagalan memuat GeoJSON juga ditolak
     * secara terkendali, bukan menyebabkan data tersimpan atau lolos validasi.
     */
    private function assertCoordinatesWithinVillage(float $longitude, float $latitude): void
    {
        try {
            $boundary = app(VillageBoundaryValidator::class);
            $inside = $boundary->contains($longitude, $latitude);
        } catch (RuntimeException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'latitude' => 'Penyimpanan ditolak karena batas referensi desa tidak dapat diverifikasi. Data tidak disimpan.',
            ]);
        }

        if (! $inside) {
            throw ValidationException::withMessages([
                'latitude' => 'Koordinat fasilitas berada di luar batas referensi Desa Pilangsari. Data tidak disimpan.',
            ]);
        }
    }

    private function snapshot(Facility $facility): array
    {
        return [
            'name' => $facility->name,
            'category' => $facility->category,
            'latitude' => $facility->latitude,
            'longitude' => $facility->longitude,
            'status' => $facility->status,
            'address' => $facility->address,
        ];
    }
}
