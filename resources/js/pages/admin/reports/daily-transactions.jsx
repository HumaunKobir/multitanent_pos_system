import { formatBdDate } from '@/lib/format-bd-date';
import { Head } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportPage,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

export default function DailyTransactionsReport({ filters = {}, entries = [] }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useLiveReportFilters('report.daily-transactions', { date_from: dateFrom, date_to: dateTo }, [dateFrom, dateTo]);

    return (
        <>
            <Head title="Daily Transactions" />
            <ReportPage
                title="Daily Transactions"
                description="All ledger entries for the selected period."
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
                <DataTable
                    columns={[
                        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
                        { id: 'account', header: 'Account', render: (row) => row.account },
                        { id: 'ref', header: 'Reference', render: (row) => row.reference },
                        { id: 'desc', header: 'Description', render: (row) => row.description },
                        { id: 'debit', header: 'Debit', render: (row) => <MoneyCell value={row.debit} /> },
                        { id: 'credit', header: 'Credit', render: (row) => <MoneyCell value={row.credit} /> },
                    ]}
                    rows={entries}
                    rowKey={(row, i) => `${row.date}-${row.account}-${i}`}
                    emptyMessage="No transactions for this period."
                />
            </ReportPage>
        </>
    );
}
