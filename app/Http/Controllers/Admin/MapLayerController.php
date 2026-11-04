<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Services\AuditWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MapLayerController extends Controller
{
    public function index(): View
    {
        return view('admin.layers.index', ['layers' => MapLayer::withCount('features')->orderBy('name')->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.layers.form', ['layer' => new MapLayer]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'layer_type' => ['required', 'in:point'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'source_name' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['slug'] = Str::slug($validated['name']).'-'.Str::lower(Str::random(5));
        $validated['status'] = 'draft';
        $validated['is_public'] = false;
        MapLayer::create($validated);

        return redirect()->route('admin.layers.index')->with('success', 'Layer dibuat sebagai draft.');
    }

    public function edit(MapLayer $layer): View
    {
        return view('admin.layers.form', compact('layer'));
    }

    public function update(Request $request, MapLayer $layer): RedirectResponse
    {
        if (PublicationCategory::where('source_layer_id', $layer->id)->exists()) {
            abort_if($request->input('layer_type') !== $layer->layer_type || $request->input('status') === 'archived', 409, 'Tipe dan arsip layer sumber sistem dilindungi.');
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'layer_type' => ['required', 'in:point,line,polygon'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,published,archived'],
            'is_public' => ['required', 'boolean'],
        ]);

        if ($validated['is_public'] && $validated['status'] !== 'published') {
            throw ValidationException::withMessages([
                'is_public' => 'Layer hanya dapat diizinkan untuk publik jika statusnya Published.',
            ]);
        }

        if ($layer->layer_type !== $validated['layer_type'] && $validated['layer_type'] !== 'point') {
            throw ValidationException::withMessages(['layer_type' => 'Layer objek baru hanya menerima Point.']);
        }

        DB::transaction(function () use ($layer, $validated): void {
            $layer = MapLayer::whereKey($layer->id)->lockForUpdate()->firstOrFail();
            if (PublicationCategory::where('source_layer_id', $layer->id)->exists()) {
                abort_if($validated['layer_type'] !== $layer->layer_type || $validated['status'] === 'archived', 409, 'Layer sumber sistem dilindungi.');
            }
            abort_if($layer->features()->exists() && $validated['layer_type'] !== $layer->layer_type, 409, 'Jenis layer berisi objek tidak boleh diubah.');
            $before = $layer->toArray();
            $layer->update($validated);
            AuditWriter::write('layer.updated', $layer, $before, $layer->toArray());
        });

        return redirect()->route('admin.layers.index')->with('success', 'Layer diperbarui.');
    }

    public function destroy(MapLayer $layer): RedirectResponse
    {
        DB::transaction(function () use ($layer): void {
            $layer = MapLayer::whereKey($layer->id)->lockForUpdate()->firstOrFail();
            abort_if(PublicationCategory::where('source_layer_id', $layer->id)->exists(), 409, 'Layer sumber kategori sistem tidak boleh dihapus atau diarsipkan.');
            abort_if($layer->features()->exists(), 409, 'Layer masih mempunyai objek; tinjau relasinya sebelum mengarsipkan.');
            $before = $layer->toArray();
            $layer->update(['status' => 'archived', 'is_public' => false]);
            AuditWriter::write('layer.archived', $layer, $before, $layer->toArray());
        });

        return redirect()->route('admin.layers.index')->with('success', 'Layer diarsipkan.');
    }
}
