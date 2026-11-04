<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\House;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $houses = House::query()->whereNull('archived_at');

        // Dashboard memakai cakupan yang sama dengan daftar rumah dan peta.
        $houses->accessibleTo($user);

        $stats = [
            'houses' => (clone $houses)->count(),
            'pending' => (clone $houses)->where('verification_status', 'pending')->count(),
            'verified' => (clone $houses)->where('verification_status', 'verified')->count(),
            'published' => (clone $houses)->where('publication_status', 'published')->count(),
            'facilities' => $user->isSuperAdmin()
                ? Facility::where('status', 'published')->whereNull('archived_at')->count()
                : null,
            'admins' => $user->isSuperAdmin()
                ? User::where('role', 'admin_rt')->where('is_active', true)->count()
                : null,
        ];

        $recent = (clone $houses)
            ->with(['creator', 'familyCards', 'mapFeature'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent'));
    }
}
