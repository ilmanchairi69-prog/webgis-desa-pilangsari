<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\House;
use App\Models\MapFeature;
use Illuminate\Support\Collection;

class PublicMapCatalog
{
    public function features(): Collection
    {
        $houses = House::publiclyVisible()
            ->orderBy('house_code')->get()->map(function (House $house): ?array {
                $geometry = $house->publicGeometry();

                return $geometry ? PublicFeatureData::house($house, $geometry) : null;
            })->filter();
        $facilities = Facility::publiclyVisible()->orderBy('name')->get()
            ->filter(fn (Facility $facility) => filled($facility->name) && filled($facility->category)
                && $this->validCoordinate($facility->latitude, $facility->longitude))
            ->map(fn (Facility $facility) => PublicFeatureData::facility($facility));
        $legacy = MapFeature::publiclyVisible()->whereDoesntHave('house')
            ->with(['layer:id,layer_type', 'house:id,map_feature_id,house_code'])->get()
            ->map(fn (MapFeature $feature) => $feature->toGeoJsonFeature())
            ->filter(fn (array $feature) => $feature['geometry'] !== null);

        return $houses->concat($facilities)->concat($legacy)->values();
    }

    public function validCoordinate(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude)
            && is_finite((float) $latitude) && is_finite((float) $longitude)
            && (float) $latitude >= -90 && (float) $latitude <= 90
            && (float) $longitude >= -180 && (float) $longitude <= 180
            && app(VillageBoundaryValidator::class)->contains((float) $longitude, (float) $latitude);
    }
}
