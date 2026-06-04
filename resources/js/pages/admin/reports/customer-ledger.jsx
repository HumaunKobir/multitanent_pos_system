import { formatBdDate } from '@/lib/format-bd-date';
import { Head } from '@inertiajs/react';
import { CalendarRange, User } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportInfoBanner,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

export default function CustomerLedgerReport({ customers = [], filters = {}, customer = null, entries = [], totals = {} }) {
    const [customerId, setCustomerId] = useState(filters.customer_id ? String(filters.customer_id) : '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useLiveReportFilters(
        'report.customer-ledger',
        { customer_id: customerId, date_from: dateFrom, date_to: dateTo },
        [customerId, dateFrom, dateTo],
    );

    return (
        <>
            <Head title="Customer Ledger" />
            <ReportPage
                title="Customer Ledger"
                description="Sales and returns ledger for a customer."
                filterBar={
                    <>
                        <ReportFilterField label="Customer" icon={User} className="sm:col-span-2">
                            <ReportSelect
                                value={customerId}
                                onChange={setCustomerId}
                                placeholder="Select customer"
                                options={customers.map((c) => ({ value: String(c.id), label: c.label }))}
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
                {customer && (
                    <ReportInfoBanner>
                        <strong>{customer.name}</strong> ({customer.phone}) — Current balance:{' '}
                        <MoneyCell value={customer.balance} className="font-semibold text-blue-950 dark:text-blue-100" />
                    </ReportInfoBanner>
                )}

                <DataTable
                    columns={[
                        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
                        { id: 'type', header: 'Type', render: (row) => row.type },
                        { id: 'ref', header: 'Reference', render: (row) => <span className="font-mono text-xs">{row.reference}</span> },
                        { id: 'desc', header: 'Description', render: (row) => row.description },
                        { id: 'debit', header: 'Debit', render: (row) => <MoneyCell value={row.debit} /> },
                        { id: 'credit', header: 'Credit', render: (row) => <MoneyCell value={row.credit} /> },
                        { id: 'balance', header: 'Balance', render: (row) => <MoneyCell value={row.balance} className="font-medium" /> },
                    ]}
                    rows={entries}
                    rowKey={(row, i) => `${row.reference}-${i}`}
                    emptyMessage={customerId ? 'No ledger entries for this period.' : 'Select a customer to view the ledger.'}
                />

                {entries.length > 0 && (
                    <div className="mt-4 flex flex-wrap justify-end gap-4 rounded-lg border border-blue-950/10 bg-gradient-to-r from-slate-50 to-blue-50/30 px-4 py-3 text-sm dark:from-slate-900/50 dark:to-blue-950/20 sm:gap-6">
                        <span>
                            Total Debit: <MoneyCell value={totals.debit} className="font-semibold text-red-700 dark:text-red-400" />
                        </span>
                        <span>
                            Total Credit: <MoneyCell value={totals.credit} className="font-semibold text-emerald-700 dark:text-emerald-400" />
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
