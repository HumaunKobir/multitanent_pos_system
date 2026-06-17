<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Services\SystemAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('user.view');

        $users = User::query()
            ->with('branch', 'roles')
            ->listedInUserManagement()
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_id' => $user->branch_id,
                'branch_name' => $user->branch?->name,
                'status' => $user->status,
                'role_id' => $user->roles->first()?->id,
                'role_name' => $user->roles->first()?->name,
            ]);

        return Inertia::render('admin/user/index', [
            'users' => $users,
            'branches' => Branch::query()
                ->availableForUserAssignment()
                ->orderBy('name')
                ->pluck('name', 'id'),
            'roles' => Role::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('user.create');

        $data = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                $this->assignableBranchExistsRule(),
            ],
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'status' => ['required', 'in:0,1'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ]);

        $user = User::create([
            'branch_id' => (int) $data['branch_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'status' => (int) $data['status'],
        ]);

        SystemAccountService::seed((int) $data['branch_id']);

        if (! empty($data['role_id'])) {
            $user->syncRoles([$data['role_id']]);
        }

        return redirect()->route('user.index')
            ->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('user.update');

        if ($user->id === User::SUPER_ADMIN_ID) {
            abort(403);
        }

        abort_unless($this->userIsManagedViaUserList($user), 403);

        $branchRules = $user->isSuperAdmin()
            ? ['nullable', 'integer', Rule::exists('branches', 'id')]
            : [
                'required',
                'integer',
                $this->assignableBranchExistsRule(),
            ];

        $data = $request->validate([
            'branch_id' => $branchRules,
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'status' => ['required', 'in:0,1'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ]);

        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        $payload = [
            'branch_id' => $branchId,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => (int) $data['status'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        if ($branchId !== null) {
            SystemAccountService::seed($branchId);
        }
        $user->syncRoles(isset($data['role_id']) ? [$data['role_id']] : []);

        return redirect()->route('user.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('user.delete');

        if ($user->id === User::SUPER_ADMIN_ID) {
            return redirect()->route('user.index')
                ->with('error', 'Superadmin user cannot be deleted.');
        }

        abort_unless($this->userIsManagedViaUserList($user), 403);

        $user->delete();

        return redirect()->route('user.index')
            ->with('success', 'User deleted successfully.');
    }

    protected function userIsManagedViaUserList(User $user): bool
    {
        if ($user->branch_id === null || $user->isSystemEcommerceAdmin()) {
            return false;
        }

        return User::query()
            ->whereKey($user->id)
            ->managedInUserList()
            ->exists();
    }

    protected function assignableBranchExistsRule(): Exists
    {
        $ecommerceBranchId = EcommerceBranchService::resolveIdStatic();

        return Rule::exists('branches', 'id')->where(function ($query) use ($ecommerceBranchId): void {
            $query->where('id', '!=', Branch::MAIN_BRANCH_ID)
                ->orWhere('id', $ecommerceBranchId);
        });
    }
}
