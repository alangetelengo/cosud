<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
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
        $roles = Role::where('guard_name', 'web')
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->paginate(15);

        return view('parametres.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::where('guard_name', 'web')->orderBy('name')->get();

        return view('parametres.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                // Role codes (seeders) utilisent par ex. `chef_service` : on autorise `_`.
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
                'permissions' => ['array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);
        } catch (ValidationException $e) {
            Log::channel('cosud')->warning('role.store validation échouée', [
                'request_name' => (string) $request->input('name'),
                'errors' => $e->errors(),
                'user_id' => auth()->id(),
            ]);
            throw $e;
        }
        $validated['guard_name'] = 'web';
        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($request->input('permissions', []));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        JournalAudit::log('role.create', 'roles', ['role_id' => $role->id, 'role_name' => $role->name]);

        return redirect()->route('parametres.roles.index')
            ->with('success', 'Rôle « '.$role->name.' » créé.');
    }

    public function edit(Role $role)
    {
        if ($role->guard_name !== 'web') {
            abort(404);
        }
        $permissions = Permission::where('guard_name', 'web')->orderBy('name')->get();
        $role->load('permissions');

        return view('parametres.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        if ($role->guard_name !== 'web') {
            abort(404);
        }
        try {
            $validated = $request->validate([
                // Idem store : autoriser `_` (ex: chef_service).
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
                'permissions' => ['array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);
        } catch (ValidationException $e) {
            Log::channel('cosud')->warning('role.update validation échouée', [
                'role_id' => (int) $role->id,
                'request_name' => (string) $request->input('name'),
                'errors' => $e->errors(),
                'user_id' => auth()->id(),
            ]);
            throw $e;
        }
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($request->input('permissions', []));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        JournalAudit::log('role.update', 'roles', ['role_id' => $role->id, 'role_name' => $role->name]);

        return redirect()->route('parametres.roles.index')
            ->with('success', 'Rôle « '.$role->name.' » mis à jour.');
    }

    public function destroy(Role $role)
    {
        if ($role->guard_name !== 'web') {
            abort(404);
        }
        if (in_array($role->name, ['admin'])) {
            return back()->with('error', 'Le rôle « admin » ne peut pas être supprimé.');
        }
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return back()->with('error', 'Impossible de supprimer ce rôle : '.$usersCount.' utilisateur(s) l\'ont encore. Réassignez-les avant suppression.');
        }
        $name = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        JournalAudit::log('role.delete', 'roles', ['role_name' => $name]);

        return redirect()->route('parametres.roles.index')
            ->with('success', 'Rôle « '.$name.' » supprimé.');
    }
}
