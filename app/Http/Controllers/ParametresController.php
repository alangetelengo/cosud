<?php

namespace App\Http\Controllers;

use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ParametresController extends Controller
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

    public function index()
    {
        Log::channel('eged')->debug('Consultation paramètres', ['user_id' => auth()->id()]);
        $structures = Structure::where('actif', true)
            ->with('fonction')
            ->orderByRaw("CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END")
            ->orderBy('nom')
            ->get();

        return view('parametres.index', compact('structures'));
    }
}
