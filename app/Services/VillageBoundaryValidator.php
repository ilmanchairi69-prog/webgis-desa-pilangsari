<?php

namespace App\Services;

use RuntimeException;

final class VillageBoundaryValidator
{
    private const GEOJSON_RELATIVE_PATH = 'data/Pilangsari_Referensi.geojson';

    private array $polygons;

    private string $sha256;

    private string $sourcePath;

    public function __construct(?string $sourcePath = null)
    {
        $this->sourcePath = $sourcePath ?? public_path(self::GEOJSON_RELATIVE_PATH);

        if (! is_file($this->sourcePath) || ! is_readable($this->sourcePath)) {
            throw new RuntimeException(
                'Validasi ditolak: file batas referensi desa tidak tersedia atau tidak dapat dibaca.'
            );
        }

        $raw = file_get_contents($this->sourcePath);

        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException(
                'Validasi ditolak: file batas referensi desa kosong atau gagal dibaca.'
            );
        }

        try {
            $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'Validasi ditolak: GeoJSON batas desa tidak valid.',
                0,
                $exception
            );
        }

        if (
            ! is_array($document) ||
            ($document['type'] ?? null) !== 'FeatureCollection' ||
            ! isset($document['features']) ||
            ! is_array($document['features']) ||
            count($document['features']) === 0
        ) {
            throw new RuntimeException(
                'Validasi ditolak: struktur FeatureCollection batas desa tidak valid.'
            );
        }

        $polygons = [];

        foreach ($document['features'] as $feature) {
            $geometry = $feature['geometry'] ?? null;

            if (! is_array($geometry)) {
                throw new RuntimeException(
                    'Validasi ditolak: terdapat fitur batas tanpa geometri.'
                );
            }

            $type = $geometry['type'] ?? null;
            $coordinates = $geometry['coordinates'] ?? null;

            if (! is_array($coordinates)) {
                throw new RuntimeException(
                    'Validasi ditolak: koordinat geometri batas tidak valid.'
                );
            }

            if ($type === 'Polygon') {
                $polygons[] = $coordinates;
            } elseif ($type === 'MultiPolygon') {
                foreach ($coordinates as $polygon) {
                    $polygons[] = $polygon;
                }
            } else {
                throw new RuntimeException(
                    'Validasi ditolak: geometri batas harus Polygon atau MultiPolygon.'
                );
            }
        }

        if ($polygons === []) {
            throw new RuntimeException(
                'Validasi ditolak: tidak ditemukan poligon batas yang dapat digunakan.'
            );
        }

        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || ! isset($polygon[0])) {
                throw new RuntimeException('Validasi ditolak: cincin poligon tidak lengkap.');
            }

            foreach ($polygon as $ring) {
                if (
                    ! is_array($ring) ||
                    count($ring) < 4 ||
                    $ring[0] !== $ring[count($ring) - 1]
                ) {
                    throw new RuntimeException(
                        'Validasi ditolak: cincin poligon harus tertutup dan memiliki minimal empat posisi.'
                    );
                }

                foreach ($ring as $position) {
                    if (
                        ! is_array($position) ||
                        ! isset($position[0], $position[1]) ||
                        ! is_numeric($position[0]) ||
                        ! is_numeric($position[1]) ||
                        ! is_finite((float) $position[0]) ||
                        ! is_finite((float) $position[1]) ||
                        (float) $position[0] < -180 ||
                        (float) $position[0] > 180 ||
                        (float) $position[1] < -90 ||
                        (float) $position[1] > 90
                    ) {
                        throw new RuntimeException(
                            'Validasi ditolak: terdapat posisi koordinat batas yang tidak valid.'
                        );
                    }
                }
            }
        }

        $this->polygons = $polygons;
        $this->sha256 = hash('sha256', $raw);
    }

    /**
     * Koordinat GeoJSON menggunakan urutan longitude, latitude.
     * Titik pada garis batas diterima.
     */
    public function contains(float $longitude, float $latitude): bool
    {
        if (
            ! is_finite($longitude) ||
            ! is_finite($latitude) ||
            $longitude < -180 ||
            $longitude > 180 ||
            $latitude < -90 ||
            $latitude > 90
        ) {
            return false;
        }

        foreach ($this->polygons as $polygon) {
            $outer = $this->ringRelation($longitude, $latitude, $polygon[0]);

            if ($outer === -1) {
                continue;
            }

            // Titik tepat pada batas luar diterima.
            if ($outer === 0) {
                return true;
            }

            $insideHole = false;

            foreach (array_slice($polygon, 1) as $hole) {
                $relation = $this->ringRelation($longitude, $latitude, $hole);

                // Batas lubang juga merupakan bagian dari batas poligon.
                if ($relation === 0) {
                    return true;
                }

                if ($relation === 1) {
                    $insideHole = true;
                    break;
                }
            }

            if (! $insideHole) {
                return true;
            }
        }

        return false;
    }

    /**
     * Memastikan seluruh geometri berada di dalam batas desa.
     * Mendukung Point, MultiPoint, LineString, MultiLineString, Polygon,
     * dan MultiPolygon. Koordinat GeoJSON berurutan [longitude, latitude].
     */
    public function geometryWithinBoundary(array $geometry): bool
    {
        if (! GeoJsonGeometry::isValid($geometry)) {
            return false;
        }
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return false;
        }

        return match ($type) {
            'Point' => $this->validPosition($coordinates),
            'MultiPoint' => $coordinates !== [] && $this->allPositionsWithin($coordinates),
            'LineString' => $this->lineWithinBoundary($coordinates),
            'MultiLineString' => $coordinates !== [] && $this->allLinesWithin($coordinates),
            'Polygon' => $coordinates !== [] && $this->allRingsWithin($coordinates),
            'MultiPolygon' => $coordinates !== [] && $this->allPolygonsWithin($coordinates),
            default => false,
        };
    }

    private function validPosition(array $position): bool
    {
        if (! isset($position[0], $position[1]) || ! is_numeric($position[0]) || ! is_numeric($position[1])) {
            return false;
        }

        return $this->contains((float) $position[0], (float) $position[1]);
    }

    private function allPositionsWithin(array $positions): bool
    {
        foreach ($positions as $position) {
            if (! is_array($position) || ! $this->validPosition($position)) {
                return false;
            }
        }

        return true;
    }

    private function lineWithinBoundary(array $positions): bool
    {
        if (count($positions) < 2 || ! $this->allPositionsWithin($positions)) {
            return false;
        }

        for ($i = 1, $count = count($positions); $i < $count; $i++) {
            if (! $this->segmentWithinBoundary($positions[$i - 1], $positions[$i])) {
                return false;
            }
        }

        return true;
    }

    private function allLinesWithin(array $lines): bool
    {
        foreach ($lines as $line) {
            if (! is_array($line) || ! $this->lineWithinBoundary($line)) {
                return false;
            }
        }

        return true;
    }

    private function allRingsWithin(array $rings): bool
    {
        foreach ($rings as $ring) {
            if (! is_array($ring) || ! $this->lineWithinBoundary($ring)) {
                return false;
            }
        }

        return true;
    }

    private function allPolygonsWithin(array $polygons): bool
    {
        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || ! $this->allRingsWithin($polygon)) {
                return false;
            }
        }

        return true;
    }

    /** Memeriksa titik-titik potong segmen dengan seluruh sisi batas desa. */
    private function segmentWithinBoundary(array $start, array $end): bool
    {
        $x1 = (float) $start[0];
        $y1 = (float) $start[1];
        $x2 = (float) $end[0];
        $y2 = (float) $end[1];
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $cuts = [0.0, 1.0];

        foreach ($this->polygons as $polygon) {
            foreach ($polygon as $ring) {
                $count = count($ring);
                for ($i = 1; $i < $count; $i++) {
                    $ax = (float) $ring[$i - 1][0];
                    $ay = (float) $ring[$i - 1][1];
                    $bx = (float) $ring[$i][0];
                    $by = (float) $ring[$i][1];
                    $ex = $bx - $ax;
                    $ey = $by - $ay;
                    $denominator = $dx * $ey - $dy * $ex;
                    if (abs($denominator) < 1.0e-14) {
                        continue;
                    }
                    $qx = $ax - $x1;
                    $qy = $ay - $y1;
                    $t = ($qx * $ey - $qy * $ex) / $denominator;
                    $u = ($qx * $dy - $qy * $dx) / $denominator;
                    if ($t >= -1.0e-10 && $t <= 1.0 + 1.0e-10 && $u >= -1.0e-10 && $u <= 1.0 + 1.0e-10) {
                        $cuts[] = max(0.0, min(1.0, $t));
                    }
                }
            }
        }

        sort($cuts, SORT_NUMERIC);
        $cuts = array_values(array_unique(array_map(static fn ($value) => round($value, 12), $cuts)));
        foreach ($cuts as $index => $t) {
            if (! $this->contains($x1 + $dx * $t, $y1 + $dy * $t)) {
                return false;
            }
            if (isset($cuts[$index + 1])) {
                $mid = ($t + $cuts[$index + 1]) / 2;
                if (! $this->contains($x1 + $dx * $mid, $y1 + $dy * $mid)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function metadata(): array
    {
        return [
            'source' => self::GEOJSON_RELATIVE_PATH,
            'sha256' => $this->sha256,
            'polygon_count' => count($this->polygons),
        ];
    }

    /**
     * Menghasilkan:
     *  1 = di dalam cincin
     *  0 = tepat pada garis cincin
     * -1 = di luar cincin
     */
    private function ringRelation(float $x, float $y, array $ring): int
    {
        $inside = false;
        $count = count($ring);
        $epsilon = 1.0e-10;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $ring[$i][0];
            $yi = (float) $ring[$i][1];
            $xj = (float) $ring[$j][0];
            $yj = (float) $ring[$j][1];

            $cross = ($x - $xi) * ($yj - $yi)
                - ($y - $yi) * ($xj - $xi);

            $scale = max(
                1.0,
                abs($x - $xi),
                abs($y - $yi),
                abs($xj - $xi),
                abs($yj - $yi)
            );

            if (
                abs($cross) <= $epsilon * $scale &&
                $x >= min($xi, $xj) - $epsilon &&
                $x <= max($xi, $xj) + $epsilon &&
                $y >= min($yi, $yj) - $epsilon &&
                $y <= max($yi, $yj) + $epsilon
            ) {
                return 0;
            }

            if (($yi > $y) !== ($yj > $y)) {
                $intersectionX = $xi
                    + (($y - $yi) * ($xj - $xi) / ($yj - $yi));

                if ($x < $intersectionX) {
                    $inside = ! $inside;
                }
            }
        }

        return $inside ? 1 : -1;
    }
}
