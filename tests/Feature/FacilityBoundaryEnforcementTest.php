<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\User;
use App\Services\VillageBoundaryValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FacilityBoundaryEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const INSIDE_LONGITUDE = 110.98743225097655;
    private const INSIDE_LATITUDE = -7.35741982269287;
    private const OUTSIDE_LONGITUDE = 110.98893225097656;
    private const OUTSIDE_LATITUDE = -7.35691982269287;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => 'super_admin',
            'is_active' => true,
        ])->save();

        return $user;
    }

    private function facilityPayload(float $longitude, float $latitude): array
    {
        return [
            'name' => 'Fasilitas Uji',
            'category' => 'Pendidikan',
            'description' => 'Data pengujian validasi batas.',
            'address' => 'Desa Pilangsari',
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    public function test_facility_creation_outside_village_boundary_is_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->from(route('admin.facilities.create'))
            ->post(route('admin.facilities.store'), $this->facilityPayload(
                self::OUTSIDE_LONGITUDE,
                self::OUTSIDE_LATITUDE
            ));

        $response->assertRedirect(route('admin.facilities.create'));
        $response->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('facilities', 0);
    }

    public function test_facility_inside_village_boundary_can_be_created_as_draft(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->post(route('admin.facilities.store'), $this->facilityPayload(
            self::INSIDE_LONGITUDE,
            self::INSIDE_LATITUDE
        ));

        $response->assertRedirect(route('admin.facilities.index'));
        $this->assertDatabaseHas('facilities', [
            'name' => 'Fasilitas Uji',
            'status' => 'draft',
        ]);
    }

    public function test_facility_update_outside_village_boundary_preserves_existing_data(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        $facility = Facility::create([
            'name' => 'Fasilitas Lama',
            'category' => 'Pendidikan',
            'address' => 'Alamat lama',
            'latitude' => self::INSIDE_LATITUDE,
            'longitude' => self::INSIDE_LONGITUDE,
            'status' => 'published',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->from(route('admin.facilities.edit', $facility))
            ->put(route('admin.facilities.update', $facility), $this->facilityPayload(
                self::OUTSIDE_LONGITUDE,
                self::OUTSIDE_LATITUDE
            ));

        $response->assertRedirect(route('admin.facilities.edit', $facility));
        $response->assertSessionHasErrors('latitude');

        $facility->refresh();
        $this->assertSame('Fasilitas Lama', $facility->name);
        $this->assertSame('published', $facility->status);
        $this->assertEqualsWithDelta(self::INSIDE_LATITUDE, (float) $facility->latitude, 1.0e-10);
        $this->assertEqualsWithDelta(self::INSIDE_LONGITUDE, (float) $facility->longitude, 1.0e-10);
        $this->assertDatabaseCount('facilities', 1);
    }

    public function test_facility_creation_fails_closed_when_boundary_reference_cannot_be_loaded(): void
    {
        $this->actingAs($this->superAdmin());
        $this->app->bind(VillageBoundaryValidator::class, function () {
            throw new RuntimeException('Boundary reference unavailable for test.');
        });

        $response = $this->from(route('admin.facilities.create'))
            ->post(route('admin.facilities.store'), $this->facilityPayload(
                self::INSIDE_LONGITUDE,
                self::INSIDE_LATITUDE
            ));

        $response->assertRedirect(route('admin.facilities.create'));
        $response->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('facilities', 0);
    }
}
