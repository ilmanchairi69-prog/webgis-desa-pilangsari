<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Services\AuditWriter;
use App\Services\GeoJsonGeometry;
use App\Services\MapCategoryCatalog;
use App\Services\VillageBoundaryValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class MapFeatureController extends Controller
{
    public function index(): View
    {
        return view('admin.features.index', [
            'features' => MapFeature::with('layer')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.features.form', [
            'feature' => new MapFeature,
            'categories' => MapCategoryCatalog::choices(),
            'dataSources' => DB::table('map_data_sources')->orderBy('name')->pluck('name'),
            'layers' => MapLayer::where('status', '!=', 'archived')
                ->orderBy('name')->get(),
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        try {
            $boundary = app(VillageBoundaryValidator::class);
            $validated = $this->validateFeature($request, $boundary);
        } catch (RuntimeException $exception) {
            $this->logBoundaryLoadFailure(
                $request,
                'feature.create_rejected',
                null,
                $exception
            );

            throw ValidationException::withMessages([
                'latitude' => 'Penyimpanan ditolak karena batas referensi desa tidak dapat diverifikasi.',
            ]);
        } catch (ValidationException $exception) {
            $this->logBoundaryRejection(
                $request,
                $boundary,
                'feature.create_rejected',
                null,
                $exception
            );

            throw $exception;
        }
        $validated['verification_status'] = 'pending';
        $validated['publication_status'] = 'draft';
        $validated['created_by'] = $request->user()?->id;

        DB::transaction(function () use ($validated): void {
            $this->lockAndValidateLayer($validated);
            $feature = MapFeature::create($validated);
            AuditWriter::write('feature.created', $feature, [], $feature->toArray());
        });

        return redirect()->route('admin.features.index')
            ->with('success', 'Objek disimpan sebagai draft dan menunggu verifikasi.');
    }

    public function show(MapFeature $feature): View
    {
        return view('admin.features.index', [
            'features' => MapFeature::with('layer')
                ->whereKey($feature->id)->paginate(15),
        ]);
    }

    public function edit(MapFeature $feature): View
    {
        return view('admin.features.form', [
            'feature' => $feature,
            'categories' => MapCategoryCatalog::choices(),
            'dataSources' => DB::table('map_data_sources')->orderBy('name')->pluck('name'),
            'layers' => MapLayer::where('status', '!=', 'archived')
                ->orderBy('name')->get(),
        ]);
    }

    public function update(
        Request $request,
        MapFeature $feature
    ): RedirectResponse {
        abort_if($feature->house()->exists(), 409, 'Kelola fitur rumah melalui menu Rumah & Keluarga.');
        try {
            $boundary = app(VillageBoundaryValidator::class);
            $validated = $this->validateFeature($request, $boundary);
        } catch (RuntimeException $exception) {
            $this->logBoundaryLoadFailure(
                $request,
                'feature.update_rejected',
                (int) $feature->id,
                $exception
            );

            throw ValidationException::withMessages([
                'latitude' => 'Perubahan ditolak karena batas referensi desa tidak dapat diverifikasi.',
            ]);
        } catch (ValidationException $exception) {
            $this->logBoundaryRejection(
                $request,
                $boundary,
                'feature.update_rejected',
                (int) $feature->id,
                $exception
            );

            throw $exception;
        }

        $validated['verification_status'] = 'pending';
        $validated['publication_status'] = 'draft';
        DB::transaction(function () use ($feature, $validated): void {
            $feature = MapFeature::query()->lockForUpdate()->findOrFail($feature->id);
            abort_if($feature->house()->exists(), 409, 'Kelola fitur rumah melalui menu Rumah & Keluarga.');
            $this->lockAndValidateLayer($validated);
            $before = $feature->toArray();
            $feature->update($validated);
            AuditWriter::write('feature.updated', $feature, $before, $feature->toArray());
        });

        return redirect()->route('admin.features.index')
            ->with(
                'success',
                'Objek diperbarui sebagai draft dan harus diverifikasi kembali.'
            );
    }

    public function destroy(MapFeature $feature): RedirectResponse
    {
        abort_if($feature->house()->exists(), 409, 'Arsipkan rumah melalui menu Rumah & Keluarga.');
        DB::transaction(function () use ($feature): void {
            $feature = MapFeature::query()->lockForUpdate()->findOrFail($feature->id);
            abort_if($feature->house()->exists(), 409, 'Arsipkan rumah melalui menu Rumah & Keluarga.');
            $before = $feature->toArray();
            $feature->update(['publication_status' => 'archived']);
            AuditWriter::write('feature.archived', $feature, $before, $feature->toArray());
        });

        return redirect()->route('admin.features.index')
            ->with('success', 'Objek diarsipkan.');
    }

    private function logBoundaryLoadFailure(
        Request $request,
        string $action,
        ?int $entityId,
        RuntimeException $exception
    ): void {
        try {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'entity_type' => 'map_feature',
                'entity_id' => $entityId,
                'changes' => [
                    'reason' => 'Batas referensi desa gagal diverifikasi.',
                    'failure_detail' => $exception->getMessage(),
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'boundary' => [
                        'source' => '../public/data/Pilangsari_Referensi.geojson',
                        'sha256' => null,
                        'metadata_status' => 'unavailable',
                    ],
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $auditException) {
            report($auditException);
        }
    }

    private function logBoundaryRejection(
        Request $request,
        VillageBoundaryValidator $boundary,
        string $action,
        ?int $entityId,
        ValidationException $exception
    ): void {
        $boundaryErrors = [];

        foreach ($exception->errors() as $messages) {
            foreach ($messages as $message) {
                $text = mb_strtolower((string) $message);

                if (
                    str_contains($text, 'batas referensi') ||
                    str_contains($text, 'di luar polygon')
                ) {
                    $boundaryErrors[] = (string) $message;
                }
            }
        }

        // Audit hanya untuk penolakan terkait batas desa.
        if ($boundaryErrors === []) {
            return;
        }

        $metadata = null;

        try {
            $metadata = $boundary->metadata();
        } catch (\Throwable $metadataException) {
            report($metadataException);
        }

        try {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'entity_type' => 'map_feature',
                'entity_id' => $entityId,
                'changes' => [
                    'reason' => $boundaryErrors,
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'boundary' => $metadata,
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $auditException) {
            // Kegagalan audit tidak boleh membuat koordinat tidak valid diterima.
            report($auditException);
        }
    }

    private function validateFeature(
        Request $request,
        VillageBoundaryValidator $boundary
    ): array {
        $validated = $request->validate([
            'layer_id' => ['required', 'integer', 'exists:map_layers,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'geometry' => ['nullable', 'json', 'max:1000000'],
        ]);

        $geometryInput = $validated['geometry'] ?? null;
        unset($validated['geometry']);

        if ($geometryInput !== null && trim($geometryInput) !== '') {
            try {
                $geometry = json_decode(
                    $geometryInput,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $exception) {
                throw ValidationException::withMessages([
                    'geometry' => 'GeoJSON tidak valid.',
                ]);
            }

            if (! GeoJsonGeometry::isValid($geometry) || ($geometry['type'] ?? null) !== 'Point') {
                throw ValidationException::withMessages([
                    'geometry' => 'Objek terkelola hanya menerima GeoJSON Point yang valid.',
                ]);
            }

            $validated['geometry'] = $geometry;

            // Validasi seluruh jenis geometri, bukan hanya layer titik.
            // Ini mencegah garis/poligon yang melintasi keluar batas desa
            // meskipun titik awal dan akhirnya masih berada di dalam desa.
            if (! $boundary->geometryWithinBoundary($geometry)) {
                throw ValidationException::withMessages([
                    'geometry' => 'Geometri berada di luar atau melintasi batas referensi Desa Pilangsari. Data tidak disimpan.',
                ]);
            }
        }

        $layer = MapLayer::findOrFail($validated['layer_id']);
        if ($layer->status === 'archived') {
            throw ValidationException::withMessages(['layer_id' => 'Layer yang diarsipkan tidak menerima objek baru atau perubahan.']);
        }
        if ($layer->layer_type !== 'point') {
            throw ValidationException::withMessages(['geometry' => 'Objek terkelola hanya dapat disimpan pada layer Point.']);
        }

        // Layer titik mewajibkan koordinat eksplisit dan valid.
        if ($layer->layer_type === 'point') {
            if (
                ! isset($validated['latitude'], $validated['longitude']) ||
                ! is_numeric($validated['latitude']) ||
                ! is_numeric($validated['longitude'])
            ) {
                throw ValidationException::withMessages([
                    'latitude' => 'Latitude dan longitude wajib diisi untuk objek titik.',
                ]);
            }

            if (
                isset($validated['geometry']) &&
                ($validated['geometry']['type'] ?? null) !== 'Point'
            ) {
                throw ValidationException::withMessages([
                    'geometry' => 'Layer titik hanya menerima geometri Point.',
                ]);
            }

            // Bila GeoJSON Point disertakan, pastikan sama dengan koordinat form.
            if (isset($validated['geometry'])) {
                $coords = $validated['geometry']['coordinates'];

                if (
                    ! is_array($coords) ||
                    ! isset($coords[0], $coords[1]) ||
                    ! is_numeric($coords[0]) ||
                    ! is_numeric($coords[1]) ||
                    abs((float) $coords[0] - (float) $validated['longitude']) > 0.0000001 ||
                    abs((float) $coords[1] - (float) $validated['latitude']) > 0.0000001
                ) {
                    throw ValidationException::withMessages([
                        'geometry' => 'Koordinat GeoJSON Point harus sama dengan longitude dan latitude.',
                    ]);
                }
            }

            try {
                $inside = $boundary->contains(
                    (float) $validated['longitude'],
                    (float) $validated['latitude']
                );
            } catch (RuntimeException $exception) {
                report($exception);

                throw ValidationException::withMessages([
                    'latitude' => 'Penyimpanan ditolak karena batas referensi desa tidak dapat diverifikasi.',
                ]);
            }

            if (! $inside) {
                throw ValidationException::withMessages([
                    'latitude' => 'Koordinat berada di luar polygon batas referensi Desa Pilangsari. Data tidak disimpan.',
                ]);
            }

            // Simpan satu sumber geometri titik yang konsisten.
            $validated['geometry'] = [
                'type' => 'Point',
                'coordinates' => [
                    (float) $validated['longitude'],
                    (float) $validated['latitude'],
                ],
            ];
        }

        return $validated;
    }

    private function lockAndValidateLayer(array $validated): void
    {
        $layer = MapLayer::query()->lockForUpdate()->findOrFail($validated['layer_id']);
        if ($layer->status === 'archived' || $layer->layer_type !== 'point' || ! GeoJsonGeometry::matchesLayerType($validated['geometry'] ?? null, $layer->layer_type)) {
            throw ValidationException::withMessages(['layer_id' => 'Layer berubah atau tidak menerima jenis geometri ini. Muat ulang dan periksa layer.']);
        }
    }
}
