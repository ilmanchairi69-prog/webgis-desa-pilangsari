<?php

namespace Tests\Unit;

use App\Services\VillageBoundaryValidator;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class VillageBoundaryValidatorTest extends TestCase
{
    private function validator(): VillageBoundaryValidator
    {
        return app(VillageBoundaryValidator::class);
    }

    #[Test]
    public function accepts_a_known_point_inside_the_village(): void
    {
        $validator = $this->validator();
        $this->assertTrue($validator->contains(110.98743225097655, -7.35741982269287));
    }

    #[Test]
    public function rejects_a_known_point_outside_the_village(): void
    {
        $validator = $this->validator();
        $this->assertFalse($validator->contains(110.98893225097656, -7.35691982269287));
    }

    #[Test]
    public function rejects_a_line_that_crosses_outside_even_when_endpoints_are_inside(): void
    {
        $validator = $this->validator();
        $this->assertTrue($validator->contains(110.98743225097655, -7.35741982269287));
        $this->assertTrue($validator->contains(110.99043225097657, -7.35641982269287));
        $this->assertFalse($validator->geometryWithinBoundary([
            'type' => 'LineString',
            'coordinates' => [
                [110.98743225097655, -7.35741982269287],
                [110.99043225097657, -7.35641982269287],
            ],
        ]));
    }

    #[Test]
    public function rejects_a_polygon_with_positions_outside_the_village(): void
    {
        $validator = $this->validator();
        $this->assertFalse($validator->geometryWithinBoundary([
            'type' => 'Polygon',
            'coordinates' => [[
                [110.9887, -7.3567],
                [110.9892, -7.3567],
                [110.9892, -7.3571],
                [110.9887, -7.3571],
                [110.9887, -7.3567],
            ]],
        ]));
    }

    #[Test]
    public function rejects_multipoint_if_any_point_is_outside(): void
    {
        $validator = $this->validator();
        $this->assertFalse($validator->geometryWithinBoundary([
            'type' => 'MultiPoint',
            'coordinates' => [
                [110.98743225097655, -7.35741982269287],
                [110.98893225097656, -7.35691982269287],
            ],
        ]));
    }

    #[Test]
    public function fails_closed_when_boundary_file_is_missing(): void
    {
        $missingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pilangsari-boundary-file-must-not-exist-' . uniqid('', true) . '.geojson';
        $this->expectException(RuntimeException::class);
        new VillageBoundaryValidator($missingPath);
    }
}