<?php

namespace App\Http\Controllers\Setting;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Services\BranchCatalogOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request, BranchCatalogOptionsService $catalogOptions): Response
    {
        $this->authorize('setting.promotion.view');

        $catalog = $catalogOptions->forBranch();
        $branchId = $catalog['branch_id'];

        $promotions = Promotion::query()
            ->with('targets')
            ->ownBranch()
            ->when($request->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Promotion $promotion): array => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'scope' => $promotion->scope->value,
                'type' => $promotion->type->value,
                'discount_value' => (float) $promotion->discount_value,
                'fixed_price' => $promotion->fixed_price !== null ? (float) $promotion->fixed_price : null,
                'buy_qty' => $promotion->buy_qty,
                'get_qty' => $promotion->get_qty,
                'get_discount_percent' => $promotion->get_discount_percent !== null ? (float) $promotion->get_discount_percent : null,
                'bundle_product_ids' => $promotion->bundle_product_ids ?? [],
                'min_qty' => $promotion->min_qty !== null ? (float) $promotion->min_qty : null,
                'max_discount_amount' => $promotion->max_discount_amount !== null ? (float) $promotion->max_discount_amount : null,
                'starts_at' => $promotion->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $promotion->ends_at?->format('Y-m-d\TH:i'),
                'status' => $promotion->status,
                'priority' => $promotion->priority,
                'stack_with_product_discount' => $promotion->stack_with_product_discount,
                'stack_with_manual_line_discount' => $promotion->stack_with_manual_line_discount,
                'stack_with_invoice_discount' => $promotion->stack_with_invoice_discount,
                'stack_with_special_discount' => $promotion->stack_with_special_discount,
                'exclusive' => $promotion->exclusive,
                'target_ids' => $promotion->targets
                    ->where('target_type', $promotion->scope->value)
                    ->pluck('target_id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
                'scope_label' => $promotion->scopeLabel(),
                'type_label' => $promotion->typeLabel(),
                'discount_summary' => $promotion->discountSummary(),
                'usage_count' => $promotion->recordedUsageCount(),
            ]);

        $products = Product::query()
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'code'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
            ])
            ->values();

        return Inertia::render('admin/setting/promotion/index', [
            'promotions' => $promotions,
            'filters' => $request->only('search'),
            'promotionScopes' => collect(PromotionScope::cases())->map(fn (PromotionScope $scope) => [
                'value' => $scope->value,
                'label' => $scope->label(),
            ])->values(),
            'promotionTypes' => collect(PromotionType::cases())->map(fn (PromotionType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
            'catalogOptions' => [
                'categories' => collect($catalog['categories'])->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values(),
                'brands' => collect($catalog['brands'])->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values(),
                'products' => $products,
            ],
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $this->authorize('setting.promotion.create');

        $this->persistPromotion(new Promotion, $request->validated(), Auth::user()?->branch_id);

        return redirect()->route('setting.promotion.index')
            ->with('success', 'Promotion created successfully.');
    }

    public function update(StorePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize('setting.promotion.update');
        $this->authorizeBranch($promotion);

        $this->persistPromotion($promotion, $request->validated(), $promotion->branch_id);

        return redirect()->route('setting.promotion.index')
            ->with('success', 'Promotion updated successfully.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->authorize('setting.promotion.delete');
        $this->authorizeBranch($promotion);

        if ($promotion->hasRecordedUsage()) {
            $usageCount = $promotion->recordedUsageCount();

            return back()->with(
                'error',
                "Cannot delete promotion that has been used in {$usageCount} sale line(s).",
            );
        }

        $promotion->delete();

        return redirect()->route('setting.promotion.index')
            ->with('success', 'Promotion deleted successfully.');
    }

    /** @param array<string, mixed> $data */
    private function persistPromotion(Promotion $promotion, array $data, ?int $branchId): void
    {
        DB::transaction(function () use ($promotion, $data, $branchId) {
            $promotion->fill([
                'branch_id' => $branchId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'scope' => $data['scope'],
                'type' => $data['type'],
                'discount_value' => $data['discount_value'] ?? 0,
                'fixed_price' => $data['fixed_price'] ?? null,
                'buy_qty' => $data['buy_qty'] ?? null,
                'get_qty' => $data['get_qty'] ?? null,
                'get_discount_percent' => $data['get_discount_percent'] ?? null,
                'bundle_product_ids' => $data['bundle_product_ids'] ?? null,
                'min_qty' => $data['min_qty'],
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'status' => $data['status'],
                'priority' => $data['priority'],
                'stack_with_product_discount' => $data['stack_with_product_discount'],
                'stack_with_manual_line_discount' => $data['stack_with_manual_line_discount'],
                'stack_with_invoice_discount' => $data['stack_with_invoice_discount'],
                'stack_with_special_discount' => $data['stack_with_special_discount'],
                'exclusive' => $data['exclusive'],
            ]);
            $promotion->save();

            $promotion->targets()->delete();

            foreach ($data['target_ids'] as $targetId) {
                PromotionTarget::create([
                    'promotion_id' => $promotion->id,
                    'target_type' => $data['scope'],
                    'target_id' => (int) $targetId,
                ]);
            }
        });
    }

    private function authorizeBranch(Promotion $promotion): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return;
        }

        if ($promotion->branch_id !== null && (int) $promotion->branch_id !== (int) $branchId) {
            abort(403);
        }
    }
}
