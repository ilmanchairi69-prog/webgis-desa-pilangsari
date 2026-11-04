<?php

namespace App\Http\Controllers;

use App\Services\PublicMapCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $features = app(PublicMapCatalog::class)->features();

        return view('public.home', [
            'publishedFacilities' => $features->where('properties.kind', 'facility')->count(),
            'publishedHouseMarkers' => $features->where('properties.kind', 'house')->count(),
        ]);
    }
}
