<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\House;
use App\Models\PublicationCategory;
use App\Services\AuditWriter;
use App\Services\PublicMapCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicationCategoryController extends Controller
{
    public function index(): View
    {
        $readyCounts = [
            PublicationCategory::Houses => House::where('verification_status', 'verified')
                ->where('publication_status', 'published')->where('public_visible', true)->whereNull('archived_at')
                ->get()->filter(fn (House $house) => $house->publicGeometry() !== null)->count(),
            PublicationCategory::Facilities => Facility::where('status', 'published')->whereNull('archived_at')
                ->get()->filter(fn (Facility $facility) => filled($facility->name) && filled($facility->category)
                    && app(PublicMapCatalog::class)->validCoordinate($facility->latitude, $facility->longitude))->count(),
        ];

        return view('admin.publication.index', [
            'categories' => PublicationCategory::get()->keyBy('system_key'), 'readyCounts' => $readyCounts,
        ]);
    }

    public function update(Request $request, string $systemKey): JsonResponse
    {
        abort_unless(array_key_exists($systemKey, PublicationCategory::Labels), 404);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        DB::transaction(function () use ($systemKey, $data): void {
            $category = PublicationCategory::whereKey($systemKey)->lockForUpdate()->firstOrFail();
            if ($category->is_public !== (bool) $data['is_public']) {
                $before = ['system_key' => $systemKey, 'is_public' => $category->is_public];
                $category->update(['is_public' => (bool) $data['is_public']]);
                AuditWriter::write('category.visibility_changed', null, $before, ['system_key' => $systemKey, 'is_public' => $category->is_public]);
            }
        });

        return response()->json(['system_key' => $systemKey, 'is_public' => (bool) $data['is_public']])
            ->header('Cache-Control', 'no-store, private');
    }
}
