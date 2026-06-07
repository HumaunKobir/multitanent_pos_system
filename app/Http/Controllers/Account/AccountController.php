<?php

namespace App\Http\Controllers\Account;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\InventoryAccountingService;
use App\Services\SystemAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private InventoryAccountingService $accounting) {}

    public function index(Request $request): Response
    {
        $this->authorize('accounts.view');

        $branchId = $request->user()?->branch_id;

        SystemAccountService::ensureConfigured($branchId);

        $accounts = ChartOfAccount::query()
            ->forPanel()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('code')
            ->get(['id', 'parent_id', 'code', 'account_number', 'name', 'type', 'current_balance', 'description', 'status', 'is_system']);

        $parentAccounts = ChartOfAccount::query()
            ->forPanel()
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'type']);

        return Inertia::render('admin/accounts/account/index', [
            'accounts' => $accounts,
            'parentAccounts' => $parentAccounts,
            'accountTypes' => AccountType::getAccountTypes(),
            'cashAndBankParentId' => SystemAccountService::id(SystemAccountKey::CashAndBank, $branchId),
            'filters' => $request->only('search', 'type'),
        ]);
    }

    public function nextCode(Request $request): JsonResponse
    {
        $this->authorize('accounts.create');

        $request->validate([
            'type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $this->applyPanelScope($query))],
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

        $branchId = $request->user()?->branch_id;

        $data = $request->validate([
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $this->applyPanelScope($query))],
            'type' => ['required', Rule::enum(AccountType::class)],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')->where(
                fn ($query) => $this->applyPanelSourceToUniqueRule($query, $branchId),
            )],
            'account_number' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(CommonStatus::class)],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $openingBalance = (float) ($data['opening_balance'] ?? 0);

        $account = ChartOfAccount::create([
            ...ChartOfAccount::panelSourceAttributes($branchId),
            'parent_id' => $data['parent_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'current_balance' => 0,
        ]);

        if ($openingBalance > 0) {
            $this->accounting->postAccountOpeningBalance(
                $account,
                $openingBalance,
                now()->format('Y-m-d'),
            );
        }

        return redirect()->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->authorize('accounts.update');

        $chartOfAccount = $this->resolvePanelAccount($chartOfAccount);

        if ($chartOfAccount->is_system) {
            return redirect()->route('accounts.index')
                ->with('error', 'System accounts cannot be updated.');
        }

        $branchId = $request->user()?->branch_id;

        $data = $request->validate([
            'parent_id' => ['nullable', Rule::notIn([$chartOfAccount->id]), Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $this->applyPanelScope($query))],
            'type' => ['required', Rule::enum(AccountType::class)],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')->ignore($chartOfAccount->id)->where(
                fn ($query) => $this->applyPanelSourceToUniqueRule($query, $branchId),
            )],
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

        $chartOfAccount = $this->resolvePanelAccount($chartOfAccount);

        if ($chartOfAccount->is_system) {
            return redirect()->route('accounts.index')
                ->with('error', 'System accounts cannot be deleted.');
        }

        $chartOfAccount->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    private function resolvePanelAccount(ChartOfAccount $chartOfAccount): ChartOfAccount
    {
        return ChartOfAccount::query()->forPanel()->whereKey($chartOfAccount->id)->firstOrFail();
    }

    private function applyPanelScope($query): void
    {
        $branchId = auth()->user()?->branch_id;

        if ($branchId === null) {
            $query->whereNull('source_type')->whereNull('source_id');

            return;
        }

        $query->where('source_type', Branch::class)->where('source_id', $branchId);
    }

    private function applyPanelSourceToUniqueRule($query, ?int $branchId): void
    {
        $query->whereNull('deleted_at');

        if ($branchId === null) {
            $query->whereNull('source_type')->whereNull('source_id');

            return;
        }

        $query->where('source_type', Branch::class)->where('source_id', $branchId);
    }
}
