import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head } from '@inertiajs/react';
import { Building2, CalendarRange, Package } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import {
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportInfoBanner,
    ReportPage,
    ReportProductSearch,
    ReportSelect,
} from '@/pages/admin/reports/_shared/report-shell';

function formatQty(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    const amount = parseFloat(value);

    return Number.isInteger(amount) ? String(amount) : amount.toFixed(2);
}

function formatMoney(value) {
    return `৳${Number(value ?? 0).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function QtyCell({ value, className = '' }) {
    return <span className={`tabular-nums ${className}`}>{formatQty(value)}</span>;
}

function MovementQtyCell({ value, positiveClass = 'text-emerald-700 dark:text-emerald-400' }) {
    const amount = parseFloat(value ?? 0);

    if (amount === 0) {
        return '—';
    }

    return <QtyCell value={amount} className={positiveClass} />;
}

export default function DateWiseStockReport({
    branches = [],
    isBranchScoped = false,
    selected_product: initialSelectedProduct = null,
    filters = {},
    mode: initialMode = 'overview',
    opening_stock: initialOpeningStock = 0,
    totals: initialTotals = {},
    entries: initialEntries = [],
}) {
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [rows, setRows] = useState(initialEntries);
    const [mode, setMode] = useState(initialMode);
    const [openingStock, setOpeningStock] = useState(initialOpeningStock);
    const [totals, setTotals] = useState(initialTotals);
    const [selectedProduct, setSelectedProduct] = useState(initialSelectedProduct);
    const [loading, setLoading] = useState(false);
    const requestIdRef = useRef(0);

    const showBranchFilter = !isBranchScoped && branches.length > 0;
    const isLedger = mode === 'ledger' && productId !== 'all' && productId !== '';
    const showProductColumn = !isLedger;

    const buildParams = useCallback(
        (overrides = {}) => {
            const branch = overrides.branchId ?? branchId;
            const product = overrides.productId ?? productId;
            const from = overrides.dateFrom ?? dateFrom;
            const to = overrides.dateTo ?? dateTo;
            const params = new URLSearchParams();

            if (showBranchFilter && branch !== 'all') {
                params.set('branch_id', String(branch));
            }

            if (product !== 'all') {
                params.set('product_id', String(product));
            }

            if (from) {
                params.set('date_from', from);
            }

            if (to) {
                params.set('date_to', to);
            }

            return params;
        },
        [branchId, productId, dateFrom, dateTo, showBranchFilter],
    );

    const loadEntries = useCallback(
        async (overrides = {}) => {
            const requestId = ++requestIdRef.current;
            setLoading(true);
            setRows([]);

            try {
                const params = buildParams(overrides);
                const queryString = params.toString();
                const url = route('report.date-wise-stock') + (queryString ? `?${queryString}` : '');

                window.history.replaceState({}, '', url);

                const response = await fetch(url, {
                    credentials: 'include',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok || requestId !== requestIdRef.current) {
                    return;
                }

                const data = await response.json();
                setRows(Array.isArray(data.entries) ? data.entries : []);
                setMode(data.mode ?? 'overview');
                setOpeningStock(data.opening_stock ?? 0);
                setTotals(data.totals ?? {});
                setSelectedProduct(data.selected_product ?? null);
            } catch {
                if (requestId === requestIdRef.current) {
                    setRows([]);
                }
            } finally {
                if (requestId === requestIdRef.current) {
                    setLoading(false);
                }
            }
        },
        [buildParams],
    );

    useDebouncedEffect(
        () => loadEntries(),
        [dateFrom, dateTo, loadEntries],
        350,
        { skipFirstRun: true },
    );

    const hasActiveFilters = Boolean(
        (showBranchFilter && branchId !== 'all' && branchId !== '') ||
            (productId !== 'all' && productId !== '') ||
            dateFrom ||
            dateTo,
    );

    function handleProductChange(nextProductId) {
        setProductId(nextProductId);
        loadEntries({ productId: nextProductId });
    }

    function handleBranchChange(nextBranchId) {
        setBranchId(nextBranchId);
        setProductId('all');
        loadEntries({ branchId: nextBranchId, productId: 'all' });
    }

    function resetFilters() {
        setBranchId('all');
        setProductId('all');
        setDateFrom('');
        setDateTo('');
        setSelectedProduct(null);
        setMode('overview');
        setOpeningStock(0);
        setTotals({});
        window.history.replaceState({}, '', route('report.date-wise-stock'));
        loadEntries({ branchId: 'all', productId: 'all', dateFrom: '', dateTo: '' });
    }

    const displayRows = useMemo(() => {
        if (!isLedger || rows.length === 0) {
            return rows;
        }

        return [
            ...rows,
            {
                _key: 'opening-balance',
                date: dateFrom || rows[rows.length - 1]?.date,
                time: '—',
                transaction_type: 'Opening Balance',
                opening_qty: null,
                in_qty: 0,
                out_qty: 0,
                balance_qty: openingStock,
                unit_cost: rows[rows.length - 1]?.unit_cost ?? 0,
                value: openingStock * (rows[rows.length - 1]?.unit_cost ?? 0),
                debit_account: '—',
                credit_account: '—',
                isOpening: true,
            },
        ];
    }, [isLedger, rows, openingStock, dateFrom]);

    const columns = useMemo(() => {
        const cols = [
            { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
            { id: 'time', header: 'Time', render: (row) => <span className="tabular-nums">{row.time}</span> },
            { id: 'transaction_type', header: 'Transaction Type', render: (row) => row.transaction_type },
        ];

        if (showProductColumn) {
            cols.push({ id: 'product', header: 'Product', render: (row) => row.product });
        }

        cols.push(
            {
                id: 'opening_qty',
                header: 'Opening Qty',
                render: (row) =>
                    row.isOpening ? (
                        '—'
                    ) : (
                        <QtyCell value={row.opening_qty} className="text-muted-foreground" />
                    ),
            },
            {
                id: 'in_qty',
                header: 'In Qty',
                render: (row) => (row.isOpening ? '—' : <MovementQtyCell value={row.in_qty} />),
            },
            {
                id: 'out_qty',
                header: 'Out Qty',
                render: (row) =>
                    row.isOpening ? (
                        '—'
                    ) : (
                        <MovementQtyCell value={row.out_qty} positiveClass="text-red-700 dark:text-red-400" />
                    ),
            },
            {
                id: 'balance_qty',
                header: 'Balance Qty',
                render: (row) => <QtyCell value={row.balance_qty} className="font-medium" />,
            },
            {
                id: 'unit_cost',
                header: 'Unit Cost',
                render: (row) => <span className="tabular-nums">{formatMoney(row.unit_cost)}</span>,
            },
            {
                id: 'value',
                header: 'Value',
                render: (row) => <span className="font-medium tabular-nums">{formatMoney(row.value)}</span>,
            },
            { id: 'debit_account', header: 'Debit Account', render: (row) => row.debit_account },
            { id: 'credit_account', header: 'Credit Account', render: (row) => row.credit_account },
        );

        return cols;
    }, [showProductColumn]);

    return (
        <>
            <Head title="Date Wise Stock" />
            <ReportPage
                title="Date Wise Stock"
                description="Stock movement ledger with opening balance, unit cost, value, and GL accounts."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        {showBranchFilter && (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId}
                                    onChange={handleBranchChange}
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
                                onChange={handleProductChange}
                                selectedProduct={selectedProduct}
                                branchId={branchId}
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
                {isLedger && selectedProduct && (
                    <ReportInfoBanner>
                        <strong>{selectedProduct.label}</strong>
                        {dateFrom ? (
                            <span className="ml-3 text-muted-foreground">
                                Opening stock on {formatBdDate(dateFrom)}: <strong>{formatQty(openingStock)}</strong>
                            </span>
                        ) : null}
                        {totals.closing != null ? (
                            <span className="ml-3 text-muted-foreground">
                                Closing balance: <strong>{formatQty(totals.closing)}</strong>
                                {totals.value != null ? (
                                    <>
                                        {' '}
                                        · Value: <strong>{formatMoney(totals.value)}</strong>
                                    </>
                                ) : null}
                            </span>
                        ) : null}
                    </ReportInfoBanner>
                )}

                <DataTable
                    columns={columns}
                    rows={displayRows}
                    rowKey={(row, i) => row._key ?? `${row.date}-${row.time}-${row.transaction_type}-${row.product}-${i}`}
                    emptyMessage={
                        loading
                            ? 'Loading stock movements…'
                            : isLedger
                              ? 'No stock movements for this product in the selected period.'
                              : 'Select a product to view the full ledger, or adjust filters to browse movements.'
                    }
                />
            </ReportPage>
        </>
    );
}
