<?php

namespace App\Services;

use RuntimeException;

class DemonstrationCoordinates
{
    private array $bounds;

    public function __construct(private VillageBoundaryValidator $boundary)
    {
        $document = json_decode(file_get_contents(public_path('data/Pilangsari_Referensi.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $positions = [];
        $collect = function (array $items) use (&$collect, &$positions): void {
            if (isset($items[0], $items[1]) && is_numeric($items[0]) && is_numeric($items[1])) {
                $positions[] = [(float) $items[0], (float) $items[1]];

                return;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    $collect($item);
                }
            }
        };
        foreach ($document['features'] ?? [] as $feature) {
            $collect($feature['geometry']['coordinates'] ?? []);
        }
        if ($positions === []) {
            throw new RuntimeException('Batas referensi tidak memiliki koordinat.');
        }
        $this->bounds = [
            min(array_column($positions, 0)), max(array_column($positions, 0)),
            min(array_column($positions, 1)), max(array_column($positions, 1)),
        ];
    }

    public function point(int $number): array
    {
        [$minLon, $maxLon, $minLat, $maxLat] = $this->bounds;
        $cluster = intdiv($number - 1, 6) + 1;
        for ($attempt = 0; $attempt < 10000; $attempt++) {
            $hash = hash('sha256', 'webgis-demo-'.$cluster.'-'.$attempt);
            $longitude = $minLon + ($maxLon - $minLon) * hexdec(substr($hash, 0, 8)) / 4294967295;
            $latitude = $minLat + ($maxLat - $minLat) * hexdec(substr($hash, 8, 8)) / 4294967295;
            $angle = (($number - 1) % 6) * M_PI / 3;
            $point = [round($longitude + cos($angle) * 0.00012, 7), round($latitude + sin($angle) * 0.00012, 7)];
            if ($this->boundary->contains($point[0], $point[1])) {
                return $point;
            }
        }

        throw new RuntimeException('Titik demonstrasi di dalam batas tidak ditemukan.');
    }
}
