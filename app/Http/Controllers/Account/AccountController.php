<?php

namespace App\Http\Controllers\Account;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\BranchSubscriptionService;
use App\Services\InventoryAccountingService;
use App\Services\ReportService;
use App\Services\SystemAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private InventoryAccountingService $accounting,
        private ReportService $reports,
        private BranchSubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('accounts.view');

        $user = $request->user();
        $branchId = $user?->branch_id;

        // Automatically synchronize overdue subscription dues into Chart of Accounts:
        // - On SuperAdmin: posts client subscription dues to Client Subscription Receivables (Asset) & Income (Revenue)
        // - On Branch: posts branch subscription due to Subscription Payable (Liability) & Expense
        try {
            if ($user !== null && $user->usesBranchPanel() && $user->branch !== null) {
                $this->subscriptions->syncDueLiabilityOnBranchAccess($user->branch);
            } elseif ($user !== null && ! $user->usesBranchPanel()) {
                $this->subscriptions->syncAllOverdueLiabilities();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        SystemAccountService::ensureConfigured($branchId);

        $allAccounts = ChartOfAccount::query()
            ->forPanel()
            ->orderBy('code')
            ->get(['id', 'parent_id', 'code', 'account_number', 'name', 'type', 'current_balance', 'description', 'status', 'is_system']);

        $currentYearEarnings = $this->reports->currentYearEarningsAsOf();
        $currentYearEarningsNumber = SystemAccountKey::CurrentYearEarnings->accountNumber();

        $allAccounts->transform(function (ChartOfAccount $account) use ($currentYearEarnings, $currentYearEarningsNumber) {
            if ($account->account_number === $currentYearEarningsNumber) {
                $account->current_balance = $currentYearEarnings;
                $account->description = $account->description ?: 'Computed from income and expenses until year-end close.';
            }

            return $account;
        });

        $displayBalances = $this->displayBalancesByAccountId($allAccounts);

        $accounts = $allAccounts
            ->when($request->search, fn (Collection $accounts, string $search) => $accounts
                ->filter(fn (ChartOfAccount $account) => str_contains(strtolower((string) $account->name), strtolower($search))
                    || str_contains(strtolower((string) $account->code), strtolower($search)))
                ->values())
            ->when($request->type, fn (Collection $accounts, string|int $type) => $accounts
                ->filter(function (ChartOfAccount $account) use ($type) {
                    $accountType = $account->type instanceof AccountType
                        ? $account->type->value
                        : (int) $account->type;

                    return $accountType === (int) $type;
                })
                ->values())
            ->map(function (ChartOfAccount $account) use ($displayBalances) {
                $account->setAttribute(
                    'display_balance',
                    $displayBalances[(int) $account->id] ?? round((float) $account->current_balance, 2),
                );

                return $account;
            })
            ->values();

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

    /**
     * Chart of Accounts page only: parent display balance = own + descendants.
     * Does not change current_balance or any posting logic.
     *
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @return array<int, float>
     */
    private function displayBalancesByAccountId(Collection $accounts): array
    {
        $childrenByParent = $accounts->groupBy(
            fn (ChartOfAccount $account) => $account->parent_id !== null ? (int) $account->parent_id : 0,
        );
        $memo = [];

        $compute = function (int $accountId) use (&$compute, &$memo, $accounts, $childrenByParent): float {
            if (array_key_exists($accountId, $memo)) {
                return $memo[$accountId];
            }

            $account = $accounts->firstWhere('id', $accountId);
            $own = round((float) ($account?->current_balance ?? 0), 2);
            $childSum = collect($childrenByParent->get($accountId, []))
                ->sum(fn (ChartOfAccount $child) => $compute((int) $child->id));

            return $memo[$accountId] = round($own + $childSum, 2);
        };

        $balances = [];

        foreach ($accounts as $account) {
            $balances[(int) $account->id] = $compute((int) $account->id);
        }

        return $balances;
    }
}
