import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    BookOpen,
    Building2,
    CalendarDays,
    ChevronDown,
    CircleDollarSign,
    HandCoins,
    Receipt,
    ReceiptText,
    User,
    Wallet,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

const sections = [
    {
        key: 'sales',
        title: 'Sales',
        icon: CircleDollarSign,
        headerClass: 'bg-emerald-600',
        bodyClass: 'from-emerald-50/90 to-white dark:from-emerald-950/30 dark:to-card',
        accentClass: 'text-emerald-700 dark:text-emerald-300',
        ringClass: 'ring-emerald-500/20',
        rows: (s) => [
            { label: 'Invoices', value: s.sales?.count ?? 0, plain: true },
            { label: 'Gross', value: <MoneyCell value={s.sales?.gross} /> },
            { label: 'Collected', value: <MoneyCell value={s.sales?.paid} /> },
            { label: 'Due', value: <MoneyCell value={s.sales?.due} />, highlight: true },
        ],
    },
    {
        key: 'purchases',
        title: 'Purchases',
        icon: HandCoins,
        headerClass: 'bg-blue-600',
        bodyClass: 'from-blue-50/90 to-white dark:from-blue-950/30 dark:to-card',
        accentClass: 'text-blue-700 dark:text-blue-300',
        ringClass: 'ring-blue-500/20',
        rows: (s) => [
            { label: 'Orders', value: s.purchases?.count ?? 0, plain: true },
            { label: 'Gross', value: <MoneyCell value={s.purchases?.gross} /> },
            { label: 'Paid', value: <MoneyCell value={s.purchases?.paid} /> },
            { label: 'Due', value: <MoneyCell value={s.purchases?.due} />, highlight: true },
        ],
    },
    {
        key: 'supplier_payments',
        title: 'Supplier Payments',
        icon: Wallet,
        headerClass: 'bg-violet-600',
        bodyClass: 'from-violet-50/90 to-white dark:from-violet-950/30 dark:to-card',
        accentClass: 'text-violet-700 dark:text-violet-300',
        ringClass: 'ring-violet-500/20',
        rows: (s) => [
            { label: 'Payments', value: s.supplier_payments?.count ?? 0, plain: true },
            { label: 'Total paid', value: <MoneyCell value={s.supplier_payments?.amount} />, highlight: true },
        ],
    },
    {
        key: 'customer_collections',
        title: 'Customer Collections',
        icon: HandCoins,
        headerClass: 'bg-teal-600',
        bodyClass: 'from-teal-50/90 to-white dark:from-teal-950/30 dark:to-card',
        accentClass: 'text-teal-700 dark:text-teal-300',
        ringClass: 'ring-teal-500/20',
        rows: (s) => [
            { label: 'Collections', value: s.customer_collections?.count ?? 0, plain: true },
            { label: 'Total collected', value: <MoneyCell value={s.customer_collections?.amount} />, highlight: true },
        ],
    },
    {
        key: 'expenses',
        title: 'Expenses',
        icon: ReceiptText,
        headerClass: 'bg-rose-600',
        bodyClass: 'from-rose-50/90 to-white dark:from-rose-950/30 dark:to-card',
        accentClass: 'text-rose-700 dark:text-rose-300',
        ringClass: 'ring-rose-500/20',
        rows: (s) => [
            { label: 'Vouchers', value: s.expenses?.count ?? 0, plain: true },
            { label: 'Total expense', value: <MoneyCell value={s.expenses?.amount} />, highlight: true },
        ],
    },
    {
        key: 'sale_returns',
        title: 'Sale Returns',
        icon: ArrowLeftRight,
        headerClass: 'bg-amber-600',
        bodyClass: 'from-amber-50/90 to-white dark:from-amber-950/30 dark:to-card',
        accentClass: 'text-amber-800 dark:text-amber-300',
        ringClass: 'ring-amber-500/20',
        rows: (s) => [
            { label: 'Returns', value: s.sale_returns?.count ?? 0, plain: true },
            { label: 'Amount', value: <MoneyCell value={s.sale_returns?.amount} />, highlight: true },
        ],
    },
    {
        key: 'damages',
        title: 'Damage',
        icon: AlertTriangle,
        headerClass: 'bg-red-600',
        bodyClass: 'from-red-50/90 to-white dark:from-red-950/30 dark:to-card',
        accentClass: 'text-red-700 dark:text-red-300',
        ringClass: 'ring-red-500/20',
        rows: (s) => [{ label: 'Records', value: s.damages?.count ?? 0, plain: true, highlight: true }],
    },
    {
        key: 'vouchers',
        title: 'Vouchers',
        icon: Receipt,
        headerClass: 'bg-indigo-600',
        bodyClass: 'from-indigo-50/90 to-white dark:from-indigo-950/30 dark:to-card',
        accentClass: 'text-indigo-700 dark:text-indigo-300',
        ringClass: 'ring-indigo-500/20',
        rows: (s) => [
            {
                label: 'Income',
                value: (
                    <>
                        {s.vouchers?.income?.count ?? 0} · <MoneyCell value={s.vouchers?.income?.amount} />
                    </>
                ),
            },
            { label: 'Journal', value: s.vouchers?.journal?.count ?? 0, plain: true },
            { label: 'Contra', value: s.vouchers?.contra?.count ?? 0, plain: true },
        ],
    },
    {
        key: 'accounting',
        title: 'Accounting',
        icon: BookOpen,
        headerClass: 'bg-cyan-700',
        bodyClass: 'from-cyan-50/90 to-white dark:from-cyan-950/30 dark:to-card',
        accentClass: 'text-cyan-800 dark:text-cyan-300',
        ringClass: 'ring-cyan-500/20',
        rows: (s) => [
            { label: 'Transactions', value: s.transactions?.count ?? 0, plain: true, highlight: true },
        ],
    },
];

