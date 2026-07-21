import { AdminPagination } from '@/components/admin/pagination';
import { ListDateExportBar } from '@/components/admin/list-date-export-bar';
import { StatTile } from '@/components/dashboard/stat-tile';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';
import { Head, router, usePage } from '@inertiajs/react';
import { Package, RotateCcw } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';

function formatStock(value) {
    const amount = parseFloat(value ?? 0);

    return Number.isInteger(amount) ? String(amount) : amount.toFixed(2);
}

function formatMoney(value) {
    return `৳${Number(value ?? 0).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function variantValues(variation) {
    const stock = parseFloat(variation?.stock ?? 0);
    const cost = stock * parseFloat(variation?.purchase_price ?? 0);
    const selling = stock * parseFloat(variation?.price ?? 0);

    return {
        stock,
        cost,
        selling,
        profit: selling - cost,
    };
}

export default function InventoryStockReport({
    products,
    summary = {},
    filters,
    categories,
    brands,
    sizes = {},
    productOptions = [],
    branches = {},
    mainBranchId = null,
}) {
    const { auth, panelType } = usePage().props;
    const canFilterByBranch = panelType === 'admin' && !auth.user?.branch_id;
    const defaultBranchId = mainBranchId != null ? String(mainBranchId) : 'all';

    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '__all');
    const [brandId, setBrandId] = useState(filters.brand_id ?? '__all');
    const [sizeId, setSizeId] = useState(filters.size_id ?? '__all');
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : '__all');
    const [branchId, setBranchId] = useState(filters.branch_id ?? defaultBranchId);
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useDebouncedEffect(
        () => {
            router.get(
                route('report.inventory-stock'),
                {
                    search: search || undefined,
                    category_id: categoryId === '__all' ? undefined : categoryId,
                    brand_id: brandId === '__all' ? undefined : brandId,
                    size_id: sizeId === '__all' ? undefined : sizeId,
                    product_id: productId === '__all' ? undefined : productId,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                    ...(canFilterByBranch ? { branch_id: branchId } : {}),
                },
                { preserveState: true, replace: true },
            );
        },
        [search, categoryId, brandId, sizeId, productId, branchId, dateFrom, dateTo, canFilterByBranch],
        350,
        { skipFirstRun: true },
    );

    function handleReset() {
        setSearch('');
        setCategoryId('__all');
        setBrandId('__all');
        setSizeId('__all');
        setProductId('__all');
        setDateFrom('');
        setDateTo('');
        if (canFilterByBranch) {
            setBranchId(defaultBranchId);
        }
    }

    const categoryOptions = Object.entries(categories || {}).map(([value, label]) => ({ value, label }));
    const brandOptions = Object.entries(brands || {}).map(([value, label]) => ({ value, label }));
    const sizeOptions = Object.entries(sizes || {}).map(([value, label]) => ({ value, label }));
    const branchOptions = Object.entries(branches || {}).map(([value, label]) => ({ value, label }));

    const flatRows = (products.data ?? []).flatMap((product) => {
        if (!product.variations?.length) {
            return [
                {
                    ...product,
                    _rowKey: `p-${product.id}`,
                    _isVariant: false,
                    _stock: parseFloat(product.stock_qty ?? product.batches_sum_available ?? 0) || 0,
                    _costValue: parseFloat(product.cost_value ?? 0),
                    _sellingValue: parseFloat(product.selling_value ?? 0),
                    _expectedProfit: parseFloat(product.expected_profit ?? 0),
                },
            ];
        }

        return product.variations.map((v) => {
            const values = variantValues(v);

            return {
                ...product,
                _rowKey: `v-${v.id}`,
                _isVariant: true,
                _variantLabel: v.variation_data?.label ?? v.sku,
                _stock: values.stock,
                _costValue: values.cost,
                _sellingValue: values.selling,
                _expectedProfit: values.profit,
            };
        });
    });

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => Number(products.from ?? 1) + i,
        },
        {
            id: 'image',
            header: 'Image',
            render: (row) =>
                row.image ? (
                    <img src={`/storage/${row.image}`} alt={row.name} className="h-12 w-12 rounded object-cover" />
                ) : (
                    <div className="flex h-12 w-12 items-center justify-center rounded bg-muted text-xs text-muted-foreground">No img</div>
                ),
        },
        {
            id: 'name',
            header: 'Name / Code',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    {row._isVariant ? (
                        <p className="text-xs font-medium text-blue-700">{row._variantLabel}</p>
                    ) : (
                        <p className="text-xs text-muted-foreground">{row.code}</p>
                    )}
                </div>
            ),
        },
        {
            id: 'category',
            header: 'Category',
            render: (row) => row.category?.name ?? '—',
        },
        {
            id: 'brand',
            header: 'Brand',
            render: (row) => row.brand?.name ?? '—',
        },
        {
            id: 'stock',
            header: 'Stock',
            render: (row) => {
                const stock = row._stock;
                const branchInitialStock = row.submission_stock_summary?.total ?? 0;
                const isReceivedFromBranch = row.source_branch_id != null && row.received_at != null;

                if (isReceivedFromBranch && stock === 0 && branchInitialStock > 0) {
                    return (
                        <div>
                            <span className="font-medium">0</span>
                            <p className="text-xs font-medium text-amber-700 dark:text-amber-400">
                                Branch initial stock: {branchInitialStock}
                            </p>
                        </div>
                    );
                }

                return <span className="font-medium">{formatStock(stock)}</span>;
            },
        },
        {
            id: 'cost_value',
            header: 'Cost Value',
            render: (row) => formatMoney(row._costValue),
        },
        {
            id: 'selling_value',
            header: 'Selling Value',
            render: (row) => formatMoney(row._sellingValue),
        },
        {
            id: 'expected_profit',
            header: 'Expected Profit',
            render: (row) => formatMoney(row._expectedProfit),
        },
    ];

    return (
        <>
            <Head title="Inventory Stock" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Package className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Inventory Stock</h1>
                            <p className="text-xs text-white/60">View product stock by category, brand, size, or search.</p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, code, brand, category..."
                        className="max-w-xs"
                    />
                    {canFilterByBranch && (
                        <Select value={branchId} onValueChange={(v) => setBranchId(v)}>
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Branch" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All branches</SelectItem>
                                {branchOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    <Select value={productId} onValueChange={(v) => setProductId(v)}>
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="All products" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All products</SelectItem>
                            {productOptions.map((opt) => (
                                <SelectItem key={opt.id} value={String(opt.id)}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={categoryId} onValueChange={(v) => setCategoryId(v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All categories</SelectItem>
                            {categoryOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={brandId} onValueChange={(v) => setBrandId(v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All brands" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All brands</SelectItem>
                            {brandOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={sizeId} onValueChange={(v) => setSizeId(v)}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All sizes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All sizes</SelectItem>
                            {sizeOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <ListDateExportBar
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onDateFromChange={setDateFrom}
                        onDateToChange={setDateTo}
                        exportExcelRoute="report.inventory-stock.export-excel"
                        exportPdfRoute="report.inventory-stock.export-pdf"
                        exportPrintRoute="report.inventory-stock.export-print"
                        query={{
                            search,
                            category_id: categoryId,
                            brand_id: brandId,
                            size_id: sizeId,
                            product_id: productId,
                            ...(canFilterByBranch ? { branch_id: branchId } : {}),
                        }}
                    />
                    <Button type="button" variant="outline" size="sm" onClick={handleReset}>
                        <RotateCcw className="size-3.5" />
                        Reset
                    </Button>
                </div>

                <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                    <StatTile
                        label="Total Products"
                        value={summary.product_count ?? 0}
                        sub="Matching current filters"
                        accentClass="border-l-indigo-600"
                    />
                    <StatTile
                        label="Total Stock Qty"
                        value={`${formatStock(summary.total_stock)} pcs`}
                        sub="Across all filtered products"
                        accentClass="border-l-blue-600"
                    />
                    <StatTile
                        label="In Stock"
                        value={summary.in_stock_count ?? 0}
                        sub="Products with stock available"
                        accentClass="border-l-teal-600"
                    />
                    <StatTile
                        label="Out of Stock"
                        value={summary.out_of_stock_count ?? 0}
                        sub="Products with zero stock"
                        accentClass="border-l-slate-600"
                    />
                    <StatTile
                        label="Total Cost Value"
                        value={formatMoney(summary.total_cost_value)}
                        sub="Based on purchase / batch cost"
                        accentClass="border-l-amber-600"
                    />
                    <StatTile
                        label="Total Selling Value"
                        value={formatMoney(summary.total_selling_value)}
                        sub="At current selling prices"
                        accentClass="border-l-emerald-600"
                    />
                    <StatTile
                        label="Expected Gross Profit"
                        value={formatMoney(summary.expected_gross_profit)}
                        sub="Selling value minus cost"
                        accentClass="border-l-rose-600"
                    />
                </div>

                <DataTable columns={columns} rows={flatRows} rowKey="_rowKey" emptyMessage="No products found." />

                <AdminPagination paginator={products} />
            </div>
        </>
    );
}
