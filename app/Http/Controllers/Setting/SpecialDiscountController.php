<?php

namespace App\Http\Controllers\Setting;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\SpecialDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpecialDiscountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('setting.special-discount.view');

        $specialDiscounts = SpecialDiscount::query()
            ->ownBranch()
            ->when($request->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('min_amount')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SpecialDiscount $discount): array => [
                'id' => $discount->id,
                'name' => $discount->name,
                'min_amount' => (float) $discount->min_amount,
                'max_amount' => $discount->max_amount !== null ? (float) $discount->max_amount : null,
                'discount_type' => $discount->discount_type->value,
                'discount_value' => (float) $discount->discount_value,
                'status' => $discount->status,
                'amount_label' => $discount->amountLabel(),
                'discount_label' => $discount->discountLabel(),
            ]);

        return Inertia::render('admin/setting/special-discount/index', [
            'specialDiscounts' => $specialDiscounts,
            'filters' => $request->only('search'),
            'discountTypes' => collect(DiscountType::cases())->map(fn (DiscountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('setting.special-discount.create');

        $data = $this->validated($request);
        $data['branch_id'] = Auth::user()?->branch_id;

        SpecialDiscount::create($data);

        return redirect()->route('setting.special-discount.index')
            ->with('success', 'Special discount created successfully.');
    }

    public function update(Request $request, SpecialDiscount $specialDiscount): RedirectResponse
    {
        $this->authorize('setting.special-discount.update');
        $this->authorizeBranch($specialDiscount);

        $specialDiscount->update($this->validated($request));

        return redirect()->route('setting.special-discount.index')
            ->with('success', 'Special discount updated successfully.');
    }

    public function destroy(SpecialDiscount $specialDiscount): RedirectResponse
    {
        $this->authorize('setting.special-discount.delete');
        $this->authorizeBranch($specialDiscount);

        if ($specialDiscount->sells()->exists()) {
            return back()->with('error', 'Cannot delete special discount that has been used in sales.');
        }

        $specialDiscount->delete();

        return redirect()->route('setting.special-discount.index')
            ->with('success', 'Special discount deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'boolean'],
        ]);

        if ($data['discount_type'] === DiscountType::Percent->value && (float) $data['discount_value'] > 100) {
            abort(422, 'Percent discount cannot exceed 100.');
        }

        return $data;
    }

    private function authorizeBranch(SpecialDiscount $specialDiscount): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return;
        }

        if ($specialDiscount->branch_id !== null && (int) $specialDiscount->branch_id !== (int) $branchId) {
            abort(403);
        }
    }
}
