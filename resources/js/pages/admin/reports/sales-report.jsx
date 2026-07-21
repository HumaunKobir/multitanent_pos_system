import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import {
    Building2,
    CalendarRange,
    Clock3,
    Gauge,
    Layers3,
} from 'lucide-react';
import { useMemo, useState } from 'react';

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
import { cn } from '@/lib/utils';

const MONEY_KEYS = new Set([
    'gross',
    'discount',
    'vat',
    'net',
    'paid',
    'amount',
    'revenue',
    'cost',
    'profit',
    'value',
]);

const DATE_SENSITIVE_TYPES = new Set([
    'daily',
    'monthly',
    'hourly',
    'product',
    'brand',
    'category',
    'profit',
    'fast_moving',
    'slow_moving',
    'salesman',
]);

const PERCENT_KEYS = new Set(['margin', 'discount_pct', 'vat_pct']);

function formatCell(columnKey, value) {
    if (value == null || value === '') {
        return '—';
    }

    if (MONEY_KEYS.has(columnKey)) {
        return <MoneyCell value={value} className="tabular-nums" />;
    }

    if (PERCENT_KEYS.has(columnKey)) {
        return <span className="tabular-nums">{Number(value).toFixed(1)}%</span>;
    }

    if (columnKey === 'period' && /^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        return formatBdDate(value);
    }

    if (typeof value === 'number') {
        return <span className="tabular-nums">{value}</span>;
    }

    return String(value);
}

function TotalsBar({ columns, totals }) {
    if (!totals || Object.keys(totals).length === 0) {
        return null;
    }

    return (
        <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {columns
                .filter((column) => totals[column.key] != null)
                .map((column) => (
                    <div
                        key={column.key}
                        className="rounded-lg border border-blue-950/10 bg-card px-3 py-2 shadow-sm ring-1 ring-blue-950/5"
                    >
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Total {column.label}
                        </p>
                        <p className="mt-0.5 text-sm font-semibold text-blue-950 dark:text-blue-100">
                            {formatCell(column.key, totals[column.key])}
                        </p>
                    </div>
                ))}
            {totals.items != null ? (
                <div className="rounded-lg border border-blue-950/10 bg-card px-3 py-2 shadow-sm ring-1 ring-blue-950/5">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Total Items
                    </p>
                    <p className="mt-0.5 text-sm font-semibold tabular-nums text-blue-950 dark:text-blue-100">
                        {totals.items}
                    </p>
                </div>
            ) : null}
        </div>
    );
}

export default function SalesReport({ types, branches, isBranchScoped, filters, report }) {
    const [type, setType] = useState(filters.type ?? 'daily');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [branchId, setBranchId] = useState(
        filters.branch_id != null ? String(filters.branch_id) : '',
    );
    const [threshold, setThreshold] = useState(String(filters.threshold ?? 5));
    const [inactiveDays, setInactiveDays] = useState(String(filters.inactive_days ?? 90));

    const needsDates = DATE_SENSITIVE_TYPES.has(type);
    const needsThreshold = type === 'low_stock';
    const needsInactiveDays = type === 'dead_stock';

    useLiveReportFilters(
        'report.sales-report',
        {
            type,
            date_from: needsDates ? dateFrom : '',
            date_to: needsDates ? dateTo : '',
            branch_id: !isBranchScoped ? branchId : '',
            threshold: needsThreshold ? threshold : '',
            inactive_days: needsInactiveDays ? inactiveDays : '',
        },
        [type, dateFrom, dateTo, branchId, threshold, inactiveDays, needsDates, needsThreshold, needsInactiveDays, isBranchScoped],
    );

    const typeOptions = useMemo(
        () => (types ?? []).map((item) => ({ value: item.key, label: item.label })),
        [types],
    );

    const branchOptions = useMemo(
        () => [
            { value: '__all', label: 'All branches' },
            ...(branches ?? []).map((branch) => ({
                value: String(branch.id),
                label: branch.label,
            })),
        ],
        [branches],
    );

    const tableColumns = useMemo(
        () =>
            (report.columns ?? []).map((column) => ({
                id: column.key,
                header: column.label,
                align: column.align === 'right' ? 'right' : 'left',
                render: (row) => formatCell(column.key, row[column.key]),
            })),
        [report.columns],
    );

    const resetFilters = () => {
        setType('daily');
        setDateFrom('');
        setDateTo('');
        setBranchId('');
        setThreshold('5');
        setInactiveDays('90');
        router.get(route('report.sales-report'), {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Sales Report" />
            <ReportPage
                title="Sales Report"
                description={report.label}
                filterActions={<ReportFilterReset onClick={resetFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Report Type" icon={Layers3}>
                            <ReportSelect
                                value={type}
                                onChange={setType}
                                options={typeOptions}
                                placeholder="Select report"
                            />
                        </ReportFilterField>

                        {needsDates ? (
                            <>
                                <ReportFilterField label="Date From" icon={CalendarRange}>
                                    <ReportDateInput value={dateFrom} onChange={setDateFrom} />
                                </ReportFilterField>
                                <ReportFilterField label="Date To" icon={CalendarRange}>
                                    <ReportDateInput value={dateTo} onChange={setDateTo} />
                                </ReportFilterField>
                            </>
                        ) : null}

                        {!isBranchScoped ? (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId || '__all'}
                                    onChange={(value) => setBranchId(value === '__all' ? '' : value)}
                                    options={branchOptions}
                                    placeholder="All branches"
                                />
                            </ReportFilterField>
                        ) : null}

                        {needsThreshold ? (
                            <ReportFilterField label="Low stock threshold" icon={Gauge}>
                                <input
                                    type="number"
                                    min={0}
                                    value={threshold}
                                    onChange={(e) => setThreshold(e.target.value)}
                                    className="h-9 w-full border-0 bg-transparent px-3 text-sm shadow-none outline-none"
                                />
                            </ReportFilterField>
                        ) : null}

                        {needsInactiveDays ? (
                            <ReportFilterField label="No-sale days" icon={Clock3}>
                                <input
                                    type="number"
                                    min={1}
                                    value={inactiveDays}
                                    onChange={(e) => setInactiveDays(e.target.value)}
                                    className="h-9 w-full border-0 bg-transparent px-3 text-sm shadow-none outline-none"
                                />
                            </ReportFilterField>
                        ) : null}
                    </>
                }
            >
                <div className="mb-3 flex flex-wrap gap-1.5">
                    {(types ?? []).map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setType(item.key)}
                            className={cn(
                                'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                type === item.key
                                    ? 'border-blue-950 bg-blue-950 text-white'
                                    : 'border-blue-950/15 bg-white text-blue-950 hover:bg-blue-50',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                <ReportInfoBanner>
                    Showing <strong>{report.label}</strong>
                    {needsDates && dateFrom && dateTo
                        ? ` · ${formatBdDate(dateFrom)} to ${formatBdDate(dateTo)}`
                        : ''}
                    {needsThreshold ? ` · threshold ${threshold}` : ''}
                    {needsInactiveDays ? ` · inactive ${inactiveDays} days` : ''}
                    {` · ${(report.rows ?? []).length} row(s)`}
                </ReportInfoBanner>

                <DataTable
                    columns={tableColumns}
                    rows={report.rows ?? []}
                    rowKey={(row) => `${row.name ?? row.period ?? 'row'}-${row.code ?? row.quantity ?? ''}`}
                    emptyMessage="No rows for this report."
                />

                <TotalsBar columns={report.columns ?? []} totals={report.totals ?? {}} />
            </ReportPage>
        </>
    );
}