function SummaryStatCard({ section, summary }) {
    const Icon = section.icon;
    const rows = section.rows(summary);

    return (
        <div
            className={[
                'overflow-hidden rounded-lg border bg-card shadow-sm ring-1',
                section.ringClass,
            ].join(' ')}
        >
            <div className={['flex items-center gap-2.5 px-4 py-2.5', section.headerClass].join(' ')}>
                <div className="flex size-7 items-center justify-center rounded-md bg-white/20">
                    <Icon className="size-4 text-white" />
                </div>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-white">{section.title}</h3>
            </div>
            <div className={['space-y-0 bg-gradient-to-b p-3', section.bodyClass].join(' ')}>
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className={[
                            'flex items-center justify-between border-b border-dashed border-black/5 py-2.5 text-sm last:border-0 dark:border-white/10',
                            row.highlight ? 'font-semibold' : '',
                        ].join(' ')}
                    >
                        <span className="text-muted-foreground">{row.label}</span>
                        <span className={row.highlight ? section.accentClass : 'font-medium'}>{row.value}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function KpiTile({ label, value, sub, className }) {
    return (
        <div className={['rounded-lg border px-4 py-3 shadow-sm', className].join(' ')}>
            <p className="text-[10px] font-semibold uppercase tracking-wider opacity-80">{label}</p>
            <p className="mt-1 text-xl font-bold">{value}</p>
            {sub && <p className="mt-0.5 text-xs opacity-70">{sub}</p>}
        </div>
    );
}

function TransactionItems({ title, items = [], type, accentClass }) {
    if (!items.length) {
        return null;
    }

    const showRoute = type === 'sale' ? 'inventory.sell.show' : 'inventory.purchase.show';

    return (
        <div className="min-w-0">
            <p className={`mb-1.5 text-[10px] font-semibold uppercase tracking-widest ${accentClass}`}>{title}</p>
            <div className="space-y-1">
                {items.map((item) => (
                    <div
                        key={`${type}-${item.id}`}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-dashed border-black/10 bg-white/70 px-2.5 py-1.5 text-xs dark:border-white/10 dark:bg-slate-950/40"
                    >
                        <Link
                            href={route(showRoute, item.id)}
                            className="font-mono font-medium text-blue-700 hover:underline dark:text-blue-300"
                        >
                            {item.reference}
                        </Link>
                        <div className="flex flex-wrap items-center gap-3 font-mono tabular-nums">
                            <span>
                                Gross <MoneyCell value={item.gross} />
                            </span>
                            <span>
                                Paid <MoneyCell value={item.paid} />
                            </span>
                            {(item.due ?? 0) > 0 ? (
                                <span className="text-amber-700 dark:text-amber-400">
                                    Due <MoneyCell value={item.due} />
                                </span>
                            ) : null}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function StaffBreakdownRow({ row }) {
    const [expanded, setExpanded] = useState(true);
    const salesItems = row.sales_items ?? [];
    const purchaseItems = row.purchases_items ?? [];
    const hasDetails = salesItems.length > 0 || purchaseItems.length > 0;
    const rowKey = `${row.branch_id}-${row.user_id}`;

    return (
        <>
            <tr className="border-b last:border-b-0">
                <td className="px-4 py-2.5 font-medium">
                    <div className="flex items-center gap-2">
                        {hasDetails ? (
                            <button
                                type="button"
                                onClick={() => setExpanded((open) => !open)}
                                className="flex size-6 shrink-0 items-center justify-center rounded border border-black/10 bg-white/80 text-muted-foreground hover:bg-muted dark:border-white/10 dark:bg-slate-900/60"
                                aria-expanded={expanded}
                                aria-label={expanded ? 'Hide transactions' : 'Show transactions'}
                            >
                                <ChevronDown className={`size-3.5 transition-transform ${expanded ? 'rotate-180' : ''}`} />
                            </button>
                        ) : (
                            <span className="size-6 shrink-0" />
                        )}
                        <span>{row.branch_name}</span>
                    </div>
                </td>
                <td className="px-4 py-2.5">{row.user_name}</td>
                <td className="px-4 py-2.5 font-mono tabular-nums">{row.sales?.count ?? 0}</td>
                <td className="px-4 py-2.5 font-mono tabular-nums text-emerald-700 dark:text-emerald-400">
                    <MoneyCell value={row.sales?.gross} />
                </td>
                <td className="px-4 py-2.5 font-mono tabular-nums">
                    <MoneyCell value={row.sales?.paid} />
                </td>
                <td className="px-4 py-2.5 font-mono tabular-nums">{row.purchases?.count ?? 0}</td>
                <td className="px-4 py-2.5 font-mono tabular-nums text-blue-700 dark:text-blue-400">
                    <MoneyCell value={row.purchases?.gross} />
                </td>
                <td className="px-4 py-2.5 font-mono tabular-nums">
                    <MoneyCell value={row.purchases?.paid} />
                </td>
            </tr>
            {expanded && hasDetails ? (
                <tr key={`${rowKey}-details`} className="border-b bg-muted/10 last:border-b-0">
                    <td colSpan={8} className="px-4 py-3">
                        <div className="grid gap-4 lg:grid-cols-2">
                            <TransactionItems
                                title="Sales invoices"
                                items={salesItems}
                                type="sale"
                                accentClass="text-emerald-700 dark:text-emerald-300"
                            />
                            <TransactionItems
                                title="Purchase orders"
                                items={purchaseItems}
                                type="purchase"
                                accentClass="text-blue-700 dark:text-blue-300"
                            />
                        </div>
                    </td>
                </tr>
            ) : null}
        </>
    );
}

export default function DailySummaryReport({
    filters = {},
    summary = {},
    branches = [],
    users = [],
    isBranchScoped = false,
}) {
    const [date, setDate] = useState(filters.date ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [userId, setUserId] = useState(filters.user_id ? String(filters.user_id) : 'all');
    const s = summary;

    useLiveReportFilters(
        'report.daily-summary',
        {
            date,
            branch_id: isBranchScoped || branchId === 'all' ? '' : branchId,
            user_id: userId === 'all' ? '' : userId,
        },
        [date, branchId, userId, isBranchScoped],
    );

    const salesGross = parseFloat(s.sales?.gross ?? 0);
    const purchaseGross = parseFloat(s.purchases?.gross ?? 0);
    const staffBreakdown = s.staff_breakdown ?? [];
    const showBranchFilter = !isBranchScoped;
    const showUserFilter = !isBranchScoped;

    const userOptions = useMemo(() => {
        if (branchId === 'all') {
            return users;
        }

        return users.filter((user) => user.branch_id === null || String(user.branch_id) === branchId);
    }, [users, branchId]);

    const hasActiveFilters = Boolean(
        (branchId !== 'all' && branchId !== '') || (userId !== 'all' && userId !== '') || date,
    );

    function resetFilters() {
        setDate(filters.date ?? '');
        setBranchId('all');
        setUserId('all');
        router.get(route('report.daily-summary'), {}, { preserveState: true, replace: true });
    }

    function handleBranchChange(value) {
        setBranchId(value);
        setUserId('all');
    }

    return (
        <>
            <Head title="Daily Summary" />
            <ReportPage
                title="Daily Summary"
                description="Business totals for a single day."
                filterGridClassName={
                    showBranchFilter || showUserFilter ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-2 lg:grid-cols-3'
                }
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Report date" icon={CalendarDays}>
                            <ReportDateInput value={date} onChange={setDate} />
                        </ReportFilterField>
                        {showBranchFilter && (
                            <ReportFilterField label="Branch" icon={Building2}>
                                <ReportSelect
                                    value={branchId}
                                    onChange={handleBranchChange}
                                    options={[
                                        { value: 'all', label: 'All branches' },
                                        ...branches.map((branch) => ({
                                            value: String(branch.id),
                                            label: branch.label,
                                        })),
                                    ]}
                                />
                            </ReportFilterField>
                        )}
                        {showUserFilter && (
                            <ReportFilterField label="User" icon={User}>
                                <ReportSelect
                                    value={userId}
                                    onChange={setUserId}
                                    options={[
                                        { value: 'all', label: 'All users' },
                                        ...userOptions.map((user) => ({
                                            value: String(user.id),
                                            label: user.label,
                                        })),
                                    ]}
                                />
                            </ReportFilterField>
                        )}
                    </>
                }
            >
                <div className="mb-4 overflow-hidden rounded-lg border border-blue-950/15 bg-gradient-to-r from-blue-950 to-blue-800 px-5 py-4 text-white shadow-md">
                    <p className="text-xs font-medium uppercase tracking-wider text-white/60">Summary date</p>
                    <p className="mt-1 text-2xl font-bold tracking-tight">{formatBdDate(s.date ?? date)}</p>
                </div>

                <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        label="Sales gross"
                        value={<MoneyCell value={salesGross} />}
                        sub={`${s.sales?.count ?? 0} invoice(s)`}
                        className="border-emerald-200/80 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100"
                    />
                    <KpiTile
                        label="Purchase gross"
                        value={<MoneyCell value={purchaseGross} />}
                        sub={`${s.purchases?.count ?? 0} order(s)`}
                        className="border-blue-200/80 bg-blue-50 text-blue-900 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
                    />
                    <KpiTile
                        label="Supplier paid"
                        value={<MoneyCell value={s.supplier_payments?.amount} />}
                        sub={`${s.supplier_payments?.count ?? 0} payment(s)`}
                        className="border-violet-200/80 bg-violet-50 text-violet-900 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-100"
                    />
                    <KpiTile
                        label="Expense"
                        value={<MoneyCell value={s.expenses?.amount} />}
                        sub={`${s.expenses?.count ?? 0} voucher(s)`}
                        className="border-rose-200/80 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {sections.map((section) => (
                        <SummaryStatCard key={section.key} section={section} summary={s} />
                    ))}
                </div>

                {staffBreakdown.length > 0 ? (
                    <div className="mt-6 overflow-hidden rounded-lg border bg-card shadow-sm">
                        <div className="border-b bg-muted/40 px-4 py-3">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-blue-950 dark:text-blue-100">
                                Branch &amp; User Performance
                            </h3>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Sales and purchase totals grouped by branch and user. Expand a row to see each invoice amount.
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[960px] text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/20 text-left">
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Branch</th>
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">User</th>
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Sales</th>
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Sales gross</th>
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Sales collected</th>
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Purchases</th>
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Purchase gross</th>
                                        <th className="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-widest">Purchase paid</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {staffBreakdown.map((row) => (
                                        <StaffBreakdownRow key={`${row.branch_id}-${row.user_id}`} row={row} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}
            </ReportPage>
        </>
    );
}
