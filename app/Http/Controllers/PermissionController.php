<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
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
        $permissions = Permission::where('guard_name', 'web')
            ->withCount('roles')
            ->orderBy('name')
            ->paginate(15);

        return view('parametres.permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('parametres.permissions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-\.]+$/', Rule::unique('permissions', 'name')->where('guard_name', 'web')],
        ]);
        $validated['guard_name'] = 'web';
        $permission = Permission::create($validated);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        JournalAudit::log('permission.create', 'permissions', ['permission_id' => $permission->id, 'name' => $permission->name]);

        return redirect()->route('parametres.permissions.index')
            ->with('success', 'Permission « ' . $permission->name . ' » créée.');
    }

    public function edit(Permission $permission)
    {
        if ($permission->guard_name !== 'web') {
            abort(404);
        }

        return view('parametres.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission)
    {
        if ($permission->guard_name !== 'web') {
            abort(404);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-\.]+$/', Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permission->id)],
        ]);
        $oldName = $permission->name;
        $permission->update($validated);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        JournalAudit::log('permission.update', 'permissions', ['permission_id' => $permission->id, 'old_name' => $oldName, 'new_name' => $permission->name]);

        return redirect()->route('parametres.permissions.index')
            ->with('success', 'Permission mise à jour.');
    }

    public function destroy(Permission $permission)
    {
        if ($permission->guard_name !== 'web') {
            abort(404);
        }
        $name = $permission->name;
        $permission->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        JournalAudit::log('permission.delete', 'permissions', ['name' => $name]);

        return redirect()->route('parametres.permissions.index')
            ->with('success', 'Permission « ' . $name . ' » supprimée.');
    }
}
