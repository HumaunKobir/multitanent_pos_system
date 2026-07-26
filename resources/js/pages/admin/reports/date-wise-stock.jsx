import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head } from '@inertiajs/react';
import { Building2, CalendarRange, Package } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import {
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    ReportProductSearch,
    ReportSelect,
} from '@/pages/admin/reports/_shared/report-shell';

export default function DateWiseStockReport({
    branches = [],
    isBranchScoped = false,
    selected_product: initialSelectedProduct = null,
    filters = {},
    entries: initialEntries = [],
}) {
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [rows, setRows] = useState(initialEntries);
    const [selectedProduct, setSelectedProduct] = useState(initialSelectedProduct);
    const [loading, setLoading] = useState(false);
    const requestIdRef = useRef(0);

    const showBranchFilter = !isBranchScoped && branches.length > 0;

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
        window.history.replaceState({}, '', route('report.date-wise-stock'));
        loadEntries({ branchId: 'all', productId: 'all', dateFrom: '', dateTo: '' });
    }

    return (
        <>
            <Head title="Date Wise Stock" />
            <ReportPage
                title="Date Wise Stock"
                description="Product stock in/out movement log."
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
                <DataTable
                    columns={[
                        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
                        { id: 'time', header: 'Time', render: (row) => row.time },
                        { id: 'product', header: 'Product', render: (row) => row.product },
                        { id: 'sku', header: 'SKU', render: (row) => row.sku },
                        { id: 'type', header: 'Type', render: (row) => row.type },
                        { id: 'qty', header: 'Qty', render: (row) => row.quantity },
                        { id: 'stock', header: 'Stock After', render: (row) => row.stock },
                        { id: 'remark', header: 'Remark', render: (row) => row.remark },
                    ]}
                    rows={rows}
                    rowKey={(row, i) => `${row.date}-${row.time}-${row.product}-${row.sku}-${i}`}
                    emptyMessage={loading ? 'Loading stock movements…' : 'No stock movements for this period.'}
                />
            </ReportPage>
        </>
    );
}
