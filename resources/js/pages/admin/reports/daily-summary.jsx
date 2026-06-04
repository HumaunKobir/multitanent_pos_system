import { formatBdDate } from '@/lib/format-bd-date';
import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    BookOpen,
    CalendarDays,
    CircleDollarSign,
    HandCoins,
    Receipt,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';

import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportPage,
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
            {
                label: 'Expense',
                value: (
                    <>
                        {s.vouchers?.expense?.count ?? 0} · <MoneyCell value={s.vouchers?.expense?.amount} />
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

export default function DailySummaryReport({ filters = {}, summary = {} }) {
    const [date, setDate] = useState(filters.date ?? '');
    const s = summary;

    useLiveReportFilters('report.daily-summary', { date }, [date]);

    const salesGross = parseFloat(s.sales?.gross ?? 0);
    const purchaseGross = parseFloat(s.purchases?.gross ?? 0);

    return (
        <>
            <Head title="Daily Summary" />
            <ReportPage
                title="Daily Summary"
                description="Business totals for a single day."
                filterBar={
                    <ReportFilterField label="Report date" icon={CalendarDays} className="sm:col-span-2 lg:col-span-1">
                        <ReportDateInput value={date} onChange={setDate} />
                    </ReportFilterField>
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
                        label="Ledger entries"
                        value={s.transactions?.count ?? 0}
                        sub="Accounting transactions"
                        className="border-cyan-200/80 bg-cyan-50 text-cyan-900 dark:border-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-100"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {sections.map((section) => (
                        <SummaryStatCard key={section.key} section={section} summary={s} />
                    ))}
                </div>
            </ReportPage>
        </>
    );
}
