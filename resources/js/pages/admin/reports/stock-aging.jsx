import { StatTile } from '@/components/dashboard/stat-tile';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { route } from '@/lib/route';
import { Head, router, usePage } from '@inertiajs/react';
import { Building2, Package, Ruler, Search, Tag } from 'lucide-react';
import { useState } from 'react';

import {
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

function formatStock(value) {
    const amount = parseFloat(value ?? 0);

    return Number.isInteger(amount) ? String(amount) : amount.toFixed(2);
}

export default function StockAgingReport({
    rows = [],
    summary = {},
    filters = {},
    categories = {},
    brands = {},
    sizes = {},
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
    const [sizeId, setSizeId] = useState(filters.size_id ? String(filters.size_id) : '__all');
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : defaultBranchId);

    const categoryOptions = Object.entries(categories || {}).map(([value, label]) => ({ value, label }));
    const brandOptions = Object.entries(brands || {}).map(([value, label]) => ({ value, label }));
    const sizeOptions = Object.entries(sizes || {}).map(([value, label]) => ({ value, label }));
    const branchOptions = Object.entries(branches || {}).map(([value, label]) => ({ value, label }));

    useLiveReportFilters(
        'report.stock-aging',
        {
            search,
            category_id: categoryId,
            brand_id: brandId,
            size_id: sizeId,
            ...(canFilterByBranch ? { branch_id: branchId } : {}),
        },
        [search, categoryId, brandId, sizeId, branchId, canFilterByBranch],
    );

    const hasActiveFilters = Boolean(
        search ||
            categoryId !== '__all' ||
            brandId !== '__all' ||
            sizeId !== '__all' ||
            (canFilterByBranch && branchId !== defaultBranchId),
    );

    function resetFilters() {
        setSearch('');
        setCategoryId('__all');
        setBrandId('__all');
        setSizeId('__all');
        if (canFilterByBranch) {
            setBranchId(defaultBranchId);
        }
        router.get(route('report.stock-aging'), {}, { preserveState: true, replace: true });
    }

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => i + 1,
        },
        {
            id: 'name',
            header: 'Product',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">{row.code ?? '—'}</p>
                </div>
            ),
        },
        { id: 'category', header: 'Category', render: (row) => row.category ?? '—' },
        { id: 'brand', header: 'Brand', render: (row) => row.brand ?? '—' },
        { id: 'qty_0_30', header: '0–30 days', render: (row) => formatStock(row.qty_0_30) },
        { id: 'qty_31_60', header: '31–60 days', render: (row) => formatStock(row.qty_31_60) },
        { id: 'qty_61_90', header: '61–90 days', render: (row) => formatStock(row.qty_61_90) },
        { id: 'qty_90_plus', header: '90+ days', render: (row) => formatStock(row.qty_90_plus) },
        {
            id: 'total_qty',
            header: 'Total',
            render: (row) => <span className="font-medium">{formatStock(row.total_qty)}</span>,
        },
    ];

    return (
        <>
            <Head title="Stock Aging" />
            <ReportPage
                title="Stock Aging"
                description="Current stock aged by days since batch receipt (or variation update for variants)."
                filterGridClassName={canFilterByBranch ? 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5' : 'sm:grid-cols-2 lg:grid-cols-4'}
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Search" icon={Search} className="sm:col-span-2 lg:col-span-1">
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Product name or code…"
                                className="h-9 border-0 bg-transparent shadow-none focus-visible:ring-0"
                            />
                        </ReportFilterField>
                        {canFilterByBranch && (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId}
                                    onChange={setBranchId}
                                    options={[
                                        { value: 'all', label: 'All branches' },
                                        ...branchOptions,
                                    ]}
                                />
                            </ReportFilterField>
                        )}
                        <ReportFilterField label="Category" icon={Package}>
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
                        <ReportFilterField label="Size" icon={Ruler}>
                            <ReportSelect
                                value={sizeId}
                                onChange={setSizeId}
                                options={[{ value: '__all', label: 'All sizes' }, ...sizeOptions]}
                            />
                        </ReportFilterField>
                    </>
                }
            >
                <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <StatTile
                        label="0–30 days"
                        value={`${formatStock(summary['0-30'])} pcs`}
                        accentClass="border-l-emerald-600"
                    />
                    <StatTile
                        label="31–60 days"
                        value={`${formatStock(summary['31-60'])} pcs`}
                        accentClass="border-l-amber-600"
                    />
                    <StatTile
                        label="61–90 days"
                        value={`${formatStock(summary['61-90'])} pcs`}
                        accentClass="border-l-orange-600"
                    />
                    <StatTile
                        label="90+ days"
                        value={`${formatStock(summary['90+'])} pcs`}
                        accentClass="border-l-rose-600"
                    />
                    <StatTile
                        label="Total Stock"
                        value={`${formatStock(summary.total)} pcs`}
                        accentClass="border-l-blue-600"
                    />
                </div>

                <DataTable columns={columns} rows={rows} rowKey="id" emptyMessage="No aged stock found." />
            </ReportPage>
        </>
    );
}
