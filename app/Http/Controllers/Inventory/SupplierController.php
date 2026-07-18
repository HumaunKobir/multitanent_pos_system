<?php

namespace App\Http\Controllers\Inventory;

use App\Concerns\ExportsFilteredList;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\InventoryAccountingService;
use App\Services\SupplierPayableDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SupplierController extends Controller
{
    use ExportsFilteredList;

    public function __construct(
        private InventoryAccountingService $accounting,
        private SupplierPayableDocumentService $payableDocuments,
    ) {}

    private function listQuery(Request $request): Builder
    {
        return $this->applyCreatedAtDateFilters(
            Supplier::ownBranch()
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%")
                        ->orWhere('company_name', 'like', "%{$s}%");
                })),
            $request,
        )->latest();
    }

    /**
     * @return Collection<int, list<string|int|float>>
     */
    private function exportRows(Request $request): Collection
    {
        return $this->listQuery($request)
            ->limit(self::LIST_EXPORT_LIMIT)
            ->get()
            ->values()
            ->map(fn (Supplier $supplier, int $index): array => [
                $index + 1,
                $supplier->company_name,
                $supplier->name,
                $supplier->phone,
                $supplier->address ?? '—',
                (float) $supplier->balance,
                optional($supplier->created_at)?->format('Y-m-d H:i') ?? '—',
            ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('party.supplier.view');

        $suppliers = $this->listQuery($request)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/supplier/index', [
            'suppliers' => $suppliers,
            'filters' => $request->only('search', 'date_from', 'date_to'),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize('party.supplier.view');

        return $this->downloadListExcel(
            'suppliers',
            ['#', 'Company', 'Name', 'Phone', 'Address', 'Balance', 'Created At'],
            $this->exportRows($request),
        );
    }

    public function exportPdf(Request $request): SymfonyResponse
    {
        $this->authorize('party.supplier.view');

        return $this->downloadListPdf(
            'Suppliers',
            ['#', 'Company', 'Name', 'Phone', 'Address', 'Balance', 'Created At'],
            $this->exportRows($request),
        );
    }

    public function exportPrint(Request $request): SymfonyResponse
    {
        $this->authorize('party.supplier.view');

        return $this->printListHtml(
            'Suppliers',
            ['#', 'Company', 'Name', 'Phone', 'Address', 'Balance', 'Created At'],
            $this->exportRows($request),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.supplier.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['branch_id'] = auth()->user()?->branch_id;
        $openingBalance = (float) ($data['opening_balance'] ?? 0);
        $data['balance'] = $openingBalance;
        unset($data['opening_balance']);

        $supplier = Supplier::create($data);

        if ($openingBalance > 0) {
            $this->accounting->postSupplierOpeningBalance(
                $supplier,
                $openingBalance,
                now()->format('Y-m-d'),
            );

            $this->payableDocuments->syncOpeningBalancePurchase(
                $supplier,
                $openingBalance,
                now()->format('Y-m-d'),
            );
        }

        return back()->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('party.supplier.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $supplier->update($data);

        return back()->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('party.supplier.delete');

        if ($supplier->purchases()->exists()) {
            return back()->with('error', 'Cannot delete supplier with purchase history.');
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted successfully.');
    }
}
