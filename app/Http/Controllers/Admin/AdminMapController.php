<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\House;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMapController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $user = $request->user();

        $query = House::query()
            ->with([
                'mapFeature:id,latitude,longitude,geometry,verification_status,publication_status',
                'familyCards',
            ])
            ->whereNull('archived_at');

        $query->accessibleTo($user);

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $escaped = addcslashes($keyword, '%_\\');
            $normalizedRt = preg_replace('/^\\s*RT\\s*/i', '', $keyword);

            $query->where(function (Builder $sub) use ($escaped, $normalizedRt): void {
                $like = "%{$escaped}%";
                $sub->where('house_code', 'like', $like)
                    ->orWhere('house_label', 'like', $like)
                    ->orWhere('rt_code', 'like', $like)
                    ->orWhere('rt_code', 'like', '%'.addcslashes($normalizedRt, '%_\\').'%')
                    ->orWhere('address', 'like', $like)
                    ->orWhereHas('familyCards', function (Builder $cards) use ($like): void {
                        $cards->where('head_name', 'like', $like)
                            ->orWhere('head_of_family_name', 'like', $like);
                    });
            });
        }

        $fallbackRtCode = $user->isRtAdmin() ? $user->rt_code : null;

        $points = $query->get()->map(function (House $house) use ($fallbackRtCode): ?array {
            $feature = $house->mapFeature;
            $lat = ($house->latitude === null || (float) $house->latitude === 0.0) ? $feature?->latitude : $house->latitude;
            $lng = ($house->longitude === null || (float) $house->longitude === 0.0) ? $feature?->longitude : $house->longitude;

            if (($lat === null || $lng === null) && is_array($feature?->geometry)) {
                $coordinates = $feature->geometry['coordinates'] ?? null;
                if (($feature->geometry['type'] ?? null) === 'Point'
                    && is_array($coordinates)
                    && isset($coordinates[0], $coordinates[1])) {
                    $lng = $lng ?? $coordinates[0];
                    $lat = $lat ?? $coordinates[1];
                }
            }

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return null;
            }

            $lat = (float) $lat;
            $lng = (float) $lng;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return null;
            }

            $heads = $house->familyCards
                ->map(fn ($card) => $card->head_name ?: $card->head_of_family_name)
                ->filter()
                ->implode(', ');

            return [
                'id' => $house->id,
                'code' => $house->house_code,
                'rt' => $house->rt_code ?: $fallbackRtCode,
                'lat' => $lat,
                'lng' => $lng,
                'heads' => $heads,
                'status' => $house->verification_status,
                'publication_status' => $house->publication_status,
                'address' => $house->address,
            ];
        })->filter()->values();

        return view('admin.map', [
            'points' => $points,
            'search' => $keyword,
        ]);
    }
}
