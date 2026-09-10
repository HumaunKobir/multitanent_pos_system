import { DashboardShell } from '@/components/dashboard/dashboard-shell';
import { PanelWelcome } from '@/components/dashboard/panel-welcome';
import { StatTile } from '@/components/dashboard/stat-tile';
import { MoneyCell } from '@/pages/admin/reports/_shared/report-shell';
import { route } from '@/lib/route';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CircleDollarSign,
    Clock3,
    Settings,
    ShieldAlert,
    UsersRound,
    Wallet,
} from 'lucide-react';

const toneClasses = {
    emerald: 'border-l-emerald-600',
    amber: 'border-l-amber-500',
    rose: 'border-l-rose-600',
    slate: 'border-l-slate-500',
    blue: 'border-l-blue-600',
    violet: 'border-l-violet-600',
};

export default function AdminDashboard({
    today,
    kpis,
    statusBreakdown = [],
    recentPending = [],
    links = {},
    limitedAccess,
    userName,
    branchName,
    branchLogoUrl,
}) {
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

    const systemName = kpis?.system_name ?? 'SaaS Platform';

    return (
        <>
            <Head title="Dashboard" />
            <DashboardShell
                title="SaaS Admin Dashboard"
                subtitle={`${systemName} · subscription platform overview`}
                today={today}
            >
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <StatTile
                        label="Client Branches"
                        value={`${kpis?.active_clients ?? 0} / ${kpis?.total_clients ?? 0}`}
                        sub="Active / total (excludes main)"
                        accentClass="border-l-violet-600"
                    />
                    <StatTile
                        label="Today Collected"
                        value={<MoneyCell value={kpis?.today_collected} />}
                        sub="Approved subscription payments"
                        accentClass="border-l-emerald-600"
                    />
                    <StatTile
                        label="Month Collected"
                        value={<MoneyCell value={kpis?.month_collected} />}
                        sub="Approved this month"
                        accentClass="border-l-blue-600"
                    />
                    <StatTile
                        label="Pending Approvals"
                        value={kpis?.pending_approvals ?? 0}
                        sub={<MoneyCell value={kpis?.pending_amount} />}
                        accentClass="border-l-amber-500"
                    />
                    <StatTile
                        label="Overdue Clients"
                        value={kpis?.overdue_clients ?? 0}
                        sub={
                            <>
                                Due <MoneyCell value={kpis?.total_overdue_due} />
                            </>
                        }
                        accentClass="border-l-rose-600"
                    />
                    <StatTile
                        label="Multi-Tenant"
                        value={kpis?.multi_tenant_enabled ? 'ON' : 'OFF'}
                        sub="Business Setup → System"
                        accentClass="border-l-slate-600"
                    />
                </div>

                <div className="grid gap-3 lg:grid-cols-2">
                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="mb-3 flex items-center gap-2 border-b border-border pb-2">
                            <UsersRound className="size-4 text-violet-600" />
                            <h2 className="text-xs font-semibold uppercase tracking-widest">Client Status</h2>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {statusBreakdown.map((row) => (
                                <div
                                    key={row.label}
                                    className={`rounded-lg border border-border border-l-4 bg-muted/20 px-3 py-2 ${toneClasses[row.tone] ?? 'border-l-slate-400'}`}
                                >
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        {row.label}
                                    </p>
                                    <p className="mt-0.5 text-xl font-bold tabular-nums">{row.count}</p>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="mb-3 flex items-center justify-between gap-2 border-b border-border pb-2">
                            <div className="flex items-center gap-2">
                                <Clock3 className="size-4 text-amber-600" />
                                <h2 className="text-xs font-semibold uppercase tracking-widest">Pending Payments</h2>
                            </div>
                            <Link
                                href={links.clients ?? route('branch-clients.index')}
                                className="text-[11px] font-semibold text-primary hover:underline"
                            >
                                Open clients
                            </Link>
                        </div>
                        {recentPending.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">No pending subscription payments.</p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {recentPending.map((item) => (
                                    <li key={item.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                        <div>
                                            <p className="font-medium">{item.branch_name}</p>
                                            <p className="text-[11px] text-muted-foreground">
                                                {item.payment_method || 'Payment'} · {item.submitted}
                                            </p>
                                        </div>
                                        <p className="font-mono font-semibold tabular-nums">
                                            <MoneyCell value={item.amount} />
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <QuickLink href={links.clients ?? '/branch-clients'} icon={Building2} label="Branch Clients" hint="Renew & approve" />
                    <QuickLink href={links.billing_report ?? '/report/subscription-billing'} icon={Wallet} label="Billing Report" hint="Collections & invoices" />
                    <QuickLink href={links.profit_loss ?? '/report/profit-loss'} icon={CircleDollarSign} label="SaaS P&L" hint="Subscription income" />
                    <QuickLink href={links.business_setup ?? '/setting/business-setup'} icon={Settings} label="Business Setup" hint="Name & multi-tenant" />
                </div>

                {(kpis?.overdue_clients ?? 0) > 0 ? (
                    <div className="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            <span className="font-semibold">{kpis.overdue_clients} client(s)</span> overdue with{' '}
                            <MoneyCell value={kpis.total_overdue_due} /> outstanding. Review Branch Clients to renew or suspend.
                        </p>
                    </div>
                ) : null}

                {(kpis?.expiring_soon ?? 0) > 0 ? (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p>
                            <span className="font-semibold">{kpis.expiring_soon} subscription(s)</span> expiring soon.
                        </p>
                    </div>
                ) : null}
            </DashboardShell>
        </>
    );
}

function QuickLink({ href, icon: Icon, label, hint }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-xs transition hover:border-primary/40 hover:bg-muted/30"
        >
            <div className="flex size-9 items-center justify-center rounded-lg bg-muted text-foreground">
                <Icon className="size-4" />
            </div>
            <div>
                <p className="text-sm font-semibold">{label}</p>
                <p className="text-[11px] text-muted-foreground">{hint}</p>
            </div>
        </Link>
    );
}
