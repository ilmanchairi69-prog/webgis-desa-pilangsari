<?php

namespace App\Services;

class VillageBoundaryService
{
    public function contains(float $longitude, float $latitude): bool
    {
        $path = public_path('data/Pilangsari_Referensi.geojson');
        if (!is_file($path)) return false;
        $geo = json_decode((string) file_get_contents($path), true);
        if (!is_array($geo) || !isset($geo['features'])) return false;
        foreach ($geo['features'] as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (!$geometry) continue;
            $point = [$longitude, $latitude];
            $type = $geometry['type'] ?? '';
            $coordinates = $geometry['coordinates'] ?? [];
            if ($type === 'Polygon' && $this->insidePolygon($point, $coordinates)) return true;
            if ($type === 'MultiPolygon') {
                foreach ($coordinates as $polygon) if ($this->insidePolygon($point, $polygon)) return true;
            }
        }
        return false;
    }

    private function insidePolygon(array $point, array $rings): bool
    {
        if (empty($rings) || !$this->insideRing($point, $rings[0] ?? [])) return false;
        foreach (array_slice($rings, 1) as $hole) if ($this->insideRing($point, $hole)) return false;
        return true;
    }

    private function insideRing(array $point, array $ring): bool
    {
        $x = (float) $point[0]; $y = (float) $point[1]; $inside = false;
        $n = count($ring); if ($n < 3) return false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $ring[$i][0]; $yi = (float) $ring[$i][1];
            $xj = (float) $ring[$j][0]; $yj = (float) $ring[$j][1];
            $intersects = (($yi > $y) !== ($yj > $y)) && ($x < (($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 0.0000000001) + $xi));
            if ($intersects) $inside = !$inside;
        }
        return $inside;
    }
}
