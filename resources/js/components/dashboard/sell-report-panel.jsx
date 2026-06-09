import { CollectionRateGauge } from '@/components/dashboard/collection-rate-gauge';
import { StatTile } from '@/components/dashboard/stat-tile';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';
import { MoneyCell } from '@/pages/admin/reports/_shared/report-shell';
import { router } from '@inertiajs/react';
import { CircleDollarSign } from 'lucide-react';

function formatCountSub(summary) {
    return `${summary?.count ?? 0} invoices · Due ৳${parseFloat(summary?.due ?? 0).toFixed(2)}`;
}

export function SellReportPanel({ sellReport, routeName, showBranchBreakdown = true }) {
    const summary = sellReport?.summary ?? {};
    const periods = sellReport?.periods ?? [];
    const activePeriod = sellReport?.period ?? 'current_month';

    function selectPeriod(value) {
        if (value === activePeriod) {
            return;
        }

        router.get(route(routeName), { period: value }, { preserveState: true, replace: true });
    }

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

            <div className="mb-3 flex flex-wrap gap-1.5">
                {periods.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => selectPeriod(option.value)}
                        className={cn(
                            'border px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide transition-colors',
                            activePeriod === option.value
                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                : 'border-border bg-card text-muted-foreground hover:border-emerald-600/40 hover:text-foreground',
                        )}
                    >
                        {option.label}
                    </button>
                ))}
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
                    sub="Outstanding for period"
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
