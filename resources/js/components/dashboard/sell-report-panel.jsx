import { CollectionRateGauge } from '@/components/dashboard/collection-rate-gauge';
import { StatTile } from '@/components/dashboard/stat-tile';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { MoneyCell, ReportDateInput } from '@/pages/admin/reports/_shared/report-shell';
import { router } from '@inertiajs/react';
import { CircleDollarSign } from 'lucide-react';
import { useEffect, useState } from 'react';

function formatCountSub(summary) {
    const parts = [`${summary?.count ?? 0} invoices`, `Due ৳${parseFloat(summary?.due ?? 0).toFixed(2)}`];

    if ((summary?.refund_due ?? 0) > 0) {
        parts.push(`Refund due ৳${parseFloat(summary.refund_due).toFixed(2)}`);
    }

    return parts.join(' · ');
}

export function SellReportPanel({ sellReport, routeName, showBranchBreakdown = true }) {
    const summary = sellReport?.summary ?? {};
    const periods = sellReport?.periods ?? [];
    const activePeriod = sellReport?.period ?? 'current_month';
    const showBranchRefundDue = (sellReport?.branch_breakdown ?? []).some((row) => (row.refund_due ?? 0) > 0);
    const [customDateFrom, setCustomDateFrom] = useState(
        activePeriod === 'custom' ? (sellReport?.date_from ?? '') : '',
    );
    const [customDateTo, setCustomDateTo] = useState(activePeriod === 'custom' ? (sellReport?.date_to ?? '') : '');

    useEffect(() => {
        if (activePeriod !== 'custom') {
            return;
        }

        setCustomDateFrom(sellReport?.date_from ?? '');
        setCustomDateTo(sellReport?.date_to ?? '');
    }, [activePeriod, sellReport?.date_from, sellReport?.date_to]);

    const activeLabel = periods.find((option) => option.value === activePeriod)?.label ?? sellReport?.label ?? 'Period';

    function visitPeriod(period, dateFrom = null, dateTo = null) {
        const params = { period };

        if (period === 'custom' && dateFrom && dateTo) {
            params.date_from = dateFrom;
            params.date_to = dateTo;
        }

        router.get(route(routeName), params, { preserveState: true, replace: true });
    }

    function selectPeriod(value) {
        if (value === activePeriod) {
            return;
        }

        if (value === 'custom') {
            setCustomDateFrom('');
            setCustomDateTo('');
            router.get(route(routeName), { period: 'custom' }, { preserveState: true, replace: true });
            return;
        }

        visitPeriod(value);
    }

    useDebouncedEffect(
        () => {
            if (activePeriod !== 'custom' || !customDateFrom || !customDateTo) {
                return;
            }

            if (customDateFrom === sellReport?.date_from && customDateTo === sellReport?.date_to) {
                return;
            }

            visitPeriod('custom', customDateFrom, customDateTo);
        },
        [customDateFrom, customDateTo, activePeriod],
        350,
        { skipFirstRun: true },
    );

    return (
        <div>
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                <div className="flex items-center gap-2">
                    <CircleDollarSign className="size-4 text-emerald-600" />
                    <h2 className="text-xs font-semibold uppercase tracking-widest">Sales Report</h2>
                </div>
                <p className="text-xs text-muted-foreground">
                    {formatBdDate(sellReport?.date_from)} — {formatBdDate(sellReport?.date_to)}
                </p>
            </div>

            <div className="mb-3 flex flex-wrap items-end gap-2">
                <div className="min-w-[180px]">
                    <Select value={activePeriod} onValueChange={selectPeriod}>
                        <SelectTrigger className="h-8 border-border bg-card text-xs font-medium uppercase tracking-wide">
                            <SelectValue placeholder="Select period">{activeLabel}</SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            {periods.map((option) => (
                                <SelectItem key={option.value} value={option.value} className="text-xs uppercase tracking-wide">
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {activePeriod === 'custom' ? (
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="min-w-[150px]">
                            <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">From</p>
                            <div className="rounded-md border border-border bg-card">
                                <ReportDateInput value={customDateFrom} onChange={setCustomDateFrom} />
                            </div>
                        </div>
                        <div className="min-w-[150px]">
                            <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">To</p>
                            <div className="rounded-md border border-border bg-card">
                                <ReportDateInput value={customDateTo} onChange={setCustomDateTo} />
                            </div>
                        </div>
                    </div>
                ) : null}
            </div>

            <div className="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile
                    label={`${sellReport?.label ?? 'Period'} Gross`}
                    value={<MoneyCell value={summary.gross} />}
                    sub={formatCountSub(summary)}
                    accentClass="border-l-emerald-600"
                />
                <StatTile
                    label="Collected"
                    value={<MoneyCell value={summary.paid} />}
                    sub={`${summary.count ?? 0} invoice(s)`}
                    accentClass="border-l-blue-600"
                />
                <StatTile
                    label="Due"
                    value={<MoneyCell value={summary.due} />}
                    sub={
                        (summary.refund_due ?? 0) > 0
                            ? `Refund due ৳${parseFloat(summary.refund_due).toFixed(2)}`
                            : 'Outstanding for period'
                    }
                    accentClass="border-l-amber-600"
                />
                <CollectionRateGauge collection={sellReport?.collection} />
            </div>

            {showBranchBreakdown && (sellReport?.branch_breakdown ?? []).length > 0 ? (
                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr className="border-b border-border bg-muted/40 text-left">
                                <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Branch</th>
                                <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Invoices</th>
                                <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Gross</th>
                                <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Collected</th>
                                <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Due</th>
                                {showBranchRefundDue ? (
                                    <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">
                                        Refund due
                                    </th>
                                ) : null}
                                <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sellReport.branch_breakdown.map((row) => (
                                <tr key={row.branch_id} className="border-b border-border last:border-b-0">
                                    <td className="px-4 py-2.5 font-medium">{row.branch_name}</td>
                                    <td className="px-4 py-2.5 font-mono tabular-nums">{row.invoice_count}</td>
                                    <td className="px-4 py-2.5 font-mono tabular-nums">
                                        <MoneyCell value={row.gross} />
                                    </td>
                                    <td className="px-4 py-2.5 font-mono tabular-nums text-emerald-700 dark:text-emerald-400">
                                        <MoneyCell value={row.paid} />
                                    </td>
                                    <td className="px-4 py-2.5 font-mono tabular-nums text-amber-700 dark:text-amber-400">
                                        <MoneyCell value={row.due} />
                                    </td>
                                    {showBranchRefundDue ? (
                                        <td className="px-4 py-2.5 font-mono tabular-nums text-rose-700 dark:text-rose-400">
                                            <MoneyCell value={row.refund_due} />
                                        </td>
                                    ) : null}
                                    <td className="px-4 py-2.5 font-mono tabular-nums">{row.collection_rate}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : null}
        </div>
    );
}
