<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicVillagePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_redirects_to_agenda_and_agenda_uses_public_navigation(): void
    {
        $this->get(route('informasi'))->assertStatus(301)->assertRedirect('/agenda');

        $this->get(route('agenda'))->assertOk()
            ->assertSee('Kalender Agenda Desa')
            ->assertSee(route('map'))
            ->assertSee(route('profil'));
    }

    public function test_agenda_displays_events_without_management_routes(): void
    {
        $this->get(route('agenda'))->assertOk()
            ->assertSee('Sosialisasi Website dan Peta Digital Desa Pilangsari')
            ->assertSee('Pemberitahuan dan Pengenalan Layanan Informasi Digital Desa')
            ->assertSee('2026-11-21')
            ->assertSee('2026-11-08')
            ->assertSee(route('map'));
        $this->post('/agenda')->assertStatus(405);
    }

    public function test_village_profile_is_public_and_links_to_map(): void
    {
        $this->get(route('profil'))->assertOk()
            ->assertSee('Profil Desa')
            ->assertSee('Parwo Rejo')
            ->assertSee('17 RT')
            ->assertSee(route('map'));
    }

    public function test_village_government_displays_supplied_officials_and_navigation(): void
    {
        $this->get(route('pemerintahan'))->assertOk()
            ->assertSee('Ahmat Munadi, S.Pd.I')
            ->assertSee('Ahmad Syaifudin')
            ->assertSee('Kasi Pelayanan')
            ->assertSee('Menuju Pilangsari Desa Digital')
            ->assertSee('Jelajahi Peta Digital Desa')
            ->assertSee(route('profil'))
            ->assertSee(route('map'));
    }
}
