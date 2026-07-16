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

const sectionStyles = {
    income: {
        header: 'bg-emerald-600',
        body: 'from-emerald-50/80 to-white dark:from-emerald-950/25 dark:to-card',
        ring: 'ring-emerald-500/25',
        total: 'text-emerald-800 dark:text-emerald-300',
    },
    expenses: {
        header: 'bg-rose-600',
        body: 'from-rose-50/80 to-white dark:from-rose-950/25 dark:to-card',
        ring: 'ring-rose-500/25',
        total: 'text-rose-800 dark:text-rose-300',
    },
};

function SectionTable({ section }) {
    const style = sectionStyles[section.slug] ?? sectionStyles.income;

    if (!section?.lines?.length) {
        return <p className="px-4 py-6 text-sm text-muted-foreground">No balances recorded.</p>;
    }

    return (
        <table className="w-full text-sm">
            <tbody>
                {section.lines.map((line) => (
                    <tr key={line.code} className="border-b border-dashed border-black/5 dark:border-white/10">
                        <td className="px-4 py-2 font-mono text-xs text-muted-foreground">{line.code}</td>
                        <td className="py-2 pr-2">{line.name}</td>
                        <td className="px-4 py-2 text-right font-medium">
                            <MoneyCell value={line.amount} />
                        </td>
                    </tr>
                ))}
                <tr className="bg-black/4 font-semibold dark:bg-white/6">
                    <td colSpan={2} className="px-4 py-2.5">
                        Total {section.type}
                    </td>
                    <td className={['px-4 py-2.5 text-right', style.total].join(' ')}>
                        <MoneyCell value={section.total} />
                    </td>
                </tr>
            </tbody>
        </table>
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
                description="Branch-wise income, expenses, and net result for a period."
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

                <div className="mb-4 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-emerald-200/80 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-800/70 dark:text-emerald-300/70">
                            Total Income
                        </p>
                        <p className="mt-1 text-xl font-bold text-emerald-800 dark:text-emerald-200">
                            <MoneyCell value={report.total_income} />
                        </p>
                    </div>
                    <div className="rounded-lg border border-rose-200/80 bg-rose-50 px-4 py-3 shadow-sm dark:border-rose-800 dark:bg-rose-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-rose-800/70 dark:text-rose-300/70">
                            Total Expenses
                        </p>
                        <p className="mt-1 text-xl font-bold text-rose-900 dark:text-rose-200">
                            <MoneyCell value={report.total_expenses} />
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

                <div className="grid gap-4 lg:grid-cols-2">
                    {(report.sections ?? []).map((section) => {
                        const style = sectionStyles[section.slug] ?? sectionStyles.income;

                        return (
                            <div
                                key={section.slug}
                                className={['overflow-hidden rounded-lg border bg-card shadow-sm ring-1', style.ring].join(' ')}
                            >
                                <div
                                    className={[
                                        'px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-white',
                                        style.header,
                                    ].join(' ')}
                                >
                                    {section.type}
                                </div>
                                <div className={['bg-linear-to-b', style.body].join(' ')}>
                                    <SectionTable section={section} />
                                </div>
                            </div>
                        );
                    })}
                </div>
            </ReportPage>
        </>
    );
}
