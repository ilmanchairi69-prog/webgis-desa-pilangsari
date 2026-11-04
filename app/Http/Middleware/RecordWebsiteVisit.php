<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordWebsiteVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->ajax() || $request->wantsJson()
            || preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|headless|preview/i', (string) $request->userAgent())
            || in_array(strtolower((string) $request->header('Purpose')), ['prefetch', 'preview'], true)
            || ! $response->isSuccessful()) {
            return $response;
        }

        $timestamp = now()->timestamp;
        $lastActivity = $request->session()->get('website_visit_last_activity');
        if (! is_numeric($lastActivity) || $timestamp - (int) $lastActivity >= 1800) {
            $date = now('Asia/Jakarta')->toDateString();
            DB::table('website_visit_counts')->insertOrIgnore(['visit_date' => $date, 'visits' => 0]);
            DB::table('website_visit_counts')->where('visit_date', $date)->increment('visits');
        }
        $request->session()->put('website_visit_last_activity', $timestamp);

        return $response;
    }
}
