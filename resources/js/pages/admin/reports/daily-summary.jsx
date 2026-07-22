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
    Package,
    Receipt,
    ReceiptText,
    Undo2,
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
            ...((s.sales?.refund_due ?? 0) > 0
                ? [
                      {
                          label: 'Refund due',
                          value: <MoneyCell value={s.sales?.refund_due} />,
                          highlight: true,
                      },
                  ]
                : []),
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
            { label: 'Gross (after returns)', value: <MoneyCell value={s.purchases?.gross} /> },
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
            { label: 'Amount', value: <MoneyCell value={s.sale_returns?.amount} /> },
            { label: 'Refunded', value: <MoneyCell value={s.sale_returns?.paid} /> },
            { label: 'Refund due', value: <MoneyCell value={s.sale_returns?.due} />, highlight: true },
        ],
    },
    {
        key: 'purchase_returns',
        title: 'Purchase Returns',
        icon: Undo2,
        headerClass: 'bg-orange-600',
        bodyClass: 'from-orange-50/90 to-white dark:from-orange-950/30 dark:to-card',
        accentClass: 'text-orange-700 dark:text-orange-300',
        ringClass: 'ring-orange-500/20',
        rows: (s) => [
            { label: 'Returns', value: s.purchase_returns?.count ?? 0, plain: true },
            { label: 'Amount', value: <MoneyCell value={s.purchase_returns?.amount} /> },
            { label: 'Received', value: <MoneyCell value={s.purchase_returns?.paid} /> },
            { label: 'Due', value: <MoneyCell value={s.purchase_returns?.due} />, highlight: true },
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
        rows: (s) => [
            { label: 'Records', value: s.damages?.count ?? 0, plain: true },
            { label: 'Amount', value: <MoneyCell value={s.damages?.amount} />, highlight: true },
        ],
    },
    {
        key: 'initial_stock',
        title: 'Initial Stock',
        icon: Package,
        headerClass: 'bg-sky-600',
        bodyClass: 'from-sky-50/90 to-white dark:from-sky-950/30 dark:to-card',
        accentClass: 'text-sky-800 dark:text-sky-300',
        ringClass: 'ring-sky-500/20',
        rows: (s) => [
            { label: 'Settlements', value: s.initial_stock?.count ?? 0, plain: true },
            { label: 'Stock value', value: <MoneyCell value={s.initial_stock?.gross} /> },
            { label: 'Paid', value: <MoneyCell value={s.initial_stock?.paid} /> },
            { label: 'Supplier due', value: <MoneyCell value={s.initial_stock?.due} />, highlight: true },
        ],
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

const sectionByKey = Object.fromEntries(sections.map((section) => [section.key, section]));

const salesGroupKeys = ['sales', 'sale_returns'];
const purchaseGroupKeys = ['purchases', 'purchase_returns', 'damages', 'initial_stock'];
const groupedKeys = new Set([...salesGroupKeys, ...purchaseGroupKeys]);

const salesGroup = salesGroupKeys.map((key) => sectionByKey[key]);
const purchaseGroup = purchaseGroupKeys.map((key) => sectionByKey[key]);
const otherSections = sections.filter((section) => !groupedKeys.has(section.key));

const recordModules = [
    { key: 'sales', title: 'Sales', icon: CircleDollarSign, showRoute: 'inventory.sell.show', headerClass: 'bg-emerald-600', accentClass: 'text-emerald-700 dark:text-emerald-300', showPaid: true },
    { key: 'sale_returns', title: 'Sale Returns', icon: ArrowLeftRight, showRoute: 'inventory.sale-return.show', headerClass: 'bg-amber-600', accentClass: 'text-amber-700 dark:text-amber-300', showPaid: true },
    { key: 'product_exchanges', title: 'Product Exchanges', icon: ArrowLeftRight, showRoute: 'inventory.product-exchange.show', headerClass: 'bg-fuchsia-600', accentClass: 'text-fuchsia-700 dark:text-fuchsia-300', showPaid: true },
    { key: 'purchases', title: 'Purchases', icon: HandCoins, showRoute: 'inventory.purchase.show', headerClass: 'bg-blue-600', accentClass: 'text-blue-700 dark:text-blue-300', showPaid: true },
    { key: 'purchase_returns', title: 'Purchase Returns', icon: Undo2, showRoute: 'inventory.purchase-return.show', headerClass: 'bg-orange-600', accentClass: 'text-orange-700 dark:text-orange-300', showPaid: true },
    { key: 'damages', title: 'Damage', icon: AlertTriangle, showRoute: 'inventory.damage.show', headerClass: 'bg-red-600', accentClass: 'text-red-700 dark:text-red-300', showPaid: false },
    { key: 'supplier_payments', title: 'Supplier Payments', icon: Wallet, showRoute: null, indexRoute: 'party.supplier-payment.index', headerClass: 'bg-violet-600', accentClass: 'text-violet-700 dark:text-violet-300', showPaid: false },
    { key: 'customer_collections', title: 'Customer Collections', icon: HandCoins, showRoute: null, indexRoute: 'party.customer-due-collection.index', headerClass: 'bg-teal-600', accentClass: 'text-teal-700 dark:text-teal-300', showPaid: false },
    { key: 'expenses', title: 'Expenses', icon: ReceiptText, showRoute: 'accounts.vouchers.show', headerClass: 'bg-rose-600', accentClass: 'text-rose-700 dark:text-rose-300', showPaid: false },
    { key: 'vouchers', title: 'Vouchers', icon: Receipt, showRoute: 'accounts.vouchers.show', headerClass: 'bg-indigo-600', accentClass: 'text-indigo-700 dark:text-indigo-300', showPaid: false },
];

function recordHref(module, item) {
    if (module.showRoute) {
        return route(module.showRoute, item.id);
    }

    if (module.indexRoute && item.reference) {
        return route(module.indexRoute, { query: { search: item.reference } });
    }

    return null;
}

function DayRecordCard({ module, items }) {
    const [expanded, setExpanded] = useState(false);
    const Icon = module.icon;
    const total = items.reduce((sum, item) => sum + parseFloat(item.gross ?? 0), 0);

    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
            <button
                type="button"
                onClick={() => setExpanded((open) => !open)}
                className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/30"
                aria-expanded={expanded}
            >
                <div className={['flex size-8 shrink-0 items-center justify-center rounded-md', module.headerClass].join(' ')}>
                    <Icon className="size-4 text-white" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">{module.title}</p>
                    <p className="text-xs text-muted-foreground">
                        {items.length} record(s) · <MoneyCell value={total} />
                    </p>
                </div>
                <ChevronDown className={`size-4 shrink-0 text-muted-foreground transition-transform ${expanded ? 'rotate-180' : ''}`} />
            </button>
            {expanded ? (
                <div className="space-y-1 border-t bg-muted/10 px-3 py-2">
                    {items.map((item) => (
                        <div
                            key={`${module.key}-${item.id}`}
                            className="flex flex-col gap-1 rounded-md border border-dashed border-black/10 bg-white/70 px-2.5 py-1.5 text-xs sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2 dark:border-white/10 dark:bg-slate-950/40"
                        >
                            <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
                                {recordHref(module, item) ? (
                                    <Link
                                        href={recordHref(module, item)}
                                        className="font-mono font-medium text-blue-700 hover:underline dark:text-blue-300"
                                    >
                                        {item.reference}
                                    </Link>
                                ) : (
                                    <span className="font-mono font-medium">{item.reference}</span>
                                )}
                                {item.party ? <span className="min-w-0 truncate text-muted-foreground">{item.party}</span> : null}
                            </div>
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 font-mono tabular-nums">
                                <span className={module.accentClass}>
                                    <MoneyCell value={item.gross} />
                                </span>
                                {module.showPaid ? (
                                    <span>
                                        Paid <MoneyCell value={item.paid} />
                                    </span>
                                ) : null}
                                {module.showPaid && (item.due ?? 0) > 0 ? (
                                    <span className="text-amber-700 dark:text-amber-400">
                                        Due <MoneyCell value={item.due} />
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

function SummaryStatCard({ section, summary }) {
    const Icon = section.icon;
    const rows = section.rows(summary);

    return (
        <div
            className={[
                'min-w-0 overflow-hidden rounded-lg border bg-card shadow-sm ring-1',
                section.ringClass,
            ].join(' ')}
        >
            <div className={['flex min-w-0 items-center gap-2.5 px-4 py-2.5', section.headerClass].join(' ')}>
                <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-white/20">
                    <Icon className="size-4 text-white" />
                </div>
                <h3 className="min-w-0 text-sm font-semibold uppercase leading-tight tracking-wide text-white">{section.title}</h3>
            </div>
            <div className={['space-y-0 bg-gradient-to-b p-3', section.bodyClass].join(' ')}>
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className={[
                            'flex items-start justify-between gap-3 border-b border-dashed border-black/5 py-2.5 text-sm last:border-0 dark:border-white/10',
                            row.highlight ? 'font-semibold' : '',
                        ].join(' ')}
                    >
                        <span className="shrink-0 text-muted-foreground">{row.label}</span>
                        <span className={['min-w-0 break-words text-right', row.highlight ? section.accentClass : 'font-medium'].join(' ')}>{row.value}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function KpiTile({ label, value, sub, className }) {
    return (
        <div className={['min-w-0 rounded-lg border px-4 py-3 shadow-sm', className].join(' ')}>
            <p className="text-[10px] font-semibold uppercase tracking-wider opacity-80">{label}</p>
            <p className="mt-1 break-words text-lg font-bold sm:text-xl">{value}</p>
            {sub && <p className="mt-0.5 text-xs opacity-70">{sub}</p>}
        </div>
    );
}

function TransactionItems({
    title,
    items = [],
    showRoute,
    accentClass,
    amountLabel = 'Gross',
    showPaid = true,
}) {
    if (!items.length) {
        return null;
    }

    return (
        <div className="min-w-0">
            <p className={`mb-1.5 text-[10px] font-semibold uppercase tracking-widest ${accentClass}`}>{title}</p>
            <div className="space-y-1">
                {items.map((item) => (
                    <div
                        key={`${showRoute}-${item.id}`}
                        className="flex flex-col gap-1.5 rounded-md border border-dashed border-black/10 bg-white/70 px-2.5 py-1.5 text-xs sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2 dark:border-white/10 dark:bg-slate-950/40"
                    >
                        <Link
                            href={route(showRoute, item.id)}
                            className="font-mono font-medium text-blue-700 hover:underline dark:text-blue-300"
                        >
                            {item.reference}
                        </Link>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 font-mono tabular-nums">
                            <span>
                                {amountLabel} <MoneyCell value={item.gross} />
                            </span>
                            {showPaid ? (
                                <span>
                                    Paid <MoneyCell value={item.paid} />
                                </span>
                            ) : null}
                            {showPaid && (item.due ?? 0) > 0 ? (
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

function ModuleCell({ count, amount, accentClass, borderClass = '' }) {
    return (
        <td className={`px-3 py-2.5 font-mono tabular-nums ${borderClass}`}>
            <div className="leading-tight">
                <div className="text-[11px] text-muted-foreground">{count ?? 0}</div>
                <div className={accentClass}>
                    <MoneyCell value={amount} />
                </div>
            </div>
        </td>
    );
}

function AmountCell({ amount, accentClass, borderClass = '' }) {
    return (
        <td className={`px-3 py-2.5 font-mono tabular-nums ${accentClass} ${borderClass}`}>
            <MoneyCell value={amount} />
        </td>
    );
}

function useStaffBreakdownItems(row) {
    const salesItems = row.sales_items ?? [];
    const saleReturnItems = row.sale_returns_items ?? [];
    const purchaseItems = row.purchases_items ?? [];
    const purchaseReturnItems = row.purchase_returns_items ?? [];
    const damageItems = row.damages_items ?? [];
    const hasDetails =
        salesItems.length > 0
        || saleReturnItems.length > 0
        || purchaseItems.length > 0
        || purchaseReturnItems.length > 0
        || damageItems.length > 0;

    return {
        salesItems,
        saleReturnItems,
        purchaseItems,
        purchaseReturnItems,
        damageItems,
        hasDetails,
    };
}

function ExpandToggle({ expanded, onToggle, hasDetails }) {
    if (!hasDetails) {
        return <span className="size-6 shrink-0" aria-hidden />;
    }

    return (
        <button
            type="button"
            onClick={onToggle}
            className="flex size-6 shrink-0 items-center justify-center rounded border border-black/10 bg-white/80 text-muted-foreground hover:bg-muted dark:border-white/10 dark:bg-slate-900/60"
            aria-expanded={expanded}
            aria-label={expanded ? 'Hide transactions' : 'Show transactions'}
        >
            <ChevronDown className={`size-3.5 transition-transform ${expanded ? 'rotate-180' : ''}`} />
        </button>
    );
}

function StaffBreakdownMetric({ label, count, amount, accentClass }) {
    return (
        <div className="min-w-0 rounded-md border border-dashed border-black/10 bg-muted/20 px-2.5 py-2 dark:border-white/10">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
            <p className={`mt-0.5 break-words font-mono text-sm tabular-nums ${accentClass}`}>
                <span className="text-[11px] text-muted-foreground">{count ?? 0}</span>
                {' · '}
                <MoneyCell value={amount} />
            </p>
        </div>
    );
}

function StaffBreakdownAmountMetric({ label, amount, accentClass }) {
    return (
        <div className="min-w-0 rounded-md border border-dashed border-black/10 bg-muted/20 px-2.5 py-2 dark:border-white/10">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
            <p className={`mt-0.5 break-words font-mono text-sm tabular-nums ${accentClass}`}>
                <MoneyCell value={amount} />
            </p>
        </div>
    );
}

function StaffBreakdownDetails({
    salesItems,
    saleReturnItems,
    purchaseItems,
    purchaseReturnItems,
    damageItems,
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <TransactionItems
                title="Sales invoices"
                items={salesItems}
                showRoute="inventory.sell.show"
                accentClass="text-emerald-700 dark:text-emerald-300"
            />
            <TransactionItems
                title="Sale returns"
                items={saleReturnItems}
                showRoute="inventory.sale-return.show"
                accentClass="text-amber-700 dark:text-amber-300"
            />
            <TransactionItems
                title="Purchase orders"
                items={purchaseItems}
                showRoute="inventory.purchase.show"
                accentClass="text-blue-700 dark:text-blue-300"
            />
            <TransactionItems
                title="Purchase returns"
                items={purchaseReturnItems}
                showRoute="inventory.purchase-return.show"
                accentClass="text-orange-700 dark:text-orange-300"
            />
            <TransactionItems
                title="Damage records"
                items={damageItems}
                showRoute="inventory.damage.show"
                accentClass="text-red-700 dark:text-red-300"
                amountLabel="Amount"
                showPaid={false}
            />
        </div>
    );
}

function StaffBreakdownCard({ row }) {
    const [expanded, setExpanded] = useState(true);
    const {
        salesItems,
        saleReturnItems,
        purchaseItems,
        purchaseReturnItems,
        damageItems,
        hasDetails,
    } = useStaffBreakdownItems(row);

    return (
        <div className="rounded-lg border bg-card p-4 shadow-sm">
            <div className="flex items-start gap-2">
                <ExpandToggle
                    expanded={expanded}
                    onToggle={() => setExpanded((open) => !open)}
                    hasDetails={hasDetails}
                />
                <div className="min-w-0 flex-1">
                    <p className="font-semibold">{row.user_name}</p>
                    <p className="text-xs text-muted-foreground">{row.branch_name}</p>
                </div>
            </div>

            <div className="mt-3 space-y-3">
                <div>
                    <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-emerald-700 dark:text-emerald-300">
                        Sales related
                    </p>
                    <div className="grid grid-cols-1 gap-2 min-[400px]:grid-cols-2">
                        <StaffBreakdownMetric
                            label="Sales"
                            count={row.sales?.count}
                            amount={row.sales?.gross}
                            accentClass="text-emerald-700 dark:text-emerald-400"
                        />
                        <StaffBreakdownMetric
                            label="Sale returns"
                            count={row.sale_returns?.count}
                            amount={row.sale_returns?.amount}
                            accentClass="text-amber-700 dark:text-amber-400"
                        />
                        <StaffBreakdownAmountMetric
                            label="Collected"
                            amount={row.customer_collections?.amount}
                            accentClass="text-teal-700 dark:text-teal-400"
                        />
                    </div>
                </div>

                <div>
                    <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-blue-700 dark:text-blue-300">
                        Purchase related
                    </p>
                    <div className="grid grid-cols-1 gap-2 min-[400px]:grid-cols-2">
                        <StaffBreakdownMetric
                            label="Purchases"
                            count={row.purchases?.count}
                            amount={row.purchases?.gross}
                            accentClass="text-blue-700 dark:text-blue-400"
                        />
                        <StaffBreakdownMetric
                            label="Purch. returns"
                            count={row.purchase_returns?.count}
                            amount={row.purchase_returns?.amount}
                            accentClass="text-orange-700 dark:text-orange-400"
                        />
                        <StaffBreakdownMetric
                            label="Damage"
                            count={row.damages?.count}
                            amount={row.damages?.amount}
                            accentClass="text-red-700 dark:text-red-400"
                        />
                        <StaffBreakdownAmountMetric
                            label="Supplier paid"
                            amount={row.supplier_payments?.amount}
                            accentClass="text-violet-700 dark:text-violet-400"
                        />
                    </div>
                </div>
            </div>

            {expanded && hasDetails ? (
                <div className="mt-4 border-t border-dashed border-black/10 pt-4 dark:border-white/10">
                    <StaffBreakdownDetails
                        salesItems={salesItems}
                        saleReturnItems={saleReturnItems}
                        purchaseItems={purchaseItems}
                        purchaseReturnItems={purchaseReturnItems}
                        damageItems={damageItems}
                    />
                </div>
            ) : null}
        </div>
    );
}

function StaffBreakdownRow({ row }) {
    const [expanded, setExpanded] = useState(true);
    const {
        salesItems,
        saleReturnItems,
        purchaseItems,
        purchaseReturnItems,
        damageItems,
        hasDetails,
    } = useStaffBreakdownItems(row);
    const rowKey = `${row.branch_id}-${row.user_id}`;

    return (
        <>
            <tr className="border-b last:border-b-0">
                <td className="px-4 py-2.5 font-medium">
                    <div className="flex items-center gap-2">
                        <ExpandToggle
                            expanded={expanded}
                            onToggle={() => setExpanded((open) => !open)}
                            hasDetails={hasDetails}
                        />
                        <span>{row.branch_name}</span>
                    </div>
                </td>
                <td className="px-4 py-2.5">{row.user_name}</td>

                {/* Sales-related modules */}
                <ModuleCell
                    count={row.sales?.count}
                    amount={row.sales?.gross}
                    accentClass="text-emerald-700 dark:text-emerald-400"
                    borderClass="border-l border-black/5 dark:border-white/10"
                />
                <ModuleCell count={row.sale_returns?.count} amount={row.sale_returns?.amount} accentClass="text-amber-700 dark:text-amber-400" />
                <AmountCell amount={row.customer_collections?.amount} accentClass="text-teal-700 dark:text-teal-400" />

                {/* Purchase-related modules */}
                <ModuleCell
                    count={row.purchases?.count}
                    amount={row.purchases?.gross}
                    accentClass="text-blue-700 dark:text-blue-400"
                    borderClass="border-l border-black/5 dark:border-white/10"
                />
                <ModuleCell count={row.purchase_returns?.count} amount={row.purchase_returns?.amount} accentClass="text-orange-700 dark:text-orange-400" />
                <ModuleCell count={row.damages?.count} amount={row.damages?.amount} accentClass="text-red-700 dark:text-red-400" />
                <AmountCell amount={row.supplier_payments?.amount} accentClass="text-violet-700 dark:text-violet-400" />
            </tr>
            {expanded && hasDetails ? (
                <tr key={`${rowKey}-details`} className="border-b bg-muted/10 last:border-b-0">
                    <td colSpan={9} className="px-4 py-3">
                        <StaffBreakdownDetails
                            salesItems={salesItems}
                            saleReturnItems={saleReturnItems}
                            purchaseItems={purchaseItems}
                            purchaseReturnItems={purchaseReturnItems}
                            damageItems={damageItems}
                        />
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

    const salesGross = parseFloat(s.sales?.gross ?? 0) - parseFloat(s.sale_returns?.amount ?? 0);
    const purchaseGross = parseFloat(s.purchases?.gross ?? 0);
    const staffBreakdown = s.staff_breakdown ?? [];
    const records = s.records ?? {};
    const populatedRecordModules = recordModules.filter((module) => (records[module.key] ?? []).length > 0);
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
                <div className="mb-4 overflow-hidden rounded-lg border border-blue-950/15 bg-gradient-to-r from-blue-950 to-blue-800 px-4 py-3 text-white shadow-md sm:px-5 sm:py-4">
                    <p className="text-xs font-medium uppercase tracking-wider text-white/60">Summary date</p>
                    <p className="mt-1 text-xl font-bold tracking-tight sm:text-2xl">{formatBdDate(s.date ?? date)}</p>
                </div>

                <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        label="Sales gross"
                        value={<MoneyCell value={salesGross} />}
                        sub={`After returns · ${s.sales?.count ?? 0} invoice(s)`}
                        className="border-emerald-200/80 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100"
                    />
                    <KpiTile
                        label="Purchase gross"
                        value={<MoneyCell value={purchaseGross} />}
                        sub={`After returns · ${s.purchases?.count ?? 0} order(s)`}
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

                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {salesGroup.map((section) => (
                            <SummaryStatCard key={section.key} section={section} summary={s} />
                        ))}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {purchaseGroup.map((section) => (
                            <SummaryStatCard key={section.key} section={section} summary={s} />
                        ))}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {otherSections.map((section) => (
                            <SummaryStatCard key={section.key} section={section} summary={s} />
                        ))}
                    </div>
                </div>

                <div className="mt-6 overflow-hidden rounded-lg border bg-card shadow-sm">
                    <div className="border-b bg-muted/40 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-blue-950 dark:text-blue-100">
                            Day Transactions
                        </h3>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Every record behind the totals above for {formatBdDate(s.date ?? date)}. Expand a module to see each
                            entry, and open a record from its reference.
                        </p>
                    </div>
                    {populatedRecordModules.length > 0 ? (
                        <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                            {populatedRecordModules.map((module) => (
                                <DayRecordCard key={module.key} module={module} items={records[module.key] ?? []} />
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Receipt className="size-5" />
                            </div>
                            <p className="text-sm font-medium">No transactions on this date</p>
                            <p className="max-w-md text-xs text-muted-foreground">
                                There is no sale, return, exchange, purchase, damage, payment, collection, expense or voucher
                                recorded for {formatBdDate(s.date ?? date)}.
                            </p>
                        </div>
                    )}
                </div>

                {!isBranchScoped && staffBreakdown.length === 0 ? (
                    <div className="mt-6 overflow-hidden rounded-lg border bg-card shadow-sm">
                        <div className="border-b bg-muted/40 px-4 py-3">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-blue-950 dark:text-blue-100">
                                Branch &amp; User Performance
                            </h3>
                        </div>
                        <div className="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Building2 className="size-5" />
                            </div>
                            {branchId === 'all' && userId === 'all' ? (
                                <>
                                    <p className="text-sm font-medium">Select a branch or user to view detailed lists</p>
                                    <p className="max-w-md text-xs text-muted-foreground">
                                        The branch &amp; user performance breakdown, with each invoice, return, exchange and
                                        damage record, appears once you pick a <span className="font-medium">Branch</span> or{' '}
                                        <span className="font-medium">User</span> from the filters above.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p className="text-sm font-medium">No transactions for the selected filter</p>
                                    <p className="max-w-md text-xs text-muted-foreground">
                                        There is no activity for the chosen branch/user on {formatBdDate(s.date ?? date)}. Try a
                                        different date or clear the filters.
                                    </p>
                                </>
                            )}
                        </div>
                    </div>
                ) : null}

                {staffBreakdown.length > 0 ? (
                    <div className="mt-6 overflow-hidden rounded-lg border bg-card shadow-sm">
                        <div className="border-b bg-muted/40 px-4 py-3">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-blue-950 dark:text-blue-100">
                                Branch &amp; User Performance
                            </h3>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Sales-related and purchase-related modules grouped by branch and user. Each module cell shows
                                count over amount. Expand a row to see each invoice amount.
                            </p>
                        </div>

                        <div className="space-y-3 p-3 xl:hidden">
                            {staffBreakdown.map((row) => (
                                <StaffBreakdownCard key={`${row.branch_id}-${row.user_id}`} row={row} />
                            ))}
                        </div>

                        <div className="hidden overflow-x-auto xl:block">
                            <table className="w-full min-w-[1100px] text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/20 text-left">
                                        <th className="px-4 py-2 text-[10px] font-semibold uppercase tracking-widest" rowSpan={2}>Branch</th>
                                        <th className="px-4 py-2 text-[10px] font-semibold uppercase tracking-widest" rowSpan={2}>User</th>
                                        <th
                                            className="border-l border-black/5 bg-emerald-50/60 px-3 py-2 text-center text-[10px] font-semibold uppercase tracking-widest text-emerald-800 dark:border-white/10 dark:bg-emerald-950/30 dark:text-emerald-300"
                                            colSpan={3}
                                        >
                                            Sales related
                                        </th>
                                        <th
                                            className="border-l border-black/5 bg-blue-50/60 px-3 py-2 text-center text-[10px] font-semibold uppercase tracking-widest text-blue-800 dark:border-white/10 dark:bg-blue-950/30 dark:text-blue-300"
                                            colSpan={4}
                                        >
                                            Purchase related
                                        </th>
                                    </tr>
                                    <tr className="border-b bg-muted/20 text-left">
                                        <th className="border-l border-black/5 px-3 py-2 text-[10px] font-semibold uppercase tracking-widest dark:border-white/10">Sales</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase tracking-widest">Sale returns</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase tracking-widest">Collected</th>
                                        <th className="border-l border-black/5 px-3 py-2 text-[10px] font-semibold uppercase tracking-widest dark:border-white/10">Purchases</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase tracking-widest">Purch. returns</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase tracking-widest">Damage</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase tracking-widest">Supplier paid</th>
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
