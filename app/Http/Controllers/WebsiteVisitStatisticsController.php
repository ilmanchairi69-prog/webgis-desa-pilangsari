<?php

namespace App\Http\Controllers;

use App\Services\WebsiteVisitStatistics;
use Illuminate\Http\JsonResponse;

class WebsiteVisitStatisticsController extends Controller
{
    public function __invoke(WebsiteVisitStatistics $statistics): JsonResponse
    {
        return response()->json($statistics->totals())->header('Cache-Control', 'no-store');
    }
}
