import { BranchSalesChart } from '@/components/dashboard/branch-sales-chart';
import { CollectionRateGauge } from '@/components/dashboard/collection-rate-gauge';
import { DashboardShell } from '@/components/dashboard/dashboard-shell';
import { SalesTrendChart } from '@/components/dashboard/sales-trend-chart';
import { StatTile } from '@/components/dashboard/stat-tile';
import { MoneyCell } from '@/pages/admin/reports/_shared/report-shell';
import { Head } from '@inertiajs/react';
import { Building2, CircleDollarSign, TrendingUp } from 'lucide-react';

function formatCountSub(kpi) {
    return `${kpi?.count ?? 0} invoices · Due ৳${parseFloat(kpi?.due ?? 0).toFixed(2)}`;
}

export default function AdminDashboard({ today, kpis, branchSales, salesTrend, collection }) {
    const todaySales = kpis?.today_sales ?? {};
    const monthSales = kpis?.month_sales ?? {};

    return (
        <>
            <Head title="Dashboard" />

            <DashboardShell title="Admin Dashboard" subtitle="Overview across all branches" today={today}>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Today Sales"
                        value={<MoneyCell value={todaySales.gross} />}
                        sub={formatCountSub(todaySales)}
                        accentClass="border-l-emerald-600"
                    />
                    <StatTile
                        label="Month Sales"
                        value={<MoneyCell value={monthSales.gross} />}
                        sub={formatCountSub(monthSales)}
                        accentClass="border-l-blue-600"
                    />
                    <StatTile
                        label="Operating Branches"
                        value={`${kpis?.active_branches ?? 0} / ${kpis?.total_branches ?? 0}`}
                        sub="Excludes main branch (ID 1)"
                        accentClass="border-l-violet-600"
                    />
                    <CollectionRateGauge collection={collection} />
                </div>

                <div className="grid gap-3 lg:grid-cols-2">
                    <div>
                        <div className="mb-2 flex items-center gap-2 border-b border-border pb-2">
                            <Building2 className="size-4 text-emerald-600" />
                            <h2 className="text-xs font-semibold uppercase tracking-widest">Branch-wise Sales (This Month)</h2>
                        </div>
                        <BranchSalesChart data={branchSales} />
                    </div>
                    <div>
                        <div className="mb-2 flex items-center gap-2 border-b border-border pb-2">
                            <TrendingUp className="size-4 text-blue-600" />
                            <h2 className="text-xs font-semibold uppercase tracking-widest">30-Day Sales Trend</h2>
                        </div>
                        <SalesTrendChart data={salesTrend} />
                    </div>
                </div>

                <div>
                    <div className="mb-2 flex items-center gap-2 border-b border-border pb-2">
                        <CircleDollarSign className="size-4 text-emerald-600" />
                        <h2 className="text-xs font-semibold uppercase tracking-widest">Branch Performance</h2>
                    </div>
                    <div className="overflow-x-auto border border-border bg-card">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/40 text-left">
                                    <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Branch</th>
                                    <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Today</th>
                                    <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Month</th>
                                    <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Collected</th>
                                    <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Due</th>
                                    <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(branchSales ?? []).length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                                            No branches found
                                        </td>
                                    </tr>
                                ) : (
                                    branchSales.map((row) => (
                                        <tr key={row.branch_id} className="border-b border-border last:border-b-0">
                                            <td className="px-4 py-2.5 font-medium">{row.branch_name}</td>
                                            <td className="px-4 py-2.5 font-mono tabular-nums">
                                                <MoneyCell value={row.today_gross} />
                                            </td>
                                            <td className="px-4 py-2.5 font-mono tabular-nums">
                                                <MoneyCell value={row.month_gross} />
                                            </td>
                                            <td className="px-4 py-2.5 font-mono tabular-nums text-emerald-700 dark:text-emerald-400">
                                                <MoneyCell value={row.month_paid} />
                                            </td>
                                            <td className="px-4 py-2.5 font-mono tabular-nums text-amber-700 dark:text-amber-400">
                                                <MoneyCell value={row.month_due} />
                                            </td>
                                            <td className="px-4 py-2.5 font-mono tabular-nums">{row.collection_rate}%</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </DashboardShell>
        </>
    );
}
