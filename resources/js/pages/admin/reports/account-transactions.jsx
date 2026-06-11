import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { ArrowLeftRight, CalendarRange, BookOpen } from 'lucide-react';
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

function TransactionLines({ lines }) {
    if (!lines?.length) {
        return null;
    }

    return (
        <div className="mt-2 rounded-md border border-dashed border-blue-950/15 bg-slate-50/80 px-3 py-2 text-xs dark:bg-slate-900/50">
            {lines.map((line, i) => (
                <div key={i} className="flex justify-between gap-4 py-0.5">
                    <span className="text-muted-foreground">{line.account}</span>
                    <span>
                        Dr <MoneyCell value={line.debit} /> / Cr <MoneyCell value={line.credit} />
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function AccountTransactionsReport({ accounts = [], filters = {}, transactions = [] }) {
    const [accountId, setAccountId] = useState(filters.account_id ? String(filters.account_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [expanded, setExpanded] = useState({});

    useLiveReportFilters(
        'report.account-transactions',
        { account_id: accountId === 'all' ? '' : accountId, date_from: dateFrom, date_to: dateTo },
        [accountId, dateFrom, dateTo],
    );

    const hasActiveFilters = Boolean((accountId !== 'all' && accountId !== '') || dateFrom || dateTo);

    function resetFilters() {
        setAccountId('all');
        setDateFrom('');
        setDateTo('');
        router.get(route('report.account-transactions'), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="A/C Transactions" />
            <ReportPage
                title="A/C Transactions"
                description="Accounting transactions with ledger lines."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Account" icon={BookOpen} className="sm:col-span-2">
                            <ReportSelect
                                value={accountId}
                                onChange={setAccountId}
                                options={[
                                    { value: 'all', label: 'All accounts' },
                                    ...accounts.map((a) => ({ value: String(a.id), label: a.label })),
                                ]}
                            />
                        </ReportFilterField>
                        <ReportFilterField label="From date" icon={CalendarRange}>
                            <ReportDateInput value={dateFrom} onChange={setDateFrom} />
                        </ReportFilterField>
                        <ReportFilterField label="To date" icon={CalendarRange}>
                            <ReportDateInput value={dateTo} onChange={setDateTo} />
                        </ReportFilterField>
                    </>
                }
            >
                <div className="space-y-3">
                    {transactions.length === 0 && (
                        <p className="rounded-lg border border-dashed border-blue-950/20 bg-slate-50/50 px-4 py-10 text-center text-sm text-muted-foreground dark:bg-slate-900/30">
                            No transactions for this period.
                        </p>
                    )}
                    {transactions.map((txn) => (
                        <div
                            key={txn.id}
                            className="overflow-hidden rounded-lg border border-blue-950/10 bg-card shadow-sm ring-1 ring-blue-950/5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-blue-950/5 bg-gradient-to-r from-slate-50 to-blue-50/40 px-4 py-3 dark:from-slate-900/40 dark:to-blue-950/10">
                                <div>
                                    <p className="font-mono text-xs font-semibold text-blue-800 dark:text-blue-300">
                                        TXN-{String(txn.id).padStart(6, '0')}
                                    </p>
                                    <p className="text-sm font-medium">{txn.description}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatBdDate(txn.date)} · {txn.source}
                                    </p>
                                </div>
                                <div className="text-right text-sm">
                                    <p className="text-lg font-bold text-blue-950 dark:text-blue-100">
                                        <MoneyCell value={txn.amount} />
                                    </p>
                                    <p className="text-xs text-muted-foreground">Dr: {txn.debit_account}</p>
                                    <p className="text-xs text-muted-foreground">Cr: {txn.credit_account}</p>
                                </div>
                            </div>
                            {txn.lines?.length > 0 && (
                                <div className="px-4 py-2">
                                    <button
                                        type="button"
                                        className="flex items-center gap-1 text-xs font-medium text-blue-800 hover:underline dark:text-blue-300"
                                        onClick={() => setExpanded((prev) => ({ ...prev, [txn.id]: !prev[txn.id] }))}
                                    >
                                        <ArrowLeftRight className="size-3" />
                                        {expanded[txn.id] ? 'Hide' : 'Show'} {txn.lines.length} ledger line(s)
                                    </button>
                                    {expanded[txn.id] && <TransactionLines lines={txn.lines} />}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </ReportPage>
        </>
    );
}
