<?php

namespace App\Http\Controllers;

use App\Services\PublicMapCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicMapController extends Controller
{
    public function index(): View
    {
        return view('public.map');
    }

    public function data(): JsonResponse
    {
        $features = app(PublicMapCatalog::class)->features();

        $boundaryPath = public_path('data/Pilangsari_Referensi.geojson');
        $boundary = is_file($boundaryPath)
            ? json_decode((string) file_get_contents($boundaryPath), true)
            : null;

        return response()->json([
            'type' => 'FeatureCollection',
            'boundary' => $boundary,
            'features' => $features,
            'meta' => [
                'count' => count($features),
                'categories' => collect(['houses' => 'Rumah Warga', 'facilities' => 'Fasilitas Umum'])
                    ->map(fn ($label, $key) => ['system_key' => $key, 'name' => $label, 'is_public' => true, 'controls_visibility' => false])->values(),
                'privacy' => 'Data keluarga bersifat privat dan tidak disertakan pada API publik.',
            ],
        ])->header('Cache-Control', 'no-store, private, max-age=0');
    }
}
