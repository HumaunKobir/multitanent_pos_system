import { formatBdDate } from '@/lib/format-bd-date';
import { Head } from '@inertiajs/react';
import { Building2, CalendarRange, Layers3, TrendingUp } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { DataTable } from '@/components/ui/data-table';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

const MONEY_KEYS = new Set(['sales', 'cost', 'profit']);
const PERCENT_KEYS = new Set(['margin']);

function formatAxisMoney(value) {
    const amount = Number(value) || 0;

    if (Math.abs(amount) >= 100000) {
        return `৳${(amount / 100000).toFixed(1)}L`;
    }

    if (Math.abs(amount) >= 1000) {
        return `৳${(amount / 1000).toFixed(1)}K`;
    }

    return `৳${amount}`;
}

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

    if (typeof value === 'number') {
        return <span className="tabular-nums">{value}</span>;
    }

    return String(value);
}

function SummaryCard({ label, value, isPercent = false }) {
    return (
        <div className="rounded-lg border border-blue-950/10 bg-card px-3 py-2 shadow-sm ring-1 ring-blue-950/5">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-sm font-semibold text-blue-950 dark:text-blue-100">
                {isPercent ? (
                    <span className="tabular-nums">{Number(value).toFixed(1)}%</span>
                ) : (
                    <MoneyCell value={value} />
                )}
            </p>
        </div>
    );
}

function chartTooltipStyle() {
    return {
        borderRadius: 0,
        border: '1px solid hsl(var(--border))',
        background: 'hsl(var(--card))',
    };
}

function SalesProfitTrendChart({ data }) {
    const chartData = data ?? [];
    const empty = chartData.every((row) => (Number(row.sales) || 0) === 0 && (Number(row.profit) || 0) === 0);

    if (empty) {
        return (
            <div className="flex h-72 items-center justify-center rounded-lg border border-dashed border-border bg-card text-sm text-muted-foreground">
                No sales in the selected period
            </div>
        );
    }

    return (
        <div className="h-80 w-full rounded-lg border border-blue-950/10 bg-card p-3 shadow-sm ring-1 ring-blue-950/5">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData} margin={{ top: 8, right: 16, left: 0, bottom: 0 }}>
                    <defs>
                        <linearGradient id="salesProfitTrendSalesFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#1e3a8a" stopOpacity={0.3} />
                            <stop offset="95%" stopColor="#1e3a8a" stopOpacity={0} />
                        </linearGradient>
                        <linearGradient id="salesProfitTrendProfitFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#059669" stopOpacity={0.35} />
                            <stop offset="95%" stopColor="#059669" stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="label" tick={{ fontSize: 10 }} interval="preserveStartEnd" />
                    <YAxis tick={{ fontSize: 11 }} tickFormatter={formatAxisMoney} />
                    <Tooltip
                        labelFormatter={(_, payload) => {
                            const period = payload?.[0]?.payload?.period;

                            if (!period) {
                                return '';
                            }

                            if (/^\d{4}-\d{2}-\d{2}$/.test(period)) {
                                return formatBdDate(period);
                            }

                            return payload?.[0]?.payload?.label ?? period;
                        }}
                        formatter={(value, name) => [
                            `৳${Number(value).toFixed(2)}`,
                            name === 'sales' ? 'Sales' : name === 'profit' ? 'Profit' : 'Cost',
                        ]}
                        contentStyle={chartTooltipStyle()}
                    />
                    <Legend />
                    <Area
                        type="monotone"
                        dataKey="sales"
                        name="Sales"
                        stroke="#1e3a8a"
                        fill="url(#salesProfitTrendSalesFill)"
                        strokeWidth={2}
                    />
                    <Area
                        type="monotone"
                        dataKey="profit"
                        name="Profit"
                        stroke="#059669"
                        fill="url(#salesProfitTrendProfitFill)"
                        strokeWidth={2}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}

