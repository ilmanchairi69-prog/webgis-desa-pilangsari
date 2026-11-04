<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['action' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $query = AuditLog::with('user')->orderByDesc('created_at');
        if ($action = trim((string) $request->query('action'))) {
            $query->where('action', 'like', '%'.$action.'%');
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        $logs = $query->paginate(30)->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
