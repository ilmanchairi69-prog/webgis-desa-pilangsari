<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Services\PublicFeatureData;
use App\Services\PublicMapCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMapApiController extends Controller
{
    public function objects(): JsonResponse
    {
        return $this->noStore(response()->json([
            'type' => 'FeatureCollection', 'features' => app(PublicMapCatalog::class)->features(),
        ]));
    }

    public function object(string $kind, string $id): JsonResponse
    {
        abort_unless(in_array($kind, ['house', 'facility'], true), 404);
        if ($kind === 'house') {
            $house = House::publiclyVisible()->findOrFail($id);
            $geometry = $house->publicGeometry();
            abort_unless($geometry, 404);
            $feature = PublicFeatureData::house($house, $geometry);
        } else {
            $facility = Facility::publiclyVisible()->findOrFail($id);
            abort_unless(filled($facility->name) && filled($facility->category)
                && app(PublicMapCatalog::class)->validCoordinate($facility->latitude, $facility->longitude), 404);
            $feature = PublicFeatureData::facility($facility);
        }

        return $this->noStore(response()->json($feature));
    }

    public function layers(): JsonResponse
    {
        $layers = MapLayer::query()->publiclyListed()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'layer_type', 'color']);

        return $this->noStore(response()->json(['data' => $layers]));
    }

    public function features(string $slug): JsonResponse
    {
        $layer = MapLayer::query()
            ->where('slug', $slug)
            ->publiclyListed()
            ->firstOrFail();

        $key = PublicationCategory::where('source_layer_id', $layer->id)->value('system_key');
        $features = app(PublicMapCatalog::class)->features()
            ->filter(fn (array $feature) => $feature['properties']['system_key'] === $key)->values();

        return $this->noStore(response()->json([
            'type' => 'FeatureCollection',
            'name' => $layer->name,
            'features' => $features,
        ]));
    }

    public function show(string $id): JsonResponse
    {
        $house = House::publiclyVisible()->where('map_feature_id', $id)->first();
        if ($house) {
            $geometry = $house->publicGeometry();
            abort_unless($geometry, 404);

            return $this->noStore(response()->json(PublicFeatureData::house($house, $geometry)));
        }

        $feature = MapFeature::query()->publiclyVisible()
            ->with(['house', 'layer:id,layer_type'])
            ->findOrFail($id);

        $serialized = $feature->toGeoJsonFeature();
        abort_if($serialized['geometry'] === null, 404);

        return $this->noStore(response()->json($serialized));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->noStore(response()->json(['data' => []]));
        }

        $results = app(PublicMapCatalog::class)->features()->filter(function (array $feature) use ($query): bool {
            return mb_stripos($feature['properties']['name'], $query) !== false;
        })->take(20)->map(function (array $feature): array {
            $properties = $feature['properties'];

            return [
                'id' => $properties['id'], 'kind' => $properties['kind'],
                'object_key' => $properties['object_key'] ?? 'legacy-feature:'.$properties['id'],
                'name' => $properties['name'], 'category' => $properties['category'],
                'latitude' => $feature['geometry']['type'] === 'Point' ? $feature['geometry']['coordinates'][1] : null,
                'longitude' => $feature['geometry']['type'] === 'Point' ? $feature['geometry']['coordinates'][0] : null,
            ];
        })->values();

        return $this->noStore(response()->json(['data' => $results]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, private, max-age=0');
    }
}
