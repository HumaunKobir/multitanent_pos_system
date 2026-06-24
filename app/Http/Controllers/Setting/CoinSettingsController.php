<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateCoinSettingsRequest;
use App\Models\Branch;
use App\Models\CoinSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CoinSettingsController extends Controller
{
    public function index(): Response
    {
        $this->authorize('setting.coin-settings.view');

        $branch = $this->currentBranchOrFail();
        $settings = CoinSettings::query()
            ->where('branch_id', $branch->id)
            ->first();

        return Inertia::render('admin/setting/coin-settings/index', [
            'branchName' => $branch->name,
            'coinSettings' => $settings ? $this->settingsPayload($settings, $branch->name) : null,
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $this->authorize('setting.coin-settings.create');

        $branch = $this->currentBranchOrFail();

        if (CoinSettings::query()->where('branch_id', $branch->id)->exists()) {
            return redirect()
                ->route('setting.coin-settings.edit')
                ->with('success', 'Coin settings already exist for this branch.');
        }

        return Inertia::render('admin/setting/coin-settings/edit', [
            'coinSettings' => $this->defaultFormValues($branch->name),
            'isCreating' => true,
        ]);
    }

    public function store(UpdateCoinSettingsRequest $request): RedirectResponse
    {
        $this->authorize('setting.coin-settings.create');

        $branch = $this->currentBranchOrFail();

        if (CoinSettings::query()->where('branch_id', $branch->id)->exists()) {
            return redirect()
                ->route('setting.coin-settings.edit')
                ->with('success', 'Coin settings already exist for this branch.');
        }

        CoinSettings::query()->create([
            'branch_id' => $branch->id,
            ...$request->validated(),
        ]);

        return redirect()
            ->route('setting.coin-settings.index')
            ->with('success', 'Coin settings created successfully.');
    }

    public function edit(): Response|RedirectResponse
    {
        $this->authorize('setting.coin-settings.view');

        $branch = $this->currentBranchOrFail();
        $settings = CoinSettings::query()
            ->where('branch_id', $branch->id)
            ->first();

        if ($settings === null) {
            return redirect()
                ->route('setting.coin-settings.create')
                ->with('error', 'Create coin settings for this branch first.');
        }

        return Inertia::render('admin/setting/coin-settings/edit', [
            'coinSettings' => $this->settingsPayload($settings, $branch->name),
            'isCreating' => false,
        ]);
    }

    public function update(UpdateCoinSettingsRequest $request): RedirectResponse
    {
        $branch = $this->currentBranchOrFail();

        CoinSettings::query()->updateOrCreate(
            ['branch_id' => $branch->id],
            $request->validated(),
        );

        return redirect()
            ->route('setting.coin-settings.index')
            ->with('success', 'Coin settings updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultFormValues(string $branchName): array
    {
        return [
            'enabled' => false,
            'earn_spend_amount' => '100',
            'earn_coins' => '1',
            'coin_value' => '1',
            'min_redeem_coins' => '0',
            'max_redeem_percent' => '50',
            'branch_name' => $branchName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function settingsPayload(CoinSettings $settings, string $branchName): array
    {
        return [
            'enabled' => $settings->enabled,
            'earn_spend_amount' => (string) (float) $settings->earn_spend_amount,
            'earn_coins' => (string) (float) $settings->earn_coins,
            'coin_value' => (string) (float) $settings->coin_value,
            'min_redeem_coins' => (string) (float) $settings->min_redeem_coins,
            'max_redeem_percent' => (string) (float) $settings->max_redeem_percent,
            'branch_name' => $branchName,
        ];
    }

    protected function currentBranchOrFail(): Branch
    {
        $branchId = Auth::user()?->branch_id;

        abort_unless($branchId, 403);

        return Branch::query()->findOrFail($branchId);
    }
}
