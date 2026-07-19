import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarRange, Package } from 'lucide-react';
import { useMemo, useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import {
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportInfoBanner,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

function QtyCell({ value, className = '' }) {
    return <span className={className}>{parseFloat(value ?? 0).toFixed(2)}</span>;
}

export default function StockLedgerReport({
    products = [],
    branches = [],
    isBranchScoped = false,
    filters = {},
    mode = 'overview',
    product = null,
    opening_stock = 0,
    entries = [],
    totals = {},
}) {
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useLiveReportFilters(
        'report.stock-ledger',
        {
            branch_id: isBranchScoped || branchId === 'all' ? '' : branchId,
            product_id: productId === 'all' ? '' : productId,
            date_from: dateFrom,
            date_to: dateTo,
        },
        [branchId, productId, dateFrom, dateTo, isBranchScoped],
    );

    const isOverview = mode === 'overview';
    const showBranchColumn = isOverview || (!isBranchScoped && branchId === 'all');
    const hasActiveFilters = Boolean(
        (branchId !== 'all' && branchId !== '') ||
            (productId !== 'all' && productId !== '') ||
            dateFrom ||
            dateTo,
    );

    function resetFilters() {
        setBranchId('all');
        setProductId('all');
        setDateFrom('');
        setDateTo('');
        router.get(route('report.stock-ledger'), {}, { preserveState: true, replace: true });
    }

    const displayRows = useMemo(() => {
        if (isOverview || !product || !dateFrom) {
            return entries;
        }

        return [
            {
                _key: 'opening',
                date: dateFrom,
                type: 'Opening stock',
                reference: '—',
                in: 0,
                out: 0,
                balance: opening_stock,
                isOpening: true,
            },
            ...entries,
        ];
    }, [isOverview, product, dateFrom, opening_stock, entries]);

    const columns = useMemo(() => {
        const cols = [{ id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) }];

        if (showBranchColumn) {
            cols.push({ id: 'branch', header: 'Branch', render: (row) => row.branch ?? '—' });
        }

        if (isOverview) {
            cols.push({ id: 'product', header: 'Product', render: (row) => row.product ?? '—' });
        }

        cols.push(
            { id: 'type', header: 'Type', render: (row) => row.type },
            {
                id: 'ref',
                header: 'Reference',
                render: (row) => <span className="font-mono text-xs">{row.reference}</span>,
            },
            {
                id: 'in',
                header: 'In',
                render: (row) =>
                    row.isOpening || row.in <= 0 ? '—' : <QtyCell value={row.in} className="text-emerald-700 dark:text-emerald-400" />,
            },
            {
                id: 'out',
                header: 'Out',
                render: (row) =>
                    row.isOpening || row.out <= 0 ? '—' : <QtyCell value={row.out} className="text-red-700 dark:text-red-400" />,
            },
        );

        if (!isOverview) {
            cols.push({
                id: 'balance',
                header: 'Balance',
                render: (row) => <QtyCell value={row.balance} className="font-medium" />,
            });
        }

        return cols;
    }, [isOverview, showBranchColumn]);

    return (
        <>
            <Head title="Stock Ledger" />
            <ReportPage
                title="Stock Ledger"
                description="Product-wise stock in/out ledger with running balance."
                filterGridClassName={
                    !isBranchScoped && branches.length > 0 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-2 lg:grid-cols-3'
                }
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        {!isBranchScoped && branches.length > 0 && (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId}
                                    onChange={setBranchId}
                                    options={[
                                        { value: 'all', label: 'All branches' },
                                        ...branches.map((b) => ({ value: String(b.id), label: b.label })),
                                    ]}
                                />
                            </ReportFilterField>
                        )}
                        <ReportFilterField label="Product" icon={Package}>
                            <ReportSelect
                                value={productId}
                                onChange={setProductId}
                                options={[
                                    { value: 'all', label: 'All products' },
                                    ...products.map((p) => ({ value: String(p.id), label: p.label })),
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
                {product && (
                    <ReportInfoBanner>
                        <strong>{product.name}</strong>
                        {product.code ? <span className="text-muted-foreground"> ({product.code})</span> : null}
                        <span className="ml-3">
                            Current stock: <QtyCell value={product.current_stock} className="font-semibold text-blue-950 dark:text-blue-100" />
                        </span>
                    </ReportInfoBanner>
                )}

                <DataTable
                    columns={columns}
                    rows={displayRows}
                    rowKey={(row, index) => row._key ?? `${row.date}-${row.branch ?? ''}-${row.product ?? ''}-${row.type}-${index}`}
                    emptyMessage="No stock movements for this period."
                />

                {entries.length > 0 && (
                    <div className="mt-4 space-y-2">
                        <div className="flex flex-wrap justify-end gap-4 rounded-lg border border-blue-950/10 bg-gradient-to-r from-slate-50 to-blue-50/30 px-4 py-3 text-sm dark:from-slate-900/50 dark:to-blue-950/20 sm:gap-6">
                            <span title="Total quantity received in the selected date range (purchase, initial stock, sale return, distribution in)">
                                Period In: <QtyCell value={totals.in} className="font-semibold text-emerald-700 dark:text-emerald-400" />
                            </span>
                            <span title="Total quantity issued in the selected date range (sale, damage, purchase return, exchange, distribution out)">
                                Period Out: <QtyCell value={totals.out} className="font-semibold text-red-700 dark:text-red-400" />
                            </span>
                            {!isOverview && (
                                <span title="Opening stock + Period In − Period Out">
                                    Closing: <QtyCell value={totals.balance} className="font-semibold text-blue-950 dark:text-blue-200" />
                                </span>
                            )}
                        </div>
                        <p className="text-right text-xs text-muted-foreground">
                            Period In / Out are totals for the selected date range only
                            {!isOverview ? ' (Opening stock is shown separately and is not included)' : ''}.
                        </p>
                    </div>
                )}
            </ReportPage>
        </>
    );
}
