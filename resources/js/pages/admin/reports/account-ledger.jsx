import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { BookOpen, CalendarRange } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportInfoBanner,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

export default function AccountLedgerReport({
    accounts = [],
    filters = {},
    account = null,
    opening_balance = 0,
    entries = [],
    totals = {},
}) {
    const [accountId, setAccountId] = useState(filters.account_id ? String(filters.account_id) : '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useLiveReportFilters(
        'report.account-ledger',
        { account_id: accountId, date_from: dateFrom, date_to: dateTo },
        [accountId, dateFrom, dateTo],
    );

    const hasActiveFilters = Boolean(accountId || dateFrom || dateTo);

    function resetFilters() {
        setAccountId('');
        setDateFrom('');
        setDateTo('');
        router.get(route('report.account-ledger'), {}, { preserveState: true, replace: true });
    }

    const displayRows =
        account && dateFrom
            ? [
                  {
                      _key: 'opening',
                      date: dateFrom,
                      description: 'Opening balance',
                      reference: '—',
                      party: 'N/A',
                      debit: 0,
                      credit: 0,
                      balance: opening_balance,
                      isOpening: true,
                  },
                  ...entries,
              ]
            : entries;

    return (
        <>
            <Head title="Account Ledger" />
            <ReportPage
                title="Account Ledger"
                description="Ledger book for a chart of account."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Account" icon={BookOpen} className="sm:col-span-2">
                            <ReportSelect
                                value={accountId}
                                onChange={setAccountId}
                                placeholder="Select account"
                                options={accounts.map((a) => ({
                                    value: String(a.id),
                                    label: `${a.label} (${a.type})`,
                                }))}
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
                {account && (
                    <ReportInfoBanner>
                        <strong>
                            {account.code} — {account.name}
                        </strong>{' '}
                        <span className="text-muted-foreground">({account.type})</span>
                        <span className="ml-3">
                            Current balance:{' '}
                            <MoneyCell value={account.current_balance} className="font-semibold text-blue-950 dark:text-blue-100" />
                        </span>
                    </ReportInfoBanner>
                )}

                <DataTable
                    columns={[
                        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
                        { id: 'ref', header: 'Reference', render: (row) => <span className="font-mono text-xs">{row.reference}</span> },
                        { id: 'desc', header: 'Description', render: (row) => row.description },
                        {
                            id: 'party',
                            header: 'Party',
                            render: (row) => row.party || 'N/A',
                        },
                        {
                            id: 'debit',
                            header: 'Debit',
                            render: (row) => (row.isOpening ? '—' : <MoneyCell value={row.debit} />),
                        },
                        {
                            id: 'credit',
                            header: 'Credit',
                            render: (row) => (row.isOpening ? '—' : <MoneyCell value={row.credit} />),
                        },
                        {
                            id: 'balance',
                            header: 'Balance',
                            render: (row) => <MoneyCell value={row.balance} className="font-medium" />,
                        },
                    ]}
                    rows={displayRows}
                    rowKey={(row) => row._key ?? `${row.date}-${row.reference}-${row.balance}`}
                    emptyMessage={accountId ? 'No ledger entries for this period.' : 'Select an account to view the ledger.'}
                />

                {entries.length > 0 && (
                    <div className="mt-4 flex flex-wrap justify-end gap-4 rounded-lg border border-blue-950/10 bg-gradient-to-r from-slate-50 to-blue-50/30 px-4 py-3 text-sm dark:from-slate-900/50 dark:to-blue-950/20 sm:gap-6">
                        <span>
                            Period Debit: <MoneyCell value={totals.debit} className="font-semibold" />
                        </span>
                        <span>
                            Period Credit: <MoneyCell value={totals.credit} className="font-semibold" />
                        </span>
                        <span>
                            Closing: <MoneyCell value={totals.balance} className="font-semibold text-blue-950 dark:text-blue-200" />
                        </span>
                    </div>
                )}
            </ReportPage>
        </>
    );
}
