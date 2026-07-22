import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarRange, Truck } from 'lucide-react';
import { useState } from 'react';

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

function TotalsBar({ totals }) {
    if (!totals || totals.invoice_count === 0) {
        return null;
    }

    const items = [
        { label: 'Invoices', value: totals.invoice_count, money: false },
        { label: 'Gross', value: totals.gross_amount, money: true },
        { label: 'Discount', value: totals.discount, money: true },
        { label: 'VAT', value: totals.vat, money: true },
        { label: 'Net amount', value: totals.net_amount, money: true },
        { label: 'Paid', value: totals.paid_amount, money: true },
        { label: 'Due', value: totals.due_amount, money: true },
    ];

    return (
        <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="rounded-lg border border-blue-950/10 bg-card px-3 py-2 shadow-sm ring-1 ring-blue-950/5"
                >
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Total {item.label}
                    </p>
                    <p className="mt-0.5 text-sm font-semibold text-blue-950 dark:text-blue-100">
                        {item.money ? (
                            <MoneyCell value={item.value} className="tabular-nums" />
                        ) : (
                            <span className="tabular-nums">{item.value}</span>
                        )}
                    </p>
                </div>
            ))}
        </div>
    );
}

export default function PurchaseReport({
    suppliers = [],
    branches = [],
    isBranchScoped = false,
    filters = {},
    rows = [],
    supplier_summaries = [],
    totals = {},
    supplier = null,
}) {
    const [supplierId, setSupplierId] = useState(filters.supplier_id ? String(filters.supplier_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : 'all');

    const filterQuery = {
        supplier_id: supplierId === 'all' ? '' : supplierId,
        date_from: dateFrom,
        date_to: dateTo,
        ...(isBranchScoped ? {} : { branch_id: branchId === 'all' ? '' : branchId }),
    };

    useLiveReportFilters('report.purchase-report', filterQuery, [supplierId, dateFrom, dateTo, branchId, isBranchScoped]);

    const hasActiveFilters = Boolean(
        (supplierId && supplierId !== 'all') ||
            dateFrom ||
            dateTo ||
            (!isBranchScoped && branchId && branchId !== 'all'),
    );

    function resetFilters() {
        setSupplierId('all');
        setDateFrom('');
        setDateTo('');
        setBranchId('all');
        router.get(route('report.purchase-report'), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Purchase Report" />
            <ReportPage
                title="Purchase Report"
                description="Purchases by supplier and date, with totals and supplier-wise summary."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Supplier" icon={Truck} className="sm:col-span-2">
                            <ReportSelect
                                value={supplierId}
                                onChange={setSupplierId}
                                placeholder="All suppliers"
                                options={[
                                    { value: 'all', label: 'All suppliers' },
                                    ...suppliers.map((s) => ({ value: String(s.id), label: s.label })),
                                ]}
                            />
                        </ReportFilterField>
                        {!isBranchScoped ? (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId}
                                    onChange={setBranchId}
                                    placeholder="All branches"
                                    options={[
                                        { value: 'all', label: 'All branches' },
                                        ...branches.map((b) => ({ value: String(b.id), label: b.label })),
                                    ]}
                                />
                            </ReportFilterField>
                        ) : null}
                        <ReportFilterField label="From date" icon={CalendarRange}>
                            <ReportDateInput value={dateFrom} onChange={setDateFrom} />
                        </ReportFilterField>
                        <ReportFilterField label="To date" icon={CalendarRange}>
                            <ReportDateInput value={dateTo} onChange={setDateTo} />
                        </ReportFilterField>
                    </>
                }
            >
                {supplier ? (
                    <ReportInfoBanner>
                        <strong>{supplier.name}</strong>
                        {supplier.company_name ? ` · ${supplier.company_name}` : ''}
                        {supplier.phone ? ` · ${supplier.phone}` : ''}
                    </ReportInfoBanner>
                ) : (
                    <ReportInfoBanner>Showing purchases for all suppliers in the selected period.</ReportInfoBanner>
                )}

                <DataTable
                    columns={[
                        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
                        {
                            id: 'invoice',
                            header: 'Invoice',
                            render: (row) => <span className="font-mono text-xs">{row.invoice}</span>,
                        },
                        { id: 'supplier', header: 'Supplier', render: (row) => row.supplier_name },
                        { id: 'branch', header: 'Branch', render: (row) => row.branch_name },
                        {
                            id: 'gross',
                            header: 'Gross',
                            render: (row) => <MoneyCell value={row.gross_amount} />,
                        },
                        {
                            id: 'discount',
                            header: 'Discount',
                            render: (row) => <MoneyCell value={row.discount} />,
                        },
                        { id: 'vat', header: 'VAT', render: (row) => <MoneyCell value={row.vat} /> },
                        {
                            id: 'net',
                            header: 'Net',
                            render: (row) => <MoneyCell value={row.net_amount} className="font-medium" />,
                        },
                        { id: 'paid', header: 'Paid', render: (row) => <MoneyCell value={row.paid_amount} /> },
                        { id: 'due', header: 'Due', render: (row) => <MoneyCell value={row.due_amount} /> },
                    ]}
                    rows={rows}
                    rowKey={(row) => row.id}
                    emptyMessage="No purchases found for this filter."
                />

                <TotalsBar totals={totals} />

                <div className="mt-8">
                    <h2 className="mb-3 text-sm font-semibold text-blue-950 dark:text-blue-100">
                        Supplier-wise totals
                    </h2>
                    <DataTable
                        columns={[
                            { id: 'supplier', header: 'Supplier', render: (row) => row.supplier_name },
                            {
                                id: 'company',
                                header: 'Company',
                                render: (row) => row.supplier_company || '—',
                            },
                            {
                                id: 'count',
                                header: 'Invoices',
                                render: (row) => <span className="tabular-nums">{row.invoice_count}</span>,
                            },
                            {
                                id: 'gross',
                                header: 'Gross',
                                render: (row) => <MoneyCell value={row.gross_amount} />,
                            },
                            {
                                id: 'discount',
                                header: 'Discount',
                                render: (row) => <MoneyCell value={row.discount} />,
                            },
                            {
                                id: 'net',
                                header: 'Net amount',
                                render: (row) => <MoneyCell value={row.net_amount} className="font-medium" />,
                            },
                            {
                                id: 'paid',
                                header: 'Paid',
                                render: (row) => <MoneyCell value={row.paid_amount} />,
                            },
                            {
                                id: 'due',
                                header: 'Due',
                                render: (row) => <MoneyCell value={row.due_amount} />,
                            },
                        ]}
                        rows={supplier_summaries}
                        rowKey={(row, i) => `${row.supplier_id ?? 'unknown'}-${i}`}
                        emptyMessage="No supplier totals for this period."
                    />
                </div>
            </ReportPage>
        </>
    );
}
