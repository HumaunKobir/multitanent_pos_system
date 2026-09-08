import { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    ExternalLink,
    Eye,
    Filter,
    History,
    Layers,
    Phone,
    Plus,
    RefreshCw,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sliders,
    Sparkles,
    TrendingUp,
    UsersRound,
    X,
} from 'lucide-react';
import { route } from '@/lib/route';
import { useAppToast } from '@/contexts/app-toast-context';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

import RenewSubscriptionDialog from '@/pages/admin/setting/business-setup/partials/renew-subscription-dialog';
import EditBranchSubscriptionDialog from '@/pages/admin/setting/business-setup/partials/edit-branch-subscription-dialog';
import PaymentHistoryDialog from '@/pages/admin/setting/business-setup/partials/payment-history-dialog';

export default function BranchClientIndex({
    branches = [],
    stats = {},
    filters = {},
    billingCycles = [],
    overdueActions = [],
    paymentMethods = [],
}) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? 'all');
    const [selectedBranch, setSelectedBranch] = useState(null);
    const [renewModalOpen, setRenewModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [historyModalOpen, setHistoryModalOpen] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    useDebouncedEffect(
        () => {
            router.get(
                route('branch-clients.index'),
                {
                    search: search || undefined,
                    status: statusFilter !== 'all' ? statusFilter : undefined,
                },
                { preserveState: true, replace: true },
            );
        },
        [search, statusFilter],
        350,
        { skipFirstRun: true },
    );

    const handleFilterChange = (status) => {
        setStatusFilter(status);
        router.get(
            route('branch-clients.index'),
            {
                search: search || undefined,
                status: status !== 'all' ? status : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const openRenew = (branch) => {
        setSelectedBranch(branch);
        setRenewModalOpen(true);
    };

    const openEdit = (branch) => {
        setSelectedBranch(branch);
        setEditModalOpen(true);
    };

    const openHistory = (branch) => {
        setSelectedBranch(branch);
        setHistoryModalOpen(true);
    };

    return (
        <div className="space-y-6 p-4 sm:p-6 max-w-7xl mx-auto">
            <Head title="Branch Clients & Subscriptions" />

            {/* Header Hero Banner */}
            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-950 to-blue-950 p-6 text-white shadow-xl">
                <div className="absolute right-0 top-0 -mt-10 -mr-10 h-64 w-64 rounded-full bg-blue-500/10 blur-3xl" />
                <div className="absolute left-1/4 bottom-0 -mb-10 h-48 w-48 rounded-full bg-indigo-500/10 blur-2xl" />

                <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/20 px-3 py-0.5 text-xs font-semibold text-blue-200 border border-blue-400/20">
                                <UsersRound className="size-3.5" />
                                SuperAdmin Central Management
                            </span>
                        </div>
                        <h1 className="text-xl sm:text-2xl font-extrabold tracking-tight text-white">
                            Branch Clients & Subscriptions
                        </h1>
                        <p className="text-xs text-slate-300 max-w-2xl leading-relaxed">
                            Monitor client branches, configure custom subscription plans & billing cycles, record renewal payments, and audit transaction receipts.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="rounded-xl bg-white/10 p-3 backdrop-blur-md border border-white/15 text-center min-w-[120px]">
                            <p className="text-[10px] text-blue-200 uppercase tracking-wider font-bold">Total Clients</p>
                            <p className="text-xl font-extrabold text-white tabular-nums">{stats.total_clients ?? 0}</p>
                        </div>
                        <div className="rounded-xl bg-rose-500/20 p-3 backdrop-blur-md border border-rose-500/30 text-center min-w-[130px]">
                            <p className="text-[10px] text-rose-200 uppercase tracking-wider font-bold">Total Due</p>
                            <p className="text-xl font-extrabold text-rose-300 tabular-nums">৳{Number(stats.total_overdue_due || 0).toFixed(0)}</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* KPI Stat Cards */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4">
                <div className="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-card p-4 sm:p-5 shadow-xs transition-all hover:shadow-md hover:border-blue-500/40">
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Total Clients</p>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/70 dark:text-blue-400">
                            <Building2 className="size-4" />
                        </div>
                    </div>
                    <div className="mt-2.5">
                        <span className="text-2xl font-extrabold text-foreground tabular-nums">{stats.total_clients ?? 0}</span>
                        <p className="text-[11px] text-muted-foreground mt-0.5">Registered outlets</p>
                    </div>
                </div>

                <div className={`group relative overflow-hidden rounded-2xl border p-4 sm:p-5 shadow-xs transition-all hover:shadow-md ${
                    (stats.pending_approvals ?? 0) > 0
                        ? 'border-amber-400/70 bg-gradient-to-br from-amber-500/15 via-amber-500/5 to-transparent hover:border-amber-500'
                        : 'border-slate-200 dark:border-slate-800 bg-card hover:border-amber-500/40'
                }`}>
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider font-bold">Pending Review</p>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs">
                            <CreditCard className="size-4" />
                        </div>
                    </div>
                    <div className="mt-2.5">
                        <span className="text-2xl font-extrabold text-amber-600 dark:text-amber-400 tabular-nums">{stats.pending_approvals ?? 0}</span>
                        <p className="text-[11px] text-amber-600/80 dark:text-amber-400/80 mt-0.5">Awaiting admin confirm</p>
                    </div>
                </div>

                <div className="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-card p-4 sm:p-5 shadow-xs transition-all hover:shadow-md hover:border-emerald-500/40">
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Active</p>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/70 dark:text-emerald-400">
                            <CheckCircle2 className="size-4" />
                        </div>
                    </div>
                    <div className="mt-2.5">
                        <span className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">{stats.active_clients ?? 0}</span>
                        <p className="text-[11px] text-emerald-600/80 dark:text-emerald-400/80 mt-0.5">In good standing</p>
                    </div>
                </div>

                <div className="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-card p-4 sm:p-5 shadow-xs transition-all hover:shadow-md hover:border-amber-500/40">
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Expiring Soon</p>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/70 dark:text-amber-400">
                            <Clock className="size-4" />
                        </div>
                    </div>
                    <div className="mt-2.5">
                        <span className="text-2xl font-extrabold text-amber-600 dark:text-amber-400 tabular-nums">{stats.expiring_soon ?? 0}</span>
                        <p className="text-[11px] text-amber-600/80 dark:text-amber-400/80 mt-0.5">Due in warning window</p>
                    </div>
                </div>

                <div className="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-card p-4 sm:p-5 shadow-xs transition-all hover:shadow-md hover:border-rose-500/40 col-span-2 md:col-span-1">
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Overdue</p>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/70 dark:text-rose-400">
                            <AlertTriangle className="size-4" />
                        </div>
                    </div>
                    <div className="mt-2.5">
                        <div className="flex items-baseline gap-1.5">
                            <span className="text-2xl font-extrabold text-rose-600 dark:text-rose-400 tabular-nums">{stats.overdue_clients ?? 0}</span>
                            <span className="text-xs font-bold text-rose-600/80 tabular-nums">
                                (৳{Number(stats.total_overdue_due || 0).toFixed(0)})
                            </span>
                        </div>
                        <p className="text-[11px] text-rose-600/80 dark:text-rose-400/80 mt-0.5">Pending bills</p>
                    </div>
                </div>
            </div>

            {/* Main Table Workspace */}
            <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-card shadow-sm overflow-hidden space-y-4 p-5">
                {/* Filters & Search */}
                <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                    {/* Status Tabs */}
                    <div className="flex flex-wrap items-center gap-1.5 w-full sm:w-auto">
                        {[
                            { key: 'all', label: 'All Branches' },
                            { key: 'pending_approval', label: (stats.pending_approvals ?? 0) > 0 ? `Pending Review (${stats.pending_approvals})` : 'Pending Review' },
                            { key: 'active', label: 'Active' },
                            { key: 'expiring_soon', label: 'Expiring Soon' },
                            { key: 'overdue', label: 'Overdue' },
                            { key: 'suspended', label: 'Suspended' },
                            { key: 'lifetime', label: 'Lifetime' },
                        ].map((tab) => {
                            const isSelected = statusFilter === tab.key;
                            return (
                                <Button
                                    key={tab.key}
                                    type="button"
                                    size="sm"
                                    variant={isSelected ? 'default' : 'outline'}
                                    onClick={() => handleFilterChange(tab.key)}
                                    className={`text-xs h-8 rounded-lg ${
                                        isSelected
                                            ? 'font-bold shadow-xs bg-primary text-primary-foreground'
                                            : 'border-slate-200 dark:border-slate-800 bg-transparent text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {tab.label}
                                </Button>
                            );
                        })}
                    </div>

                    {/* Search Field */}
                    <div className="relative w-full sm:w-72">
                        <Search className="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Search branch name, phone, email..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9 text-xs h-9 rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Branches Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table className="w-full text-left text-xs">
                        <thead className="border-b border-slate-200 dark:border-slate-800 bg-slate-100/70 dark:bg-slate-900/60 font-semibold text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3.5">Branch Client</th>
                                <th className="px-4 py-3.5">Plan / Cycle</th>
                                <th className="px-4 py-3.5">Fee</th>
                                <th className="px-4 py-3.5">Subscription Status</th>
                                <th className="px-4 py-3.5">Expiration & Overdue</th>
                                <th className="px-4 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800 bg-card">
                            {branches.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="p-12 text-center text-muted-foreground text-xs">
                                        No branch client records match your search or filter criteria.
                                    </td>
                                </tr>
                            ) : (
                                branches.map((branch) => {
                                    const sub = branch.subscription || {};
                                    const isMain = branch.is_main_branch;

                                    return (
                                        <tr key={branch.id} className="hover:bg-muted/30 transition-colors">
                                            {/* Branch Details */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex flex-col gap-0.5">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-foreground text-sm">
                                                            {branch.name}
                                                        </span>
                                                        {isMain ? (
                                                            <Badge className="bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 text-[0.65rem] px-1.5 py-0 border-purple-200 dark:border-purple-900">
                                                                Main Central
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="text-[0.65rem] px-1.5 py-0 border-slate-300 dark:border-slate-700 font-mono">
                                                                ID: #{branch.id}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-[11px] text-muted-foreground flex items-center gap-3 mt-0.5">
                                                        {branch.phone && (
                                                            <span className="flex items-center gap-1">
                                                                <Phone className="size-3" /> {branch.phone}
                                                            </span>
                                                        )}
                                                        {branch.address && (
                                                            <span className="truncate max-w-[200px]" title={branch.address}>
                                                                {branch.address}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Plan & Cycle */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {isMain ? (
                                                    <Badge className="bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border-none font-semibold">
                                                        SuperAdmin Central
                                                    </Badge>
                                                ) : (
                                                    <div className="space-y-0.5">
                                                        <Badge variant="outline" className="font-semibold text-xs border-blue-200 dark:border-blue-900 bg-blue-50/50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300">
                                                            {sub.plan_label || sub.plan || 'Standard'}
                                                        </Badge>
                                                        {sub.cycle_days && (
                                                            <p className="text-[10px] text-muted-foreground">
                                                                {sub.cycle_days} Days Recurring
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Fee */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {isMain ? (
                                                    <span className="text-muted-foreground font-semibold">—</span>
                                                ) : (
                                                    <div className="font-extrabold text-foreground text-sm tabular-nums">
                                                        ৳{Number(sub.fee || 0).toFixed(2)}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Status Badge */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {isMain ? (
                                                    <Badge className="bg-emerald-600 text-white border-none font-bold">
                                                        Lifetime Active
                                                    </Badge>
                                                ) : sub.status === 'lifetime' ? (
                                                    <Badge className="bg-purple-600 text-white border-none font-bold">
                                                        Lifetime Active
                                                    </Badge>
                                                ) : sub.has_pending_payment || branch.latest_payment?.status === 'pending' ? (
                                                    <div className="space-y-0.5">
                                                        <Badge className="bg-amber-500 text-white font-bold border-none animate-pulse">
                                                            Pending Review
                                                        </Badge>
                                                        <p className="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">
                                                            Receipt submitted
                                                        </p>
                                                    </div>
                                                ) : sub.is_suspended ? (
                                                    <Badge className="bg-rose-600 text-white font-bold border-none">
                                                        Suspended
                                                    </Badge>
                                                ) : sub.is_overdue ? (
                                                    <div className="space-y-0.5">
                                                        <Badge className="bg-rose-600 text-white font-bold border-none">
                                                            Overdue ({sub.overdue_days}d)
                                                        </Badge>
                                                        {sub.is_in_grace_period && (
                                                            <p className="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">
                                                                Grace: {sub.grace_days_remaining}d left
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : sub.is_expiring_soon ? (
                                                    <Badge className="bg-amber-500 text-white font-bold border-none">
                                                        Expiring in {sub.days_remaining}d
                                                    </Badge>
                                                ) : (
                                                    <Badge className="bg-emerald-600 text-white font-bold border-none">
                                                        Active ({sub.days_remaining}d left)
                                                    </Badge>
                                                )}
                                            </td>

                                            {/* Expiry & Overdue Breakdown */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {isMain || sub.status === 'lifetime' ? (
                                                    <span className="text-muted-foreground text-[11px]">No Expiration</span>
                                                ) : (
                                                    <div className="space-y-0.5">
                                                        <div className="flex items-center gap-1.5 font-medium text-foreground tabular-nums">
                                                            <Calendar className="size-3.5 text-muted-foreground" />
                                                            <span>Expires: {sub.expires_at || 'Not set'}</span>
                                                        </div>
                                                        {sub.is_overdue && sub.pending_bills_count > 0 && (
                                                            <div className="text-[11px] font-extrabold text-rose-600 dark:text-rose-400 tabular-nums">
                                                                {sub.pending_bills_count} Unpaid Bill(s) (৳{Number(sub.total_overdue_fee || 0).toFixed(2)})
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Actions */}
                                            <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                                {!isMain ? (
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {sub.has_pending_payment || branch.latest_payment?.status === 'pending' ? (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="h-7 px-2.5 text-xs bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg shadow-xs ring-2 ring-amber-400/40"
                                                                onClick={() => openRenew(branch)}
                                                            >
                                                                <Eye className="size-3 mr-1" /> Review & Approve
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="h-7 px-2.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-xs"
                                                                onClick={() => openRenew(branch)}
                                                            >
                                                                <RefreshCw className="size-3 mr-1" /> Renew
                                                            </Button>
                                                        )}

                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 px-2 text-xs border-slate-300 dark:border-slate-700 text-foreground rounded-lg"
                                                            onClick={() => openEdit(branch)}
                                                        >
                                                            <Sliders className="size-3 mr-1" /> Config
                                                        </Button>

                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 px-2 text-xs border-slate-300 dark:border-slate-700 text-muted-foreground hover:text-foreground rounded-lg"
                                                            onClick={() => openHistory(branch)}
                                                            title="Payment & Receipt History"
                                                        >
                                                            <History className="size-3" />
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground italic">Master Branch</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modals */}
            {selectedBranch && (
                <>
                    <RenewSubscriptionDialog
                        open={renewModalOpen}
                        onOpenChange={setRenewModalOpen}
                        branch={selectedBranch}
                        paymentMethods={paymentMethods}
                        submitUrl={route('branch-clients.renew', selectedBranch.id)}
                    />

                    <EditBranchSubscriptionDialog
                        open={editModalOpen}
                        onOpenChange={setEditModalOpen}
                        branch={selectedBranch}
                        billingCycles={billingCycles}
                        overdueActions={overdueActions}
                        submitUrl={route('branch-clients.update', selectedBranch.id)}
                    />

                    <PaymentHistoryDialog
                        open={historyModalOpen}
                        onOpenChange={setHistoryModalOpen}
                        branch={selectedBranch}
                        fetchUrl={route('branch-clients.payments', selectedBranch.id)}
                    />
                </>
            )}
        </div>
    );
}
