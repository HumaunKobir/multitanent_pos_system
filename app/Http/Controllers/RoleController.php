<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('role.view');

        $currentUser = $request->user();
        $isBranchScoped = $currentUser !== null && $currentUser->usesBranchPanel();

        $query = Role::withCount('users')->orderBy('name');

        if ($isBranchScoped) {
            $query->where('branch_id', $currentUser->branch_id);
        } else {
            $query->where(function ($q) use ($currentUser) {
                $q->whereNull('branch_id')
                    ->when($currentUser?->branch_id, fn ($sub) => $sub->orWhere('branch_id', $currentUser->branch_id));
            });
        }

        $roles = $query
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

    public function create(Request $request): Response
    {
        $this->authorize('role.create');

        return Inertia::render('admin/role/create', [
            'permissionGroups' => $this->buildPermissionGroups($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('role.create');

        $currentUser = $request->user();
        $isBranchScoped = $currentUser !== null && $currentUser->usesBranchPanel();

        $data = $this->validatedRolePayload($request);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'branch_id' => $isBranchScoped ? $currentUser->branch_id : null,
            'created_by_id' => $currentUser?->id,
        ]);
        $role->syncPermissions($data['permissions']);

        return redirect()->route('role.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->authorize('role.update');

        $this->ensureRoleWithinUserScope($request->user(), $role);

        return Inertia::render('admin/role/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
            'permissionGroups' => $this->buildPermissionGroups($request->user()),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('role.update');

        $this->ensureRoleWithinUserScope($request->user(), $role);

        $data = $this->validatedRolePayload($request, $role);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions']);

        return redirect()->route('role.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('role.delete');

        $this->ensureRoleWithinUserScope($request->user(), $role);

        if ($role->users()->count() > 0) {
            return redirect()->route('role.index')
                ->with('error', 'Cannot delete a role that is assigned to users.');
        }

        $role->delete();

        return redirect()->route('role.index')
            ->with('success', 'Role deleted successfully.');
    }

    public function editPermissions(Request $request, Role $role): Response
    {
        $this->authorize('role.update');

        $this->ensureRoleWithinUserScope($request->user(), $role);

        return Inertia::render('admin/role/permissions', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
            'permissionGroups' => $this->buildPermissionGroups($request->user()),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('role.update');

        $this->ensureRoleWithinUserScope($request->user(), $role);

        $permissions = $this->validatedPermissions($request);

        $role->syncPermissions($permissions);

        return redirect()->route('role.permissions', $role)
            ->with('success', 'Permissions updated successfully.');
    }

    /**
     * @return array{name: string, permissions: list<string>}
     */
    private function validatedRolePayload(Request $request, ?Role $role = null): array
    {
        $user = $request->user();
        $allowedPool = ($user !== null && ! $user->hasUnrestrictedPermissions())
            ? $user->getAllPermissions()->pluck('name')->all()
            : PermissionCatalog::names();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('roles', 'name')->ignore($role),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPool)],
        ]);

        $permissions = array_values($data['permissions'] ?? []);
        PermissionCatalog::ensureExist($permissions);

        return [
            'name' => $data['name'],
            'permissions' => $permissions,
        ];
    }

    /**
     * @return list<string>
     */
    private function validatedPermissions(Request $request): array
    {
        $user = $request->user();
        $allowedPool = ($user !== null && ! $user->hasUnrestrictedPermissions())
            ? $user->getAllPermissions()->pluck('name')->all()
            : PermissionCatalog::names();

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPool)],
        ]);

        $permissions = array_values($data['permissions'] ?? []);
        PermissionCatalog::ensureExist($permissions);

        return $permissions;
    }

    private function ensureRoleWithinUserScope(?User $user, Role $role): void
    {
        if ($user === null || $user->hasUnrestrictedPermissions()) {
            return;
        }

        if ($user->usesBranchPanel() && (int) $role->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        $allowedPermissionNames = $user->getAllPermissions()->pluck('name')->all();
        $rolePermissionNames = $role->permissions->pluck('name')->all();

        $disallowed = array_diff($rolePermissionNames, $allowedPermissionNames);
        if (! empty($disallowed)) {
            abort(403);
        }
    }

    private function buildPermissionGroups(?User $user = null): Collection
    {
        $allowedPermissions = null;
        if ($user !== null && ! $user->hasUnrestrictedPermissions()) {
            $allowedPermissions = $user->getAllPermissions()->pluck('name')->flip();
        }

        return collect(config('permissions.modules', []))
            ->map(function (array $module) use ($allowedPermissions) {
                $permissions = collect($module['permissions'])
                    ->filter(fn (string $label, string $key) => $allowedPermissions === null || $allowedPermissions->has($key))
                    ->map(fn (string $label, string $key) => [
                        'name' => $key,
                        'label' => $label,
                    ])
                    ->values();

                if ($permissions->isEmpty()) {
                    return null;
                }

                return [
                    'label' => $module['label'],
                    'group' => $module['group'],
                    'permissions' => $permissions,
                ];
            })
            ->filter()
            ->groupBy('group')
            ->map(fn ($modules, string $group) => [
                'group' => $group,
                'modules' => $modules->values(),
            ])
            ->values();
    }
}

