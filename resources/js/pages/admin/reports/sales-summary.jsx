import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarRange, Hash, Package, Phone, User } from 'lucide-react';
import { useState } from 'react';

import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/ui/data-table';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportInfoBanner,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

export default function SalesSummaryReport({
    products = [],
    branches = [],
    isBranchScoped = false,
    filters = {},
    rows = [],
}) {
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [minQuantity, setMinQuantity] = useState(String(filters.min_quantity ?? 5));

    const showBranchFilter = !isBranchScoped && branches.length > 0;

    useLiveReportFilters(
        'report.sales-summary',
        {
            branch_id: branchId === 'all' ? '' : branchId,
            product_id: productId === 'all' ? '' : productId,
            date_from: dateFrom,
            date_to: dateTo,
            min_quantity: minQuantity,
        },
        [branchId, productId, dateFrom, dateTo, minQuantity],
    );

    const hasActiveFilters = Boolean(
        (showBranchFilter && branchId !== 'all' && branchId !== '') ||
            (productId !== 'all' && productId !== '') ||
            dateFrom ||
            dateTo ||
            minQuantity !== '5',
    );

    function resetFilters() {
        setBranchId('all');
        setProductId('all');
        setDateFrom('');
        setDateTo('');
        setMinQuantity('5');
        router.get(route('report.sales-summary'), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Sales Summary" />
            <ReportPage
                title="Sales Summary"
                description="Products sold in large quantities with customer details."
                filterGridClassName={
                    showBranchFilter ? 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5' : 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                }
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        {showBranchFilter && (
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
                        <ReportFilterField label="Min quantity" icon={Hash}>
                            <Input
                                type="number"
                                min={1}
                                value={minQuantity}
                                onChange={(e) => setMinQuantity(e.target.value)}
                                className="h-9 border-0 bg-transparent shadow-none focus-visible:ring-0"
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
                    Showing sale lines with total quantity (paid + free) of <strong>{minQuantity || 5}</strong> or more.
                </ReportInfoBanner>

                <DataTable
                    columns={[
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
                        { id: 'product_code', header: 'Code', render: (row) => row.product_code },
                        { id: 'quantity', header: 'Qty', render: (row) => row.quantity },
                        { id: 'free_quantity', header: 'Free', render: (row) => row.free_quantity },
                        {
                            id: 'total_quantity',
                            header: 'Total Qty',
                            render: (row) => <span className="font-medium">{row.total_quantity}</span>,
                        },
                        { id: 'line_total', header: 'Amount', render: (row) => <MoneyCell value={row.line_total} /> },
                    ]}
                    rows={rows}
                    rowKey="id"
                    emptyMessage="No large-quantity sales found for this period."
                />
            </ReportPage>
        </>
    );
}
