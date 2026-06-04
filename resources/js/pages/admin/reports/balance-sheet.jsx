import { formatBdDate } from '@/lib/format-bd-date';
import { Head } from '@inertiajs/react';
import { CalendarDays, Scale } from 'lucide-react';
import { useState } from 'react';

import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportPage,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

const sectionStyles = {
    asset: {
        header: 'bg-emerald-600',
        body: 'from-emerald-50/80 to-white dark:from-emerald-950/25 dark:to-card',
        ring: 'ring-emerald-500/25',
        total: 'text-emerald-800 dark:text-emerald-300',
    },
    liability: {
        header: 'bg-amber-600',
        body: 'from-amber-50/80 to-white dark:from-amber-950/25 dark:to-card',
        ring: 'ring-amber-500/25',
        total: 'text-amber-800 dark:text-amber-300',
    },
    equity: {
        header: 'bg-blue-600',
        body: 'from-blue-50/80 to-white dark:from-blue-950/25 dark:to-card',
        ring: 'ring-blue-500/25',
        total: 'text-blue-800 dark:text-blue-300',
    },
};

function SectionTable({ section }) {
    const style = sectionStyles[section.slug] ?? sectionStyles.asset;

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
                            <MoneyCell value={line.balance} />
                        </td>
                    </tr>
                ))}
                <tr className="bg-black/[0.04] font-semibold dark:bg-white/[0.06]">
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

export default function BalanceSheetReport({ filters = {}, sheet = {} }) {
    const [asOf, setAsOf] = useState(filters.as_of ?? '');
    const balanced = sheet.is_balanced;

    useLiveReportFilters('report.balance-sheet', { as_of: asOf }, [asOf]);

    return (
        <>
            <Head title="Balance Sheet" />
            <ReportPage
                title="Balance Sheet"
                description="Assets, liabilities and equity as of a date."
                filterBar={
                    <ReportFilterField label="As of date" icon={CalendarDays}>
                        <ReportDateInput value={asOf} onChange={setAsOf} />
                    </ReportFilterField>
                }
            >
                <div className="mb-4 overflow-hidden rounded-lg border border-blue-950/15 bg-gradient-to-r from-blue-950 to-blue-800 px-5 py-4 text-white shadow-md">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-white/15">
                            <Scale className="size-5 text-white" />
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wider text-white/60">Position as of</p>
                            <p className="text-xl font-bold">{formatBdDate(sheet.as_of ?? asOf)}</p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-emerald-200/80 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-800/70 dark:text-emerald-300/70">
                            Total Assets
                        </p>
                        <p className="mt-1 text-xl font-bold text-emerald-800 dark:text-emerald-200">
                            <MoneyCell value={sheet.total_assets} />
                        </p>
                    </div>
                    <div className="rounded-lg border border-amber-200/80 bg-amber-50 px-4 py-3 shadow-sm dark:border-amber-800 dark:bg-amber-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-800/70 dark:text-amber-300/70">
                            Total Liabilities
                        </p>
                        <p className="mt-1 text-xl font-bold text-amber-900 dark:text-amber-200">
                            <MoneyCell value={sheet.total_liabilities} />
                        </p>
                    </div>
                    <div className="rounded-lg border border-blue-200/80 bg-blue-50 px-4 py-3 shadow-sm dark:border-blue-800 dark:bg-blue-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-blue-800/70 dark:text-blue-300/70">
                            Total Equity
                        </p>
                        <p className="mt-1 text-xl font-bold text-blue-900 dark:text-blue-200">
                            <MoneyCell value={sheet.total_equity} />
                        </p>
                    </div>
                </div>

                <div
                    className={[
                        'mb-4 rounded-lg border px-4 py-3 text-sm shadow-sm',
                        balanced
                            ? 'border-emerald-300/80 bg-emerald-50 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-100'
                            : 'border-amber-300/80 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100',
                    ].join(' ')}
                >
                    <span className="font-medium">Liabilities + Equity:</span>{' '}
                    <MoneyCell value={sheet.liabilities_plus_equity} className="font-bold" />
                    {balanced ? (
                        <span className="ml-2 text-emerald-700 dark:text-emerald-300">✓ Balanced with assets</span>
                    ) : (
                        <span className="ml-2">
                            — Difference:{' '}
                            <MoneyCell
                                value={Math.abs((sheet.total_assets ?? 0) - (sheet.liabilities_plus_equity ?? 0))}
                                className="font-bold"
                            />
                        </span>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {(sheet.sections ?? []).map((section) => {
                        const s = style[section.slug] ?? style.asset;

                        return (
                            <div
                                key={section.slug}
                                className={['overflow-hidden rounded-lg border bg-card shadow-sm ring-1', s.ring].join(' ')}
                            >
                                <div className={['px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-white', s.header].join(' ')}>
                                    {section.type}
                                </div>
                                <div className={['bg-gradient-to-b', s.body].join(' ')}>
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
