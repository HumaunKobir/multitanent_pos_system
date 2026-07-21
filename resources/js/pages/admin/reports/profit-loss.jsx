import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarRange, ReceiptText } from 'lucide-react';
import { useState } from 'react';

import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

function sectionBySlug(sections, slug) {
    return (sections ?? []).find((section) => section.slug === slug) ?? { lines: [], total: 0 };
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

function BranchFilter({ branches, branchId, setBranchId, isBranchScoped }) {
    if (isBranchScoped) {
        return null;
    }

    return (
        <ReportFilterField label="Branch" icon={Building2}>
            <ReportSelect
                value={branchId}
                onChange={setBranchId}
                placeholder="All branches"
                options={branches.map((branch) => ({
                    value: String(branch.id),
                    label: branch.label,
                }))}
            />
        </ReportFilterField>
    );
}

export default function ProfitLossReport({ filters = {}, branches = [], isBranchScoped = false, report = {} }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : '');

    useLiveReportFilters(
        'report.profit-loss',
        { date_from: dateFrom, date_to: dateTo, branch_id: branchId },
        [dateFrom, dateTo, branchId],
    );

    const hasActiveFilters = Boolean(dateFrom || dateTo || branchId);
    const isProfit = (report.net_result ?? 0) >= 0;
    const salesRevenue = sectionBySlug(report.sections, 'sales_revenue');
    const salesReturns = sectionBySlug(report.sections, 'sales_returns');
    const salesDiscounts = sectionBySlug(report.sections, 'sales_discounts');
    const outputVat = sectionBySlug(report.sections, 'output_vat');
    const cogs = sectionBySlug(report.sections, 'cogs');
    const operatingExpenses = sectionBySlug(report.sections, 'operating_expenses');

    function resetFilters() {
        setDateFrom('');
        setDateTo('');
        setBranchId('');
        router.get(route('report.profit-loss'), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Profit & Loss" />
            <ReportPage
                title="Profit & Loss"
                description="Sales, COGS, gross profit, operating expenses, and net result for a period."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="From date" icon={CalendarRange}>
                            <ReportDateInput value={dateFrom} onChange={setDateFrom} />
                        </ReportFilterField>
                        <ReportFilterField label="To date" icon={CalendarRange}>
                            <ReportDateInput value={dateTo} onChange={setDateTo} />
                        </ReportFilterField>
                        <BranchFilter
                            branches={branches}
                            branchId={branchId}
                            setBranchId={setBranchId}
                            isBranchScoped={isBranchScoped}
                        />
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
                        </div>
                    </div>
                </div>

                <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div className="rounded-lg border border-emerald-200/80 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-800/70 dark:text-emerald-300/70">
                            Net Sales
                        </p>
                        <p className="mt-1 text-xl font-bold text-emerald-800 dark:text-emerald-200">
                            <MoneyCell value={report.net_sales} />
                        </p>
                    </div>
                    <div className="rounded-lg border border-sky-200/80 bg-sky-50 px-4 py-3 shadow-sm dark:border-sky-800 dark:bg-sky-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-sky-800/70 dark:text-sky-300/70">
                            Output VAT
                        </p>
                        <p className="mt-1 text-xl font-bold text-sky-900 dark:text-sky-200">
                            <MoneyCell value={report.output_vat} />
                        </p>
                    </div>
                    <div className="rounded-lg border border-amber-200/80 bg-amber-50 px-4 py-3 shadow-sm dark:border-amber-800 dark:bg-amber-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-800/70 dark:text-amber-300/70">
                            Gross Profit
                        </p>
                        <p className="mt-1 text-xl font-bold text-amber-900 dark:text-amber-200">
                            <MoneyCell value={report.gross_profit} />
                        </p>
                    </div>
                    <div className="rounded-lg border border-rose-200/80 bg-rose-50 px-4 py-3 shadow-sm dark:border-rose-800 dark:bg-rose-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-rose-800/70 dark:text-rose-300/70">
                            Operating Expenses
                        </p>
                        <p className="mt-1 text-xl font-bold text-rose-900 dark:text-rose-200">
                            <MoneyCell value={report.operating_expenses} />
                        </p>
                    </div>
                    <div
                        className={[
                            'rounded-lg border px-4 py-3 shadow-sm',
                            isProfit
                                ? 'border-blue-200/80 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30'
                                : 'border-amber-200/80 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30',
                        ].join(' ')}
                    >
                        <p
                            className={[
                                'text-[10px] font-semibold uppercase tracking-wider',
                                isProfit
                                    ? 'text-blue-800/70 dark:text-blue-300/70'
                                    : 'text-amber-800/70 dark:text-amber-300/70',
                            ].join(' ')}
                        >
                            {report.result_label ?? 'Net Result'}
                        </p>
                        <p
                            className={[
                                'mt-1 text-xl font-bold',
                                isProfit ? 'text-blue-900 dark:text-blue-200' : 'text-amber-900 dark:text-amber-200',
                            ].join(' ')}
                        >
                            <MoneyCell value={Math.abs(report.net_result ?? 0)} />
                        </p>
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg border bg-card shadow-sm ring-1 ring-blue-950/10">
                    <div className="bg-blue-950 px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-white">
                        Profit &amp; Loss Statement
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
                                        Output VAT (collected)
                                    </td>
                                </tr>
                                <StatementLines lines={outputVat.lines} emptyLabel="No output VAT recorded." />
                                <TotalRow
                                    label="Output VAT"
                                    value={report.output_vat ?? outputVat.total}
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
            </ReportPage>
        </>
    );
}
