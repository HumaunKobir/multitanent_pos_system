import { AdminPagination } from '@/components/admin/pagination';
import { StatTile } from '@/components/dashboard/stat-tile';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, Search, Tag } from 'lucide-react';
import { useState } from 'react';

import {
    ReportFilterField,
    ReportFilterReset,
    ReportInfoBanner,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

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

export default function OpeningStockReport({
    rows,
    summary = {},
    filters = {},
    categories = {},
    brands = {},
    branches = {},
    mainBranchId = null,
    isBranchScoped = false,
}) {
    const { auth, panelType } = usePage().props;
    const canFilterByBranch = !isBranchScoped && panelType === 'admin' && !auth.user?.branch_id;
    const defaultBranchId = mainBranchId != null ? String(mainBranchId) : 'all';

    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ? String(filters.category_id) : '__all');
    const [brandId, setBrandId] = useState(filters.brand_id ? String(filters.brand_id) : '__all');
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : defaultBranchId);

    const categoryOptions = Object.entries(categories || {}).map(([value, label]) => ({ value, label }));
    const brandOptions = Object.entries(brands || {}).map(([value, label]) => ({ value, label }));
    const branchOptions = Object.entries(branches || {}).map(([value, label]) => ({ value, label }));

    useLiveReportFilters(
        'report.opening-stock',
        {
            search,
            category_id: categoryId,
            brand_id: brandId,
            ...(canFilterByBranch ? { branch_id: branchId } : {}),
        },
        [search, categoryId, brandId, branchId, canFilterByBranch],
    );

    const hasActiveFilters = Boolean(
        search ||
            categoryId !== '__all' ||
            brandId !== '__all' ||
            (canFilterByBranch && branchId !== defaultBranchId),
    );

    function resetFilters() {
        setSearch('');
        setCategoryId('__all');
        setBrandId('__all');
        if (canFilterByBranch) {
            setBranchId(defaultBranchId);
        }
        router.get(route('report.opening-stock'), {}, { preserveState: true, replace: true });
    }

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => Number(rows.from ?? 1) + i,
        },
        {
            id: 'product',
            header: 'Product',
            render: (row) => (
                <div>
                    <Link href={route('product.edit', row.product_id)} className="font-medium text-primary hover:underline">
                        {row.product}
                    </Link>
                    {row.variation ? <p className="text-xs text-blue-700">{row.variation}</p> : null}
                    <p className="text-xs text-muted-foreground">{row.code}</p>
                </div>
            ),
        },
        { id: 'category', header: 'Category', render: (row) => row.category ?? '—' },
        { id: 'brand', header: 'Brand', render: (row) => row.brand ?? '—' },
        ...(canFilterByBranch
            ? [{ id: 'branch', header: 'Branch', render: (row) => row.branch ?? '—' }]
            : []),
        { id: 'supplier', header: 'Supplier', render: (row) => row.supplier ?? '—' },
        {
            id: 'quantity',
            header: 'Opening Qty',
            align: 'right',
            render: (row) => <span className="font-medium tabular-nums">{formatStock(row.quantity)}</span>,
        },
        {
            id: 'unit_cost',
            header: 'Unit Cost',
            align: 'right',
            render: (row) => <span className="tabular-nums">{formatMoney(row.unit_cost)}</span>,
        },
        {
            id: 'stock_value',
            header: 'Stock Value',
            align: 'right',
            render: (row) => <span className="font-medium tabular-nums">{formatMoney(row.stock_value)}</span>,
        },
        {
            id: 'paid_amount',
            header: 'Paid',
            align: 'right',
            render: (row) => <span className="tabular-nums">{formatMoney(row.paid_amount)}</span>,
        },
    ];

    return (
        <>
            <Head title="Opening Stock" />
            <ReportPage
                title="Opening Stock"
                description="Products that were opened with initial stock. Add or edit opening stock from Product create/edit."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Search" icon={Search}>
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Product name or code…"
                                className="h-9 border-0 bg-transparent shadow-none focus-visible:ring-0"
                            />
                        </ReportFilterField>
                        <ReportFilterField label="Category" icon={Tag}>
                            <ReportSelect
                                value={categoryId}
                                onChange={setCategoryId}
                                options={[{ value: '__all', label: 'All categories' }, ...categoryOptions]}
                            />
                        </ReportFilterField>
                        <ReportFilterField label="Brand" icon={Tag}>
                            <ReportSelect
                                value={brandId}
                                onChange={setBrandId}
                                options={[{ value: '__all', label: 'All brands' }, ...brandOptions]}
                            />
                        </ReportFilterField>
                        {canFilterByBranch ? (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId || '__all'}
                                    onChange={(value) => setBranchId(value === '__all' ? 'all' : value)}
                                    options={[
                                        { value: 'all', label: 'All branches' },
                                        ...branchOptions,
                                    ]}
                                />
                            </ReportFilterField>
                        ) : null}
                    </>
                }
            >
                <ReportInfoBanner>
                    Opening stock is entered on <strong>Settings → Product</strong> (Initial Stock field). This report lists those records.
                </ReportInfoBanner>

                <div className="mb-4 grid gap-2 sm:grid-cols-3">
                    <StatTile label="Lines" value={summary.line_count ?? 0} />
                    <StatTile label="Total Qty" value={formatStock(summary.total_qty)} />
                    <StatTile label="Total Value" value={formatMoney(summary.total_value)} />
                </div>

                <DataTable columns={columns} rows={rows.data ?? []} rowKey="id" emptyMessage="No opening stock records yet." />
                <AdminPagination links={rows.links} />
            </ReportPage>
        </>
    );
}
