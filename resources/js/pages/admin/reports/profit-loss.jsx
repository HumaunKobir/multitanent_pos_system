import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { CalendarRange, ReceiptText } from 'lucide-react';
import { useState } from 'react';

import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

function sectionBySlug(sections, slug) {
    return (sections ?? []).find((section) => section.slug === slug) ?? { lines: [], total: 0 };
}

function percentOf(part, whole) {
    const amount = Number(part) || 0;
    const base = Number(whole) || 0;

    if (base <= 0) {
        return 0;
    }

    return Math.round((amount / base) * 1000) / 10;
}

function SummaryCard({ label, value, percent, className, labelClassName, valueClassName, showPercent = true }) {
    return (
        <div className={['rounded-lg border px-4 py-3 shadow-sm', className].join(' ')}>
            <p className={['text-[10px] font-semibold uppercase tracking-wider', labelClassName].join(' ')}>
                {label}
            </p>
            <div className="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p className={['text-xl font-bold', valueClassName].join(' ')}>
                    <MoneyCell value={value} />
                </p>
                {showPercent ? (
                    <p className={['text-sm font-semibold tabular-nums opacity-75', valueClassName].join(' ')}>
                        {Number(percent).toFixed(1)}%
                    </p>
                ) : null}
            </div>
        </div>
    );
}

function StatementLines({ lines, emptyLabel = 'No balances recorded.' }) {
    if (!lines?.length) {
        return (
            <tr>
                <td colSpan={3} className="px-4 py-2 text-sm text-muted-foreground">
                    {emptyLabel}
                </td>
            </tr>
        );
    }

    return lines.map((line) => (
        <tr key={`${line.code}-${line.name}`} className="border-b border-dashed border-black/5 dark:border-white/10">
            <td className="px-4 py-2 font-mono text-xs text-muted-foreground">{line.code}</td>
            <td className="py-2 pr-2">{line.name}</td>
            <td className="px-4 py-2 text-right font-medium">
                <MoneyCell value={line.amount} />
            </td>
        </tr>
    ));
}

function TotalRow({ label, value, prefix = '', emphasize = false, tone = 'default' }) {
    const toneClass =
        tone === 'profit'
            ? 'text-emerald-800 dark:text-emerald-300'
            : tone === 'loss'
              ? 'text-rose-800 dark:text-rose-300'
              : tone === 'muted'
                ? 'text-muted-foreground'
                : '';

    return (
        <tr className={emphasize ? 'bg-black/5 font-semibold dark:bg-white/8' : 'font-semibold'}>
            <td colSpan={2} className="px-4 py-2.5">
                {prefix ? <span className="mr-1 text-muted-foreground">{prefix}</span> : null}
                {label}
            </td>
            <td className={['px-4 py-2.5 text-right', toneClass].join(' ')}>
                <MoneyCell value={value} />
            </td>
        </tr>
    );
}

