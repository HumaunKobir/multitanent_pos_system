import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarDays, Scale } from 'lucide-react';
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

export default function TrialBalanceReport({ filters = {}, branches = [], isBranchScoped = false, report = {} }) {
    const [asOf, setAsOf] = useState(filters.as_of ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ? String(filters.branch_id) : '');

    useLiveReportFilters(
        'report.trial-balance',
        { as_of: asOf, branch_id: branchId },
        [asOf, branchId],
    );

    const hasActiveFilters = Boolean(asOf || branchId);
    const balanced = report.is_balanced;

    function resetFilters() {
        setAsOf('');
        setBranchId('');
        router.get(route('report.trial-balance'), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Trial Balance" />
            <ReportPage
                title="Trial Balance"
                description="Branch-wise debit and credit balances as of a date."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="As of date" icon={CalendarDays}>
                            <ReportDateInput value={asOf} onChange={setAsOf} />
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
                            <Scale className="size-5 text-white" />
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wider text-white/60">Position as of</p>
                            <p className="text-xl font-bold">{formatBdDate(report.as_of ?? asOf)}</p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-lg border border-emerald-200/80 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-800/70 dark:text-emerald-300/70">
                            Total Debit
                        </p>
                        <p className="mt-1 text-xl font-bold text-emerald-800 dark:text-emerald-200">
                            <MoneyCell value={report.totals?.debit} />
                        </p>
                    </div>
                    <div className="rounded-lg border border-blue-200/80 bg-blue-50 px-4 py-3 shadow-sm dark:border-blue-800 dark:bg-blue-950/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-blue-800/70 dark:text-blue-300/70">
                            Total Credit
                        </p>
                        <p className="mt-1 text-xl font-bold text-blue-900 dark:text-blue-200">
                            <MoneyCell value={report.totals?.credit} />
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
                    {balanced ? (
                        <span className="font-medium">Debit and credit totals are balanced.</span>
                    ) : (
                        <span className="font-medium">
                            Difference:{' '}
                            <MoneyCell
                                value={Math.abs((report.totals?.debit ?? 0) - (report.totals?.credit ?? 0))}
                                className="font-bold"
                            />
                        </span>
                    )}
                </div>

                <div className="overflow-hidden rounded-lg border border-blue-950/10 bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-blue-950 text-left text-xs uppercase tracking-wide text-white">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">Code</th>
                                    <th className="px-4 py-3 font-semibold">Account</th>
                                    <th className="px-4 py-3 font-semibold">Type</th>
                                    <th className="px-4 py-3 text-right font-semibold">Debit</th>
                                    <th className="px-4 py-3 text-right font-semibold">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(report.rows ?? []).length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                                            No account balances recorded.
                                        </td>
                                    </tr>
                                ) : (
                                    <>
                                        {(report.rows ?? []).map((row) => (
                                            <tr
                                                key={`${row.code}-${row.name}`}
                                                className="border-b border-dashed border-black/5 dark:border-white/10"
                                            >
                                                <td className="px-4 py-2 font-mono text-xs text-muted-foreground">{row.code}</td>
                                                <td className="px-4 py-2">{row.name}</td>
                                                <td className="px-4 py-2 text-muted-foreground">{row.type}</td>
                                                <td className="px-4 py-2 text-right">
                                                    {row.debit > 0 ? <MoneyCell value={row.debit} /> : '—'}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    {row.credit > 0 ? <MoneyCell value={row.credit} /> : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="bg-black/4 font-semibold dark:bg-white/6">
                                            <td colSpan={3} className="px-4 py-3">
                                                Totals
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <MoneyCell value={report.totals?.debit} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <MoneyCell value={report.totals?.credit} />
                                            </td>
                                        </tr>
                                    </>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </ReportPage>
        </>
    );
}
