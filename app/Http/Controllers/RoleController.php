<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('role.view');

        $roles = Role::withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
            ]);

        return Inertia::render('admin/role/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('role.create');

        return Inertia::render('admin/role/create', [
            'permissionGroups' => $this->buildPermissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('role.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('role.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('role.update');

        return Inertia::render('admin/role/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionGroups' => $this->buildPermissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('role.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('role.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('role.delete');

        if ($role->users()->count() > 0) {
            return redirect()->route('role.index')
                ->with('error', 'Cannot delete a role that is assigned to users.');
        }

        $role->delete();

        return redirect()->route('role.index')
            ->with('success', 'Role deleted successfully.');
    }

    public function editPermissions(Role $role): Response
    {
        $this->authorize('role.update');

        return Inertia::render('admin/role/permissions', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionGroups' => $this->buildPermissionGroups(),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('role.update');

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('role.permissions', $role)
            ->with('success', 'Permissions updated successfully.');
    }

    private function buildPermissionGroups(): Collection
    {
        return collect(config('permissions.modules', []))
            ->map(fn (array $module) => [
                'label' => $module['label'],
                'group' => $module['group'],
                'permissions' => collect($module['permissions'])
                    ->map(fn (string $label, string $key) => [
                        'name' => $key,
                        'label' => $label,
                    ])
                    ->values(),
            ])
            ->groupBy('group')
            ->map(fn ($modules, string $group) => [
                'group' => $group,
                'modules' => $modules->values(),
            ])
            ->values();
    }
}
