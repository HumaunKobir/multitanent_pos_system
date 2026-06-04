<?php

namespace App\Http\Controllers\Account;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('accounts.view');

        $accounts = ChartOfAccount::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('code')
            ->get(['id', 'parent_id', 'code', 'account_number', 'name', 'type', 'current_balance', 'description', 'status', 'is_system']);

        $parentAccounts = ChartOfAccount::query()
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'type']);

        return Inertia::render('admin/accounts/account/index', [
            'accounts' => $accounts,
            'parentAccounts' => $parentAccounts,
            'accountTypes' => AccountType::getAccountTypes(),
            'filters' => $request->only('search', 'type'),
        ]);
    }

    public function nextCode(Request $request): JsonResponse
    {
        $this->authorize('accounts.create');

        $request->validate([
            'type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
        ]);

        $type = AccountType::from((int) $request->type);
        $parentId = $request->parent_id ? (int) $request->parent_id : null;

        return response()->json([
            'code' => ChartOfAccount::previewCode($type, $parentId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('accounts.create');

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')->whereNull('deleted_at')],
            'account_number' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(CommonStatus::class)],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        ChartOfAccount::create([
            'parent_id' => $data['parent_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'current_balance' => $data['opening_balance'] ?? 0,
        ]);

        return redirect()->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->authorize('accounts.update');

        $data = $request->validate([
            'parent_id' => ['nullable', Rule::notIn([$chartOfAccount->id]), 'exists:chart_of_accounts,id'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')->ignore($chartOfAccount->id)->whereNull('deleted_at')],
            'account_number' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(CommonStatus::class)],
        ]);

        $chartOfAccount->update([
            'parent_id' => $data['parent_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'code' => $data['code'] ?? $chartOfAccount->code,
            'account_number' => $data['account_number'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        return redirect()->route('accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->authorize('accounts.delete');

        if ($chartOfAccount->is_system) {
            return redirect()->route('accounts.index')
                ->with('error', 'System accounts cannot be deleted.');
        }

        $chartOfAccount->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Account deleted successfully.');
    }
}
