<?php

namespace App\Services;

final class GeoJsonGeometry
{
    public static function matchesLayerType(mixed $geometry, ?string $layerType): bool
    {
        if (! self::isValid($geometry)) {
            return false;
        }

        return match ($layerType) {
            'point' => $geometry['type'] === 'Point',
            'line' => in_array($geometry['type'], ['LineString', 'MultiLineString'], true),
            'polygon' => in_array($geometry['type'], ['Polygon', 'MultiPolygon'], true),
            default => false,
        };
    }

    public static function isValid(mixed $geometry): bool
    {
        if (! is_array($geometry) || ! is_array($geometry['coordinates'] ?? null)) {
            return false;
        }
        $coordinates = $geometry['coordinates'];

        return match ($geometry['type'] ?? null) {
            'Point' => self::position($coordinates),
            'MultiPoint' => self::every($coordinates, self::position(...)),
            'LineString' => self::line($coordinates),
            'MultiLineString' => self::every($coordinates, self::line(...)),
            'Polygon' => self::polygon($coordinates),
            'MultiPolygon' => self::every($coordinates, self::polygon(...)),
            default => false,
        };
    }

    private static function position(mixed $position): bool
    {
        if (! is_array($position) || ! array_is_list($position) || count($position) < 2 || count($position) > 3) {
            return false;
        }
        foreach ($position as $number) {
            if ((! is_int($number) && ! is_float($number)) || ! is_finite((float) $number)) {
                return false;
            }
        }

        return abs($position[0]) <= 180 && abs($position[1]) <= 90;
    }

    private static function line(mixed $positions): bool
    {
        return is_array($positions) && count($positions) >= 2 && self::every($positions, self::position(...));
    }

    private static function ring(mixed $positions): bool
    {
        return is_array($positions) && count($positions) >= 4
            && self::every($positions, self::position(...))
            && $positions[0] === $positions[count($positions) - 1];
    }

    private static function polygon(mixed $rings): bool
    {
        return self::every($rings, self::ring(...));
    }

    private static function every(mixed $items, callable $validator): bool
    {
        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            return false;
        }
        foreach ($items as $item) {
            if (! $validator($item)) {
                return false;
            }
        }

        return true;
    }
}
