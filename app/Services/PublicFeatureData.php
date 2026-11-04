<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\PublicationCategory;

final readonly class PublicFeatureData
{
    public function __construct(private MapFeature $feature) {}

    public static function house(House $house, array $geometry): array
    {
        return [
            'type' => 'Feature', 'geometry' => $geometry,
            'properties' => [
                'id' => $house->id, 'kind' => 'house', 'system_key' => PublicationCategory::Houses,
                'object_key' => 'house:'.$house->id,
                'name' => 'Lokasi rumah warga terverifikasi', 'category' => 'Rumah warga',
            ],
        ];
    }

    public static function facility(Facility $facility): array
    {
        return [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [(float) $facility->longitude, (float) $facility->latitude]],
            'properties' => [
                'id' => $facility->id, 'kind' => 'facility', 'system_key' => PublicationCategory::Facilities,
                'object_key' => 'facility:'.$facility->id,
                'name' => $facility->name, 'category' => $facility->category,
                'description' => $facility->description, 'address' => $facility->address,
            ],
        ];
    }

    public function toArray(): array
    {
        $house = $this->feature->house;

        return [
            'type' => 'Feature',
            'id' => $this->feature->id,
            'geometry' => $this->feature->publicGeometry(),
            'properties' => [
                'id' => $this->feature->id,
                'name' => $house?->house_code ?? $this->feature->name,
                'category' => $this->feature->category,
                'kind' => $house ? 'house' : 'facility',
                'system_key' => $house ? PublicationCategory::Houses : PublicationCategory::Facilities,
            ],
        ];
    }
}
