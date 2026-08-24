<?php

namespace App\Http\Controllers;

use App\Mail\TwoFactorBulkMailable;
use App\Models\Fonction;
use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\ValidationRule;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

class UtilisateurController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $query = User::with('roles', 'structure')->orderBy('name');
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%");
            });
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $request->role));
        }
        if ($request->filled('structure_id')) {
            $structureId = (int) $request->structure_id;
            $structure = Structure::where('id', $structureId)->first();
            if ($structure) {
                $ids = $structure->idsAvecDescendants();
                $query->whereIn('structure_id', $ids);
            } else {
                $query->where('structure_id', $structureId);
            }
        }
        if ($request->filled('actif')) {
            $query->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN));
        }
        $perPage = (int) $request->get('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100]) ? $perPage : 15;
        $users = $query->paginate($perPage)->withQueryString();
        $roles = Role::where('guard_name', 'web')->orderBy('name')->get();
        $structures = Structure::where('actif', true)->orderBy('nom')->get();

        return view('utilisateurs.index', compact('users', 'roles', 'structures'));
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $query = User::with('roles', 'structure')->orderBy('name');
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%");
            });
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $request->role));
        }
        if ($request->filled('structure_id')) {
            $query->where('structure_id', (int) $request->structure_id);
        }
        if ($request->filled('actif')) {
            $query->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN));
        }
        $users = $query->get();

        $filename = 'utilisateurs_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($users) {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($stream, ['Nom', 'Email', 'Email professionnel', 'Téléphone', 'Rôle', 'Structure', 'Actif', 'Créé le'], ';');
            foreach ($users as $u) {
                fputcsv($stream, [
                    $u->name,
                    $u->email,
                    $u->email_professionnel ?? '',
                    $u->telephone ?? '',
                    $u->roles->first()?->name ?? '',
                    $u->structure?->nom ?? '',
                    ($u->actif ?? true) ? 'Oui' : 'Non',
                    $u->created_at->format('d/m/Y H:i'),
                ], ';');
            }
            fclose($stream);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        $this->authorize('create', User::class);
        $roles = Role::where('guard_name', 'web')->orderBy('name')->get();
        $structures = Structure::where('actif', true)->orderBy('nom')->get();

        return view('utilisateurs.create', compact('roles', 'structures'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'email_professionnel' => ['nullable', 'string', 'email', 'max:255'],
            'telephone' => $this->reglesTelephoneSms(),
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', 'exists:roles,name'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'actif' => ['boolean'],
        ], $this->messagesTelephoneSms());

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'email_professionnel' => $request->email_professionnel ?: null,
            'telephone' => $this->normaliserTelephoneSms($request->input('telephone')),
            'password' => Hash::make($request->password),
            'structure_id' => $request->structure_id ?: null,
            'actif' => $request->boolean('actif', true),
        ]);
        $user->assignRole($request->role);

        JournalAudit::log('utilisateur.creation', 'utilisateurs', ['user_id' => $user->id]);
        Log::channel('eged')->info('Utilisateur créé', ['user_id' => $user->id, 'email' => $user->email, 'by' => auth()->id()]);

        return redirect()->route('utilisateurs.index')->with('success', 'Utilisateur créé.');
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);
        $user->load('roles', 'structure');

        return view('utilisateurs.show', ['utilisateur' => $user]);
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);
        $user->load([
            'roles',
            'structures' => fn ($q) => $q->with('fonction')->orderBy('nom'),
        ]);
        $roles = Role::where('guard_name', 'web')->orderBy('name')->get();
        $structures = Structure::orderBy('nom')->get();
        $structuresActives = Structure::where('actif', true)->with('fonction')->orderBy('nom')->get();
        $fonctions = Fonction::where('actif', true)->orderBy('libelle')->get();
        $structuresDisponibles = $structuresActives->whereNotIn('id', $user->structures->pluck('id')->all())->values();

        return view('utilisateurs.edit', [
            'utilisateur' => $user,
            'roles' => $roles,
            'structures' => $structures,
            'fonctions' => $fonctions,
            'structuresDisponibles' => $structuresDisponibles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'telephone' => $this->reglesTelephoneSms(),
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', 'exists:roles,name'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'actif' => ['boolean'],
            'documents_view_hierarchique' => ['nullable', 'boolean'],
        ], $this->messagesTelephoneSms());

        $actif = $request->boolean('actif', true);
        if ($user->id === auth()->id() && ! $actif) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'email_professionnel' => $request->email_professionnel ?: null,
            'telephone' => $this->normaliserTelephoneSms($request->input('telephone')),
            'structure_id' => $request->structure_id ?: null,
            'actif' => $actif,
        ]);
        $racine = $user->dossierRacineMesDossiers;
        if ($racine) {
            $racine->update(['structure_id' => $user->structure_id]);
        }
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }
        $user->syncRoles([$request->role]);

        if ($request->boolean('documents_view_hierarchique')) {
            $user->givePermissionTo('documents.view-hierarchique');
        } else {
            $user->revokePermissionTo('documents.view-hierarchique');
        }

        JournalAudit::log('utilisateur.modification', 'utilisateurs', ['user_id' => $user->id]);
        Log::channel('eged')->info('Utilisateur mis à jour', ['user_id' => $user->id, 'by' => auth()->id()]);

        return redirect()
            ->route('utilisateurs.edit', $user)
            ->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        JournalAudit::log('utilisateur.suppression', 'utilisateurs', ['user_id' => $user->id]);
        Log::channel('eged')->info('Utilisateur supprimé', ['user_id' => $user->id, 'email' => $user->email, 'by' => auth()->id()]);
        $user->delete();

        return redirect()->route('utilisateurs.index')->with('success', 'Utilisateur supprimé.');
    }

    /**
     * Activer ou désactiver la 2FA en masse pour les utilisateurs sélectionnés.
     */
    public function bulkToggle2FA(Request $request)
    {
        abort_unless($request->user()->can('utilisateurs.edit'), 403);

        $request->validate([
            'action' => 'required|in:enable,disable',
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $userIds = $request->user_ids;
        $action = $request->action;
        $users = User::whereIn('id', $userIds)->get();

        if ($action === 'enable') {
            $google2fa = new Google2FA;
            $issuer = config('app.name');

            foreach ($users as $user) {
                $secret = $google2fa->generateSecretKey(32);
                $recoveryCodes = $user->generateRecoveryCodes(8);
                $user->enableTwoFactor($secret, $recoveryCodes);

                $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?'.http_build_query([
                    'size' => '200x200',
                    'data' => $google2fa->getQRCodeUrl($issuer, $user->email, $secret),
                ]);

                $destinataire = ! empty($user->email_professionnel) ? $user->email_professionnel : $user->email;
                Mail::to($destinataire)->send(new TwoFactorBulkMailable($user, $secret, $recoveryCodes, $qrCodeUrl));
            }

            return back()->with('success', count($users).' utilisateur(s) : 2FA activée, emails envoyés.');
        }

        foreach ($users as $user) {
            $user->disableTwoFactor();
        }

        return back()->with('success', count($users).' utilisateur(s) : 2FA désactivée.');
    }

    /**
     * @return list<ValidationRule|string|callable>
     */
    private function reglesTelephoneSms(): array
    {
        return [
            'nullable',
            'string',
            'max:30',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || trim((string) $value) === '') {
                    return;
                }

                $norm = app(SmsService::class)->normalizeSmsPhone((string) $value);
                if ($norm === '' || ! preg_match('/^2420\d{8}$/', $norm)) {
                    $fail('Le numéro SMS doit être un mobile Congo valide (ex. +242 06 XXX XX XX).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messagesTelephoneSms(): array
    {
        return [
            'telephone.max' => 'Le numéro de téléphone ne peut pas dépasser 30 caractères.',
        ];
    }

    private function normaliserTelephoneSms(?string $telephone): ?string
    {
        if ($telephone === null || trim($telephone) === '') {
            return null;
        }

        $norm = app(SmsService::class)->normalizeSmsPhone($telephone);

        return $norm !== '' ? $norm : null;
    }
}
