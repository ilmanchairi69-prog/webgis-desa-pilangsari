<?php

namespace Tests\Unit;

use App\Services\GeoJsonGeometry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GeoJsonGeometryTest extends TestCase
{
    public static function validGeometries(): array
    {
        $a = [110.98, -7.35];
        $b = [110.99, -7.35];
        $c = [110.99, -7.36];

        return [
            [['type' => 'Point', 'coordinates' => $a]],
            [['type' => 'Point', 'coordinates' => [110.98, -7.35, 20]]],
            [['type' => 'MultiPoint', 'coordinates' => [$a, $b]]],
            [['type' => 'LineString', 'coordinates' => [$a, $b]]],
            [['type' => 'MultiLineString', 'coordinates' => [[$a, $b]]]],
            [['type' => 'Polygon', 'coordinates' => [[$a, $b, $c, $a]]]],
            [['type' => 'MultiPolygon', 'coordinates' => [[[$a, $b, $c, $a]]]]],
        ];
    }

    #[DataProvider('validGeometries')]
    public function test_supported_geojson_numeric_coordinates_are_valid(array $geometry): void
    {
        $this->assertTrue(GeoJsonGeometry::isValid($geometry));
    }

    public static function invalidGeometries(): array
    {
        return [[null], [true], ['text'], [['type' => 'Point', 'coordinates' => ['110.98', -7.35]]],
            [['type' => 'Point', 'coordinates' => [110.98, 100]]],
            [['type' => 'Point', 'coordinates' => [INF, -7.35]]],
            [['type' => 'Point', 'coordinates' => [110.98, -7.35, NAN]]],
            [['type' => 'Polygon', 'coordinates' => []]],
            [['type' => 'Polygon', 'coordinates' => [[[1, 2], [1, 3], [2, 3], [2, 2]]]]],
            [['type' => 'MultiPolygon', 'coordinates' => [true]]]];
    }

    #[DataProvider('invalidGeometries')]
    public function test_malformed_or_non_finite_geometry_is_rejected_without_throwing(mixed $geometry): void
    {
        $this->assertFalse(GeoJsonGeometry::isValid($geometry));
    }
}