function SaasStatement({ report }) {
    const subscriptionIncome = sectionBySlug(report.sections, 'subscription_income');
    const operatingExpenses = sectionBySlug(report.sections, 'operating_expenses');
    const isProfit = (report.net_result ?? 0) >= 0;
    const incomeBase = Number(report.subscription_income) || 0;

    return (
        <>
            <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <SummaryCard
                    label="Subscription Income"
                    value={report.subscription_income}
                    percent={100}
                    showPercent={incomeBase > 0}
                    className="border-teal-200/80 bg-teal-50 dark:border-teal-800 dark:bg-teal-950/30"
                    labelClassName="text-teal-800/70 dark:text-teal-300/70"
                    valueClassName="text-teal-900 dark:text-teal-200"
                />
                <SummaryCard
                    label="Operating Expenses"
                    value={report.operating_expenses}
                    percent={percentOf(report.operating_expenses, incomeBase)}
                    showPercent={incomeBase > 0}
                    className="border-rose-200/80 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30"
                    labelClassName="text-rose-800/70 dark:text-rose-300/70"
                    valueClassName="text-rose-900 dark:text-rose-200"
                />
                <SummaryCard
                    label={report.result_label ?? 'Net Result'}
                    value={Math.abs(report.net_result ?? 0)}
                    percent={percentOf(report.net_result, incomeBase)}
                    showPercent={incomeBase > 0}
                    className={
                        isProfit
                            ? 'border-blue-200/80 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30'
                            : 'border-amber-200/80 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
                    }
                    labelClassName={
                        isProfit
                            ? 'text-blue-800/70 dark:text-blue-300/70'
                            : 'text-amber-800/70 dark:text-amber-300/70'
                    }
                    valueClassName={isProfit ? 'text-blue-900 dark:text-blue-200' : 'text-amber-900 dark:text-amber-200'}
                />
            </div>

            <div className="overflow-hidden rounded-lg border bg-card shadow-sm ring-1 ring-blue-950/10">
                <div className="bg-blue-950 px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-white">
                    SaaS Profit &amp; Loss Statement
                </div>
                <div className="bg-linear-to-b from-slate-50/80 to-white dark:from-slate-950/20 dark:to-card">
                    <table className="w-full text-sm">
                        <tbody>
                            <tr className="bg-teal-700/90 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    Subscription Income
                                </td>
                            </tr>
                            <StatementLines
                                lines={subscriptionIncome.lines}
                                emptyLabel="No subscription income recorded."
                            />
                            <TotalRow
                                label="Subscription Income"
                                value={report.subscription_income ?? subscriptionIncome.total}
                            />

                            <tr className="bg-slate-700 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    (−) Operating Expenses
                                </td>
                            </tr>
                            <StatementLines
                                lines={operatingExpenses.lines}
                                emptyLabel="No operating expenses recorded."
                            />
                            <TotalRow
                                label="Operating Expenses"
                                value={report.operating_expenses ?? operatingExpenses.total}
                                prefix="(−)"
                                tone="loss"
                            />

                            <TotalRow
                                label={report.result_label ?? 'Net Profit'}
                                value={Math.abs(report.net_result ?? 0)}
                                prefix="="
                                emphasize
                                tone={isProfit ? 'profit' : 'loss'}
                            />
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function BranchStatement({ report }) {
    const isProfit = (report.net_result ?? 0) >= 0;
    const salesBase = Number(report.sales_revenue) || 0;
    const salesRevenue = sectionBySlug(report.sections, 'sales_revenue');
    const salesReturns = sectionBySlug(report.sections, 'sales_returns');
    const salesDiscounts = sectionBySlug(report.sections, 'sales_discounts');
    const vatPayable = sectionBySlug(report.sections, 'vat_payable');
    const cogs = sectionBySlug(report.sections, 'cogs');
    const subscriptionExpense = sectionBySlug(report.sections, 'subscription_expense');
    const operatingExpenses = sectionBySlug(report.sections, 'operating_expenses');

    return (
        <>
            <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <SummaryCard
                    label="Net Sales"
                    value={report.net_sales}
                    percent={percentOf(report.net_sales, salesBase)}
                    className="border-emerald-200/80 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30"
                    labelClassName="text-emerald-800/70 dark:text-emerald-300/70"
                    valueClassName="text-emerald-800 dark:text-emerald-200"
                />
                <SummaryCard
                    label="Discount Applied"
                    value={report.sales_discounts}
                    percent={percentOf(report.sales_discounts, salesBase)}
                    className="border-violet-200/80 bg-violet-50 dark:border-violet-800 dark:bg-violet-950/30"
                    labelClassName="text-violet-800/70 dark:text-violet-300/70"
                    valueClassName="text-violet-900 dark:text-violet-200"
                />
                <SummaryCard
                    label="Net Taxes Payable"
                    value={report.vat_payable ?? report.net_vat_payable ?? report.output_vat}
                    percent={percentOf(report.vat_payable ?? report.net_vat_payable ?? report.output_vat, salesBase)}
                    className="border-sky-200/80 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30"
                    labelClassName="text-sky-800/70 dark:text-sky-300/70"
                    valueClassName="text-sky-900 dark:text-sky-200"
                />
                <SummaryCard
                    label="Gross Profit"
                    value={report.gross_profit}
                    percent={percentOf(report.gross_profit, salesBase)}
                    className="border-amber-200/80 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30"
                    labelClassName="text-amber-800/70 dark:text-amber-300/70"
                    valueClassName="text-amber-900 dark:text-amber-200"
                />
                <SummaryCard
                    label="Subscription Expense"
                    value={report.subscription_expense}
                    percent={percentOf(report.subscription_expense, salesBase)}
                    className="border-orange-200/80 bg-orange-50 dark:border-orange-800 dark:bg-orange-950/30"
                    labelClassName="text-orange-800/70 dark:text-orange-300/70"
                    valueClassName="text-orange-900 dark:text-orange-200"
                />
                <SummaryCard
                    label={report.result_label ?? 'Net Result'}
                    value={Math.abs(report.net_result ?? 0)}
                    percent={percentOf(report.net_result, salesBase)}
                    className={
                        isProfit
                            ? 'border-blue-200/80 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30'
                            : 'border-amber-200/80 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
                    }
                    labelClassName={
                        isProfit
                            ? 'text-blue-800/70 dark:text-blue-300/70'
                            : 'text-amber-800/70 dark:text-amber-300/70'
                    }
                    valueClassName={isProfit ? 'text-blue-900 dark:text-blue-200' : 'text-amber-900 dark:text-amber-200'}
                />
            </div>

            <div className="overflow-hidden rounded-lg border bg-card shadow-sm ring-1 ring-blue-950/10">
                <div className="bg-blue-950 px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-white">
                    Branch Profit &amp; Loss Statement
                </div>
                <div className="bg-linear-to-b from-slate-50/80 to-white dark:from-slate-950/20 dark:to-card">
                    <table className="w-full text-sm">
                        <tbody>
                            <tr className="bg-emerald-600/90 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    Sales Revenue
                                </td>
                            </tr>
                            <StatementLines lines={salesRevenue.lines} emptyLabel="No sales revenue recorded." />
                            <TotalRow label="Sales Revenue" value={report.sales_revenue ?? salesRevenue.total} />

                            <tr className="bg-rose-600/90 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    (−) Sales Return
                                </td>
                            </tr>
                            <StatementLines lines={salesReturns.lines} emptyLabel="No sales returns recorded." />
                            <TotalRow
                                label="Sales Returns"
                                value={report.sales_returns ?? salesReturns.total}
                                prefix="(−)"
                                tone="loss"
                            />

                            <tr className="bg-violet-700/90 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    (−) Discount Applied
                                </td>
                            </tr>
                            <StatementLines lines={salesDiscounts.lines} emptyLabel="No discounts recorded." />
                            <TotalRow
                                label="Discount Applied"
                                value={report.sales_discounts ?? salesDiscounts.total}
                                prefix="(−)"
                                tone="loss"
                            />

                            <TotalRow
                                label="Net Sales"
                                value={report.net_sales}
                                prefix="="
                                emphasize
                                tone="profit"
                            />

                            <tr className="bg-sky-700/90 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    Taxes Payable
                                </td>
                            </tr>
                            <StatementLines lines={vatPayable.lines} emptyLabel="No taxes payable activity in this period." />
                            <TotalRow
                                label="Net Taxes Payable"
                                value={report.vat_payable ?? report.net_vat_payable ?? report.output_vat ?? vatPayable.total}
                                emphasize
                                tone="muted"
                            />

                            <tr className="bg-amber-600/90 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    (−) Cost of Goods Sold (COGS)
                                </td>
                            </tr>
                            <StatementLines lines={cogs.lines} emptyLabel="No COGS recorded." />
                            <TotalRow label="Cost of Goods Sold (COGS)" value={report.cogs ?? cogs.total} prefix="(−)" tone="loss" />

                            <TotalRow
                                label="Gross Profit"
                                value={report.gross_profit}
                                prefix="="
                                emphasize
                                tone="profit"
                            />

                            <tr className="bg-orange-700/90 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    (−) Subscription Expense
                                </td>
                            </tr>
                            <StatementLines
                                lines={subscriptionExpense.lines}
                                emptyLabel="No subscription expense recorded."
                            />
                            <TotalRow
                                label="Subscription Expense"
                                value={report.subscription_expense ?? subscriptionExpense.total}
                                prefix="(−)"
                                tone="loss"
                            />

                            <tr className="bg-slate-700 text-white">
                                <td colSpan={3} className="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    (−) Operating Expenses
                                </td>
                            </tr>
                            <StatementLines
                                lines={operatingExpenses.lines}
                                emptyLabel="No operating expenses recorded."
                            />
                            <TotalRow
                                label="Operating Expenses"
                                value={report.operating_expenses ?? operatingExpenses.total}
                                prefix="(−)"
                                tone="loss"
                            />

                            <TotalRow
                                label={report.result_label ?? 'Net Profit'}
                                value={Math.abs(report.net_result ?? 0)}
                                prefix="="
                                emphasize
                                tone={isProfit ? 'profit' : 'loss'}
                            />
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

export default function ProfitLossReport({ filters = {}, panelVariant = 'branch', report = {} }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const variant = report.variant ?? panelVariant ?? 'branch';
    const isSaas = variant === 'saas';

    useLiveReportFilters('report.profit-loss', { date_from: dateFrom, date_to: dateTo }, [dateFrom, dateTo]);

    const hasActiveFilters = Boolean(dateFrom || dateTo);

    function resetFilters() {
        setDateFrom('');
        setDateTo('');
        router.get(route('report.profit-loss'), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Profit & Loss" />
            <ReportPage
                title="Profit & Loss"
                description={
                    isSaas
                        ? 'Platform subscription income and operating expenses for the SaaS / Main panel.'
                        : 'Sales, COGS, subscription expense, operating expenses, and net result for your branch.'
                }
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="From date" icon={CalendarRange}>
                            <ReportDateInput value={dateFrom} onChange={setDateFrom} />
                        </ReportFilterField>
                        <ReportFilterField label="To date" icon={CalendarRange}>
                            <ReportDateInput value={dateTo} onChange={setDateTo} />
                        </ReportFilterField>
                    </>
                }
            >
                <div className="mb-4 overflow-hidden rounded-lg border border-blue-950/15 bg-linear-to-r from-blue-950 to-blue-800 px-5 py-4 text-white shadow-md">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-white/15">
                            <ReceiptText className="size-5 text-white" />
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wider text-white/60">Reporting period</p>
                            <p className="text-xl font-bold">
                                {formatBdDate(report.date_from ?? dateFrom)} to {formatBdDate(report.date_to ?? dateTo)}
                            </p>
                            <p className="mt-0.5 text-[11px] text-white/50">
                                {isSaas
                                    ? 'SaaS panel — Main branch platform accounts only'
                                    : 'Card percentages are of sales revenue'}
                            </p>
                        </div>
                    </div>
                </div>

                {isSaas ? <SaasStatement report={report} /> : <BranchStatement report={report} />}
            </ReportPage>
        </>
    );
}