function BreakdownComparisonChart({ data, groupLabel }) {
    const chartData = (data ?? []).map((row) => ({
        ...row,
        shortName: String(row.name ?? '—').length > 18 ? `${String(row.name).slice(0, 16)}…` : row.name,
    }));

    if (chartData.length === 0) {
        return (
            <div className="flex h-72 items-center justify-center rounded-lg border border-dashed border-border bg-card text-sm text-muted-foreground">
                No {groupLabel.toLowerCase()} data to compare
            </div>
        );
    }

    return (
        <div className="h-80 w-full rounded-lg border border-blue-950/10 bg-card p-3 shadow-sm ring-1 ring-blue-950/5">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} margin={{ top: 8, right: 16, left: 0, bottom: 48 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="shortName" tick={{ fontSize: 10 }} interval={0} angle={-25} textAnchor="end" height={60} />
                    <YAxis tick={{ fontSize: 11 }} tickFormatter={formatAxisMoney} />
                    <Tooltip
                        labelFormatter={(_, payload) => payload?.[0]?.payload?.name ?? ''}
                        formatter={(value, name) => [
                            `৳${Number(value).toFixed(2)}`,
                            name === 'sales' ? 'Sales' : 'Profit',
                        ]}
                        contentStyle={chartTooltipStyle()}
                    />
                    <Legend />
                    <Bar dataKey="sales" name="Sales" fill="#1e3a8a" radius={[2, 2, 0, 0]} />
                    <Bar dataKey="profit" name="Profit" fill="#059669" radius={[2, 2, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

export default function SalesProfitTrend({ groups, periods, branches, isBranchScoped, filters, report }) {
    const [groupBy, setGroupBy] = useState(filters.group_by ?? 'product');
    const [period, setPeriod] = useState(filters.period ?? 'daily');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id != null ? String(filters.branch_id) : '');

    const query = useMemo(
        () => ({
            group_by: groupBy,
            period,
            date_from: dateFrom,
            date_to: dateTo,
            branch_id: !isBranchScoped ? branchId : '',
        }),
        [groupBy, period, dateFrom, dateTo, branchId, isBranchScoped],
    );

    useLiveReportFilters('report.sales-profit-trend', query, [
        groupBy,
        period,
        dateFrom,
        dateTo,
        branchId,
        isBranchScoped,
    ]);

    const groupOptions = useMemo(
        () => (groups ?? []).map((item) => ({ value: item.key, label: item.label })),
        [groups],
    );

    const periodOptions = useMemo(
        () => (periods ?? []).map((item) => ({ value: item.key, label: item.label })),
        [periods],
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

    const groupLabel = groupOptions.find((item) => item.value === groupBy)?.label ?? 'Product wise';

    const tableColumns = useMemo(
        () =>
            (report.breakdown?.columns ?? []).map((column) => ({
                id: column.key,
                header: column.label,
                align: column.align === 'right' ? 'right' : 'left',
                render: (row) => formatCell(column.key, row[column.key]),
            })),
        [report.breakdown?.columns],
    );

    const resetFilters = () => {
        setGroupBy('product');
        setPeriod('daily');
        setDateFrom('');
        setDateTo('');
        setBranchId('');
    };

    return (
        <>
            <Head title="Sales Profit Trend" />
            <ReportPage
                title="Sales Profit Trend"
                description="Sales and profit trend with product, brand, and category breakdowns."
                filterGridClassName="xl:grid-cols-5"
                filterActions={<ReportFilterReset onClick={resetFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Group by" icon={Layers3}>
                            <ReportSelect
                                value={groupBy}
                                onChange={setGroupBy}
                                options={groupOptions}
                                placeholder="Group by"
                            />
                        </ReportFilterField>
                        <ReportFilterField label="Trend period" icon={TrendingUp}>
                            <ReportSelect
                                value={period}
                                onChange={setPeriod}
                                options={periodOptions}
                                placeholder="Period"
                            />
                        </ReportFilterField>
                        <ReportFilterField label="Date from" icon={CalendarRange}>
                            <ReportDateInput value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                        </ReportFilterField>
                        <ReportFilterField label="Date to" icon={CalendarRange}>
                            <ReportDateInput value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                        </ReportFilterField>
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
                    </>
                }
            >
                <div className="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard label="Total Sales" value={report.totals?.sales} />
                    <SummaryCard label="Total Cost" value={report.totals?.cost} />
                    <SummaryCard label="Total Profit" value={report.totals?.profit} />
                    <SummaryCard label="Profit %" value={report.totals?.margin} isPercent />
                </div>

                <div className="mb-4 grid gap-4 xl:grid-cols-2">
                    <section className="min-w-0">
                        <h2 className="mb-2 text-sm font-semibold text-blue-950 dark:text-blue-100">
                            Sales &amp; profit trend
                        </h2>
                        <SalesProfitTrendChart data={report.trend} />
                    </section>
                    <section className="min-w-0">
                        <h2 className="mb-2 text-sm font-semibold text-blue-950 dark:text-blue-100">
                            Top {groupLabel.toLowerCase()} by profit
                        </h2>
                        <BreakdownComparisonChart data={report.comparison} groupLabel={groupLabel} />
                    </section>
                </div>

                <section className="min-w-0 rounded-lg border border-blue-950/10 bg-card p-3 shadow-sm ring-1 ring-blue-950/5">
                    <h2 className="mb-3 text-sm font-semibold text-blue-950 dark:text-blue-100">
                        {groupLabel} breakdown
                    </h2>
                    <DataTable
                        columns={tableColumns}
                        rows={report.breakdown?.rows ?? []}
                        rowKey={(row) => `${row.name ?? 'row'}-${row.code ?? row.quantity ?? ''}`}
                        emptyMessage="No rows for this period."
                    />
                </section>
            </ReportPage>
        </>
    );
}
