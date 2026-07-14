import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { ArrowDownWideNarrow, Building2, CalendarRange, Package, Percent, Phone, User } from 'lucide-react';
import { useEffect, useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportInfoBanner,
    ReportPage,
    ReportProductSearch,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

function attributeLine(row) {
    if (row.variant) {
        return row.variant;
    }

    const parts = [];

    if (row.colors?.length) {
        parts.push(row.colors.join(', '));
    }

    if (row.sizes?.length) {
        parts.push(row.sizes.join(', '));
    }

    return parts.length > 0 ? parts.join(' · ') : null;
}

function DiscountSummaryCard({ row }) {
    return (
        <div className="min-w-0 rounded-lg border border-blue-950/10 bg-card p-3 shadow-sm ring-1 ring-blue-950/5">
            <div className="flex min-w-0 items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-blue-950 dark:text-blue-100">{row.label}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {row.type}
                        {row.period ? ` · ${row.period}` : ''}
                    </p>
                </div>
                <MoneyCell value={row.total_amount} className="shrink-0 text-sm font-semibold tabular-nums" />
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                <div className="rounded-md bg-muted/40 px-2.5 py-1.5">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Total Qty</p>
                    <p className="font-medium tabular-nums">{row.total_quantity}</p>
                </div>
                <div className="rounded-md bg-muted/40 px-2.5 py-1.5">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Sale items</p>
                    <p className="font-medium tabular-nums">{row.line_count}</p>
                </div>
                <div className="col-span-2 rounded-md bg-muted/40 px-2.5 py-1.5 sm:col-span-1">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Discount</p>
                    <p className="font-medium tabular-nums">
                        <MoneyCell value={row.total_discount} />
                    </p>
                </div>
            </div>
        </div>
    );
}

function SaleRowCard({ row }) {
    const attrs = attributeLine(row);

    return (
        <article className="min-w-0 rounded-lg border border-blue-950/10 bg-card p-3 shadow-sm ring-1 ring-blue-950/5">
            <div className="flex min-w-0 items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate font-mono text-sm font-semibold text-blue-950 dark:text-blue-100">{row.invoice}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">{formatBdDate(row.date)}</p>
                </div>
                <MoneyCell value={row.line_total} className="shrink-0 text-sm font-semibold tabular-nums" />
            </div>

            <div className="mt-3 min-w-0">
                <p className="truncate text-sm font-medium">{row.product}</p>
                {attrs ? <p className="mt-0.5 truncate text-xs text-muted-foreground">{attrs}</p> : null}
            </div>

            <div className="mt-3 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                <span className="inline-flex min-w-0 items-center gap-1">
                    <User className="size-3.5 shrink-0" />
                    <span className="truncate">{row.customer_name}</span>
                </span>
                <span className="inline-flex min-w-0 items-center gap-1">
                    <Phone className="size-3.5 shrink-0" />
                    <span className="truncate">{row.customer_phone}</span>
                </span>
            </div>

            <div className="mt-3 grid grid-cols-3 gap-2 text-xs">
                <div className="rounded-md bg-muted/40 px-2.5 py-1.5">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Qty</p>
                    <p className="font-medium tabular-nums">{row.quantity}</p>
                </div>
                <div className="rounded-md bg-muted/40 px-2.5 py-1.5">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Free</p>
                    <p className="font-medium tabular-nums">{row.free_quantity}</p>
                </div>
                <div className="rounded-md bg-muted/40 px-2.5 py-1.5">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Total</p>
                    <p className="font-medium tabular-nums">{row.total_quantity}</p>
                </div>
            </div>

            <div className="mt-3 flex min-w-0 items-end justify-between gap-3 border-t border-blue-950/10 pt-3 text-xs">
                <div className="min-w-0">
                    <p className="truncate font-medium">{row.discount_label}</p>
                    {row.discount_period ? (
                        <p className="truncate text-muted-foreground">{row.discount_period}</p>
                    ) : null}
                </div>
                <div className="shrink-0 text-right tabular-nums text-muted-foreground">
                    Disc. <MoneyCell value={row.discount_amount} />
                </div>
            </div>
        </article>
    );
}

function EmptyState({ message }) {
    return (
        <div className="rounded-lg border border-blue-950/10 bg-card px-4 py-10 text-center text-sm text-muted-foreground shadow-sm ring-1 ring-blue-950/5">
            {message}
        </div>
    );
}

export default function SalesSummaryReport({
    branches = [],
    discounts = [],
    isBranchScoped = false,
    selected_product = null,
    filters = {},
    rows = [],
    discount_summary = [],
    top_discount = null,
}) {
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [sort, setSort] = useState(filters.sort ?? 'desc');
    const [discount, setDiscount] = useState(filters.discount ?? 'all');

    useEffect(() => {
        setBranchId(filters.branch_id ? String(filters.branch_id) : 'all');
        setProductId(filters.product_id ? String(filters.product_id) : 'all');
        setDateFrom(filters.date_from ?? '');
        setDateTo(filters.date_to ?? '');
        setSort(filters.sort ?? 'desc');
        setDiscount(filters.discount ?? 'all');
    }, [filters.branch_id, filters.product_id, filters.date_from, filters.date_to, filters.sort, filters.discount]);

    const showBranchFilter = !isBranchScoped && branches.length > 0;
    const sortLabel = sort === 'asc' ? 'lowest to highest' : 'highest to lowest';

    useLiveReportFilters(
        'report.sales-summary',
        {
            branch_id: branchId === 'all' ? '' : branchId,
            product_id: productId === 'all' ? '' : productId,
            date_from: dateFrom,
            date_to: dateTo,
            sort,
            discount: discount === 'all' ? '' : discount,
        },
        [branchId, productId, dateFrom, dateTo, sort, discount],
    );

    const hasActiveFilters = Boolean(
        (showBranchFilter && branchId !== 'all' && branchId !== '') ||
            (productId !== 'all' && productId !== '') ||
            dateFrom ||
            dateTo ||
            sort !== 'desc' ||
            discount !== 'all',
    );

    function resetFilters() {
        router.get(route('report.sales-summary'), {}, { preserveState: true, replace: true });
    }

    const discountColumns = [
        { id: 'label', header: 'Discount', render: (row) => row.label },
        { id: 'type', header: 'Type', render: (row) => row.type },
        { id: 'period', header: 'Period', render: (row) => row.period ?? '—' },
        {
            id: 'total_quantity',
            header: 'Total Qty',
            render: (row) => <span className="font-medium">{row.total_quantity}</span>,
        },
        { id: 'line_count', header: 'Sale items', render: (row) => row.line_count },
        {
            id: 'total_discount',
            header: 'Discount',
            render: (row) => <MoneyCell value={row.total_discount} />,
        },
        {
            id: 'total_amount',
            header: 'Amount',
            render: (row) => <MoneyCell value={row.total_amount} />,
        },
    ];

    const saleColumns = [
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        { id: 'invoice', header: 'Invoice', render: (row) => row.invoice },
        {
            id: 'customer',
            header: 'Customer',
            render: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    <User className="size-3.5 text-muted-foreground" />
                    {row.customer_name}
                </span>
            ),
        },
        {
            id: 'phone',
            header: 'Phone',
            render: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    <Phone className="size-3.5 text-muted-foreground" />
                    {row.customer_phone}
                </span>
            ),
        },
        { id: 'product', header: 'Product', render: (row) => row.product },
        {
            id: 'variant',
            header: 'Variant',
            render: (row) =>
                row.variant ? row.variant : <span className="text-muted-foreground">—</span>,
        },
        {
            id: 'colors',
            header: 'Color',
            render: (row) =>
                row.colors && row.colors.length > 0 ? (
                    row.colors.join(', ')
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            id: 'sizes',
            header: 'Size',
            render: (row) =>
                row.sizes && row.sizes.length > 0 ? (
                    row.sizes.join(', ')
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            id: 'discount',
            header: 'Discount',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.discount_label}</p>
                    {row.discount_period ? (
                        <p className="text-xs text-muted-foreground">{row.discount_period}</p>
                    ) : null}
                </div>
            ),
        },
        { id: 'quantity', header: 'Qty', render: (row) => row.quantity },
        { id: 'free_quantity', header: 'Free', render: (row) => row.free_quantity },
        {
            id: 'total_quantity',
            header: 'Total Qty',
            render: (row) => <span className="font-medium">{row.total_quantity}</span>,
        },
        {
            id: 'discount_amount',
            header: 'Disc. Amt',
            render: (row) => <MoneyCell value={row.discount_amount} />,
        },
        { id: 'line_total', header: 'Amount', render: (row) => <MoneyCell value={row.line_total} /> },
    ];

    return (
        <>
            <Head title="Sales Summary" />
            <ReportPage
                title="Sales Summary"
                description="Sale lines with customer and discount details."
                filterGridClassName="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        {showBranchFilter && (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId}
                                    onChange={(value) => {
                                        setBranchId(value);
                                        setProductId('all');
                                    }}
                                    options={[
                                        { value: 'all', label: 'All branches' },
                                        ...branches.map((b) => ({ value: String(b.id), label: b.label })),
                                    ]}
                                />
                            </ReportFilterField>
                        )}
                        <ReportFilterField label="Product" icon={Package} className="sm:col-span-2 lg:col-span-1 xl:col-span-2">
                            <ReportProductSearch
                                value={productId}
                                onChange={setProductId}
                                selectedProduct={selected_product}
                                branchId={branchId}
                            />
                        </ReportFilterField>
                        <ReportFilterField label="Discount" icon={Percent}>
                            <ReportSelect
                                value={discount}
                                onChange={setDiscount}
                                options={discounts.map((d) => ({ value: d.value, label: d.label }))}
                            />
                        </ReportFilterField>
                        <ReportFilterField label="Sort by qty" icon={ArrowDownWideNarrow}>
                            <ReportSelect
                                value={sort}
                                onChange={setSort}
                                options={[
                                    { value: 'desc', label: 'Highest to lowest' },
                                    { value: 'asc', label: 'Lowest to highest' },
                                ]}
                            />
                        </ReportFilterField>
                        <ReportFilterField label="From date" icon={CalendarRange}>
                            <ReportDateInput value={dateFrom} onChange={setDateFrom} />
                        </ReportFilterField>
                        <ReportFilterField label="To date" icon={CalendarRange}>
                            <ReportDateInput value={dateTo} onChange={setDateTo} />
                        </ReportFilterField>
                    </>
                }
            >
                <ReportInfoBanner>
                    <p className="leading-relaxed">
                        Sorted by quantity — <strong>{sortLabel}</strong>.
                        {top_discount ? (
                            <>
                                {' '}
                                Top selling discount: <strong>{top_discount.label}</strong>
                                {top_discount.period ? (
                                    <span className="text-muted-foreground"> ({top_discount.period})</span>
                                ) : null}
                                {' — '}
                                <strong>{top_discount.total_quantity}</strong> units sold.
                            </>
                        ) : null}
                    </p>
                </ReportInfoBanner>

                {discount_summary.length > 0 && (
                    <div className="mb-4 min-w-0 max-w-full">
                        <h2 className="mb-1 text-sm font-semibold uppercase tracking-wide text-blue-950 dark:text-blue-100">
                            Discount-wise summary
                        </h2>
                        <p className="mb-3 text-xs text-muted-foreground">
                            Each discount groups product rows from sales. &quot;Sale items&quot; is how many product lines used
                            that discount (one invoice with 3 products = 3 sale items).
                        </p>
                        <div className="space-y-3 @[56rem]/report:hidden">
                            {discount_summary.map((row) => (
                                <DiscountSummaryCard key={row.key} row={row} />
                            ))}
                        </div>
                        <div className="hidden min-w-0 max-w-full @[56rem]/report:block">
                            <DataTable
                                columns={discountColumns}
                                rows={discount_summary}
                                rowKey="key"
                                emptyMessage="No discount data for this period."
                                tableClassName="min-w-[720px]"
                            />
                        </div>
                    </div>
                )}

                {rows.length === 0 ? (
                    <EmptyState message="No sales found for this period." />
                ) : (
                    <div className="min-w-0 max-w-full">
                        <div className="space-y-3 @[68rem]/report:hidden">
                            {rows.map((row) => (
                                <SaleRowCard key={row.id} row={row} />
                            ))}
                        </div>
                        <div className="hidden min-w-0 max-w-full @[68rem]/report:block">
                            <DataTable
                                columns={saleColumns}
                                rows={rows}
                                rowKey="id"
                                emptyMessage="No sales found for this period."
                                tableClassName="min-w-[1100px]"
                            />
                        </div>
                    </div>
                )}
            </ReportPage>
        </>
    );
}
