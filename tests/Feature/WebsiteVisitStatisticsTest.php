<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebsiteVisitStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_views_count_once_until_thirty_minutes_of_inactivity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'Asia/Jakarta'));
        $this->get('/')->assertOk();
        $this->get('/')->assertOk();
        $this->travel(10)->minutes();
        $this->get('/profil')->assertOk();
        $this->travel(29)->minutes();
        $this->get('/pemerintahan')->assertOk();
        $this->assertDatabaseHas('website_visit_counts', ['visit_date' => '2026-11-05', 'visits' => 1]);
        $this->travel(30)->minutes();
        $this->get('/peta')->assertOk();
        $this->assertDatabaseHas('website_visit_counts', ['visit_date' => '2026-11-05', 'visits' => 2]);
    }

    public function test_non_page_requests_and_bots_are_not_counted(): void
    {
        $this->get('/statistik-kunjungan')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->get('/css/portal.css');
        $this->get('/api/map/public')->assertOk();
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get('/profil')->assertOk();
        $this->flushHeaders()->withHeader('User-Agent', 'Googlebot')->get('/profil')->assertOk();
        $this->assertDatabaseCount('website_visit_counts', 0);
    }

    public function test_statistics_use_jakarta_calendar_periods_and_only_return_aggregates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-02 00:30:00', 'Asia/Jakarta'));
        foreach (['2026-03-02' => 2, '2026-03-01' => 3, '2026-02-23' => 5, '2026-02-22' => 7, '2026-01-31' => 11] as $date => $count) {
            DB::table('website_visit_counts')->insert(['visit_date' => $date, 'visits' => $count]);
        }
        Cache::flush();
        $this->getJson('/statistik-kunjungan')->assertOk()->assertExactJson([
            'today' => 2, 'yesterday' => 3, 'this_week' => 2, 'last_week' => 8,
            'this_month' => 5, 'last_month' => 12, 'total' => 28,
        ]);
        $this->assertSame(28, DB::table('website_visit_counts')->sum('visits'));
    }
}
