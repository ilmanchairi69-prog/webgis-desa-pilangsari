<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WebsiteVisitStatistics
{
    /** @return array<string, int> */
    public function totals(): array
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        $today = $now->startOfDay();
        $week = $today->startOfWeek();
        $month = $today->startOfMonth();
        $periods = [
            'today' => [$today, $today->addDay()],
            'yesterday' => [$today->subDay(), $today],
            'this_week' => [$week, $today->addDay()],
            'last_week' => [$week->subWeek(), $week],
            'this_month' => [$month, $today->addDay()],
            'last_month' => [$month->subMonth(), $month],
        ];

        return Cache::remember('website_visit_statistics:'.$today->toDateString(), 30, function () use ($periods): array {
            $query = DB::table('website_visit_counts');
            foreach ($periods as $key => [$start, $end]) {
                $query->selectRaw("COALESCE(SUM(CASE WHEN visit_date >= ? AND visit_date < ? THEN visits ELSE 0 END), 0) AS $key", [$start->toDateString(), $end->toDateString()]);
            }
            $row = $query->selectRaw('COALESCE(SUM(visits), 0) AS total')->first();

            return array_map('intval', (array) $row);
        });
    }
}
