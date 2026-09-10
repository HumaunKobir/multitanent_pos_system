import { Can } from '@/components/can';
import { CollectionRateGauge } from '@/components/dashboard/collection-rate-gauge';
import { DashboardShell } from '@/components/dashboard/dashboard-shell';
import { ModuleWidget } from '@/components/dashboard/module-widget';
import { PanelWelcome } from '@/components/dashboard/panel-welcome';
import { QuickActions } from '@/components/dashboard/quick-actions';
import { SalesTrendChart } from '@/components/dashboard/sales-trend-chart';
import { SellReportPanel } from '@/components/dashboard/sell-report-panel';
import { StatTile } from '@/components/dashboard/stat-tile';
import { MoneyCell } from '@/pages/admin/reports/_shared/report-shell';
import { route } from '@/lib/route';
import { Head, Link } from '@inertiajs/react';
import {
    BarChart2,
    CircleDollarSign,
    Coins,
    HandCoins,
    ReceiptText,
    UsersRound,
    Wallet,
} from 'lucide-react';

function formatCountSub(kpi) {
    const parts = [`${kpi?.count ?? 0} invoices`, `Due ৳${parseFloat(kpi?.due ?? 0).toFixed(2)}`];

    if ((kpi?.refund_due ?? 0) > 0) {
        parts.push(`Refund due ৳${parseFloat(kpi.refund_due).toFixed(2)}`);
    }

    return parts.join(' · ');
}

function formatExpenseSub(kpi) {
    return `${kpi?.count ?? 0} voucher(s)`;
}

export default function BranchDashboard({ today, branchName, branchLogoUrl, sections, limitedAccess, userName }) {
    if (limitedAccess) {
        return (
            <>
                <Head title="Welcome" />

                <DashboardShell title="Welcome" subtitle={branchName} today={today}>
                    <PanelWelcome userName={userName} branchName={branchName} branchLogoUrl={branchLogoUrl} />
                </DashboardShell>
            </>
        );
    }

    const hasSections = sections && Object.keys(sections).length > 0;
    const sales = sections?.sales;

    return (
        <>
            <Head title="Dashboard" />

            <DashboardShell
                title={`${branchName || 'Branch'} Dashboard`}
                subtitle="Your branch overview"
                today={today}
            >
                {!hasSections ? (
                    <div className="border border-border bg-card p-8 text-center shadow-none">
                        <p className="text-sm font-medium">Welcome to {branchName || 'your branch'}</p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            No module access assigned yet. Contact your administrator for permissions.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Can permission="inventory.sell.view">
                                <StatTile
                                    label="Today Sales"
                                    value={<MoneyCell value={sales?.today?.gross} />}
                                    sub={formatCountSub(sales?.today)}
                                    accentClass="border-l-emerald-600"
                                />
                            </Can>

                            <Can permission="inventory.purchase.view">
                                <StatTile
                                    label="Today Purchases"
                                    value={<MoneyCell value={sections?.purchases?.today?.gross} />}
                                    sub={formatCountSub(sections?.purchases?.today)}
                                    accentClass="border-l-blue-600"
                                />
                            </Can>

                            <Can permission="inventory.sale-return.view">
                                <StatTile
                                    label="Today Returns"
                                    value={sections?.sale_returns?.today_count ?? 0}
                                    sub={
                                        <>
                                            Amount: <MoneyCell value={sections?.sale_returns?.today_amount} />
                                        </>
                                    }
                                    accentClass="border-l-amber-600"
                                />
                            </Can>

                            <Can permission="party.customer.view">
                                <StatTile
                                    label="Customers"
                                    value={sections?.customers?.count ?? 0}
                                    sub={
                                        <>
                                            Total due: <MoneyCell value={sections?.customers?.total_due} />
                                        </>
                                    }
                                    accentClass="border-l-violet-600"
                                />
                            </Can>

                            <Can permission="accounts.view">
                                <StatTile
                                    label="Today Expenses"
                                    value={<MoneyCell value={sections?.expenses?.today?.amount} />}
                                    sub={formatExpenseSub(sections?.expenses?.today)}
                                    accentClass="border-l-rose-600"
                                />
                            </Can>
                        </div>

                        <Can permission="inventory.sell.view">
                            <SellReportPanel
                                sellReport={sales?.report}
                                routeName="branch-panel.dashboard"
                                showBranchBreakdown={false}
                            />

                            <div className="grid gap-3 lg:grid-cols-3">
                                <div className="lg:col-span-2">
                                    <ModuleWidget title="30-Day Sales Trend" icon={CircleDollarSign}>
                                        <SalesTrendChart data={sales?.trend} />
                                    </ModuleWidget>
                                </div>
                                <CollectionRateGauge collection={sales?.collection} />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <ModuleWidget title="Month Sales" icon={CircleDollarSign} accentClass="border-l-emerald-600">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <p className="text-[10px] uppercase tracking-widest text-muted-foreground">Gross</p>
                                            <p className="font-mono text-lg font-bold tabular-nums">
                                                <MoneyCell value={sales?.month?.gross} />
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-[10px] uppercase tracking-widest text-muted-foreground">Collected</p>
                                            <p className="font-mono text-lg font-bold tabular-nums text-emerald-700 dark:text-emerald-400">
                                                <MoneyCell value={sales?.month?.paid} />
                                            </p>
                                        </div>
                                    </div>
                                </ModuleWidget>
                            </div>
                        </Can>

                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Can permission="inventory.purchase.view">
                                <ModuleWidget title="Month Purchases" icon={HandCoins} accentClass="border-l-blue-600">
                                    <p className="font-mono text-xl font-bold tabular-nums">
                                        <MoneyCell value={sections?.purchases?.month?.gross} />
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">{formatCountSub(sections?.purchases?.month)}</p>
                                </ModuleWidget>
                            </Can>

                            <Can permission="party.supplier-payment.view">
                                <ModuleWidget title="Supplier Payments" icon={Wallet} accentClass="border-l-violet-600">
                                    <p className="font-mono text-xl font-bold tabular-nums">
                                        <MoneyCell value={sections?.supplier_payments?.month_amount} />
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">Paid this month</p>
                                </ModuleWidget>
                            </Can>

                            <Can permission="party.customer-due-collection.view">
                                <ModuleWidget title="Due Collections" icon={Coins} accentClass="border-l-teal-600">
                                    <p className="font-mono text-xl font-bold tabular-nums">
                                        <MoneyCell value={sections?.customer_collections?.month_amount} />
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">Collected this month</p>
                                </ModuleWidget>
                            </Can>

                            <Can permission="accounts.view">
                                <ModuleWidget title="Month Expenses" icon={ReceiptText} accentClass="border-l-rose-600">
                                    <p className="font-mono text-xl font-bold tabular-nums">
                                        <MoneyCell value={sections?.expenses?.month?.amount} />
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">{formatExpenseSub(sections?.expenses?.month)}</p>
                                </ModuleWidget>
                            </Can>

                            <Can permission="report.daily-summary.view">
                                <ModuleWidget title="Reports" icon={BarChart2} accentClass="border-l-slate-600">
                                    <Link
                                        href={route('report.daily-summary')}
                                        className="inline-flex items-center gap-2 border border-border px-3 py-2 text-xs font-medium hover:bg-muted"
                                    >
                                        <BarChart2 className="size-3.5" />
                                        Open Daily Summary
                                    </Link>
                                </ModuleWidget>
                            </Can>
                        </div>

                        <QuickActions />
                    </>
                )}
            </DashboardShell>
        </>
    );
}
