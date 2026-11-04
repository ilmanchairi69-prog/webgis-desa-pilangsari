<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\AuditWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', Rule::in(array_keys(Complaint::CATEGORIES))], 'status' => ['nullable', Rule::in(array_keys(Complaint::STATUSES))], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $query = Complaint::query();
        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')->orWhere('ticket_number', 'like', '%'.$filters['search'].'%'));
        }
        foreach (['category', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }
        $complaints = $query->latest()->paginate(15)->withQueryString();
        $counts = Complaint::selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('admin.complaints.index', compact('complaints', 'counts', 'filters'));
    }

    public function show(Complaint $complaint): View
    {
        return view('admin.complaints.show', compact('complaint'));
    }

    public function download(Complaint $complaint): StreamedResponse
    {
        abort_unless($complaint->attachment_path && Storage::disk('local')->exists($complaint->attachment_path), 404);

        return Storage::disk('local')->download($complaint->attachment_path, 'lampiran-'.basename($complaint->attachment_path), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Complaint::STATUSES))], 'admin_response' => ['nullable', 'string', 'max:5000']]);
        DB::transaction(function () use ($complaint, $data, $request): void {
            $complaint = Complaint::whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $complaint->status];
            $complaint->update(['status' => $data['status'], 'admin_response' => $data['admin_response'] ?? null, 'responded_by' => $request->user()->id, 'responded_at' => now()]);
            AuditWriter::write('complaint.updated', $complaint, $before, ['status' => $complaint->status, 'response_updated' => true]);
        });

        return redirect()->route('admin.complaints.show', $complaint)->with('success', 'Penanganan pengaduan tersimpan.');
    }
}
