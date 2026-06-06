<?php

namespace App\Http\Controllers\Account;

use App\Enums\VoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreVoucherRequest;
use App\Http\Requests\Account\UpdateVoucherRequest;
use App\Models\Voucher;
use App\Services\VoucherAccountsPicker;
use App\Services\VoucherContactPicker;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    public function index(Request $request): Response
    {
        $this->authorize('accounts.view');

        $typeSlug = $request->string('type')->toString() ?: 'journal';
        $type = VoucherType::fromSlug($typeSlug) ?? VoucherType::Journal;

        $vouchers = Voucher::query()
            ->where('type', $type)
            ->with(['party', 'createdBy:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('voucher_no', 'like', "%{$s}%")
                    ->orWhere('transaction_reference', 'like', "%{$s}%")
                    ->orWhere('narration', 'like', "%{$s}%");
            }))
            ->latest('date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/accounts/vouchers/index', [
            'vouchers' => $vouchers,
            'activeType' => $type->slug(),
            'accountsPicker' => VoucherAccountsPicker::forType($type),
            'assetAccounts' => VoucherAccountsPicker::assetLeafAccounts(),
            'contacts' => VoucherContactPicker::contacts(),
            'filters' => $request->only('search', 'type'),
            'defaults' => [
                'voucher_no' => VoucherService::nextVoucherNo($type),
                'transaction_reference' => VoucherService::nextTransactionReference(),
                'date' => now()->format('Y-m-d'),
            ],
        ]);
    }

    public function store(StoreVoucherRequest $request): RedirectResponse
    {
        $this->authorize('accounts.create');

        $user = Auth::user();
        abort_unless($user, 403);

        try {
            $this->voucherService->store($request->validated(), $user);
        } catch (\Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()])->withInput();
        }

        $type = VoucherType::from((int) $request->input('type'));

        return redirect()
            ->route('accounts.vouchers.index', ['type' => $type->slug()])
            ->with('success', 'Voucher created successfully.');
    }

    public function show(Voucher $voucher): JsonResponse
    {
        $this->authorize('accounts.view');

        $voucher->load([
            'lines.account:id,code,name',
            'party:id,name',
            'fromAccount:id,code,name',
            'toAccount:id,code,name',
            'paymentAccount:id,code,name',
            'createdBy:id,name',
        ]);

        return response()->json([
            'voucher' => $this->formatVoucher($voucher),
        ]);
    }

    public function update(UpdateVoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        $this->authorize('accounts.update');

        try {
            $this->voucherService->update($voucher, $request->validated());
        } catch (\Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('accounts.vouchers.index', ['type' => $voucher->type->slug()])
            ->with('success', 'Voucher updated successfully.');
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $this->authorize('accounts.delete');

        $type = $voucher->type->slug();

        try {
            $this->voucherService->destroy($voucher);
        } catch (\Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('accounts.vouchers.index', ['type' => $type])
            ->with('success', 'Voucher deleted successfully.');
    }

    public function nextNumber(Request $request): JsonResponse
    {
        $this->authorize('accounts.create');

        $type = VoucherType::fromSlug($request->string('type')->toString() ?? '') ?? VoucherType::Journal;

        return response()->json([
            'voucher_no' => VoucherService::nextVoucherNo($type),
            'transaction_reference' => VoucherService::nextTransactionReference(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatVoucher(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'type' => $voucher->type->value,
            'type_slug' => $voucher->type->slug(),
            'voucher_no' => $voucher->voucher_no,
            'date' => $voucher->date->format('Y-m-d'),
            'transaction_reference' => $voucher->transaction_reference,
            'party_type' => $voucher->party_type,
            'party_id' => $voucher->party_id,
            'party_key' => VoucherContactPicker::partyKey($voucher->party_type, $voucher->party_id),
            'party_name' => $voucher->party?->name,
            'from_account_id' => $voucher->from_account_id,
            'to_account_id' => $voucher->to_account_id,
            'payment_account_id' => $voucher->payment_account_id,
            'narration' => $voucher->narration,
            'total_amount' => (float) $voucher->total_amount,
            'lines' => $voucher->lines->map(fn ($line) => [
                'id' => $line->id,
                'side' => $line->side->value,
                'account_id' => $line->account_id,
                'account_label' => $line->account ? "{$line->account->code} — {$line->account->name}" : null,
                'amount' => (float) $line->amount,
                'narration' => $line->narration,
            ])->values()->all(),
        ];
    }
}
