import { formatBdDate } from '@/lib/format-bd-date';
import { Head } from '@inertiajs/react';
import { CalendarRange, Package } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/ui/data-table';
import {
    ReportDateInput,
    ReportFilterField,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';

export default function DateWiseStockReport({ products = [], filters = {}, entries = [] }) {
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useLiveReportFilters(
        'report.date-wise-stock',
        { product_id: productId === 'all' ? '' : productId, date_from: dateFrom, date_to: dateTo },
        [productId, dateFrom, dateTo],
    );

    return (
        <>
            <Head title="Date Wise Stock" />
            <ReportPage
                title="Date Wise Stock"
                description="Product stock in/out movement log."
                filterBar={
                    <>
                        <ReportFilterField label="Product" icon={Package} className="sm:col-span-2">
                            <ReportSelect
                                value={productId}
                                onChange={setProductId}
                                options={[
                                    { value: 'all', label: 'All products' },
                                    ...products.map((p) => ({ value: String(p.id), label: p.label })),
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
                        { id: 'time', header: 'Time', render: (row) => row.time },
                        { id: 'product', header: 'Product', render: (row) => row.product },
                        { id: 'sku', header: 'SKU', render: (row) => row.sku },
                        { id: 'type', header: 'Type', render: (row) => row.type },
                        { id: 'qty', header: 'Qty', render: (row) => row.quantity },
                        { id: 'stock', header: 'Stock After', render: (row) => row.stock },
                        { id: 'remark', header: 'Remark', render: (row) => row.remark },
                    ]}
                    rows={entries}
                    rowKey={(row, i) => `${row.date}-${row.time}-${i}`}
                    emptyMessage="No stock movements for this period."
                />
            </ReportPage>
        </>
    );
}
