import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import { CalendarRange, Wallet } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

export default function CashFlowSummaryReport({ accounts = [], filters = {}, rows = [] }) {
    const [accountId, setAccountId] = useState(filters.account_id ? String(filters.account_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useLiveReportFilters(
        'report.cash-flow-summary',
        { account_id: accountId === 'all' ? '' : accountId, date_from: dateFrom, date_to: dateTo },
        [accountId, dateFrom, dateTo],
    );

    const hasActiveFilters = Boolean((accountId !== 'all' && accountId !== '') || dateFrom || dateTo);

    function resetFilters() {
        setAccountId('all');
        setDateFrom('');
        setDateTo('');
        router.get(route('report.cash-flow-summary'), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Cash Flow Summary" />
            <ReportPage
                title="Cash Flow Summary"
                description="Daily totals of cash account movements."
                filterActions={<ReportFilterReset onClick={resetFilters} disabled={!hasActiveFilters} />}
                filterBar={
                    <>
                        <ReportFilterField label="Cash account" icon={Wallet} className="sm:col-span-2">
                            <ReportSelect
                                value={accountId}
                                onChange={setAccountId}
                                options={[
                                    { value: 'all', label: 'All cash accounts' },
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
                <DataTable
                    columns={[
                        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
                        { id: 'debit', header: 'Total Debit', render: (row) => <MoneyCell value={row.debit} /> },
                        { id: 'credit', header: 'Total Credit', render: (row) => <MoneyCell value={row.credit} /> },
                        { id: 'net', header: 'Net', render: (row) => <MoneyCell value={row.net} className="font-medium" /> },
                    ]}
                    rows={rows}
                    rowKey="date"
                    emptyMessage="No summary data for this period."
                />
            </ReportPage>
        </>
    );
}
