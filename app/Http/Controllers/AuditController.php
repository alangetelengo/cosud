<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->hasRole('admin')) {
                abort(403, 'Accès réservé aux administrateurs.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = JournalAudit::with(['user', 'document', 'dossier'])
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        $entries = $query->paginate(25)->withQueryString();
        $users = \App\Models\User::orderBy('name')->get(['id', 'name']);
        $modules = JournalAudit::select('module')->distinct()->whereNotNull('module')->pluck('module');

        return view('parametres.audit.index', compact('entries', 'users', 'modules'));
    }
}
