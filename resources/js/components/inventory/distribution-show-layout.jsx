import { formatQty } from '@/components/inventory/inventory-form';
import { formatBdDate } from '@/lib/format-bd-date';
import { usePage } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';

function lineStatusBadge(row) {
    if (row.is_received) {
        return <Badge variant="default">Received</Badge>;
    }

    return <Badge variant="secondary">Pending</Badge>;
}

const distributionLineColumns = [
    {
        id: 'num',
        header: '#',
        render: (_, i) => <span className="text-muted-foreground">{i + 1}</span>,
    },
    {
        id: 'product',
        header: 'Product',
        render: (row) => (
            <div>
                <p className="font-medium">{row.product?.name ?? '—'}</p>
                {row.variation?.variation_data?.label && (
                    <p className="text-xs text-muted-foreground">{row.variation.variation_data.label}</p>
                )}
            </div>
        ),
    },
    {
        id: 'main_stock_before',
        header: 'Main Stock',
        align: 'right',
        render: (row) => (
            <span className="font-medium text-muted-foreground">{formatQty(row.main_stock_before ?? 0)}</span>
        ),
    },
    {
        id: 'quantity',
        header: 'Distributed',
        align: 'right',
        render: (row) => <span className="font-semibold text-primary">{formatQty(row.quantity ?? 0)}</span>,
    },
    {
        id: 'main_stock_after',
        header: 'Remaining',
        align: 'right',
        render: (row) => <span className="font-medium">{formatQty(row.main_stock_after ?? 0)}</span>,
    },
    {
        id: 'status',
        header: 'Status',
        render: (row) => (
            <div className="space-y-0.5">
                {lineStatusBadge(row)}
                {row.is_received && row.received_by?.name && (
                    <p className="text-[10px] text-muted-foreground">by {row.received_by.name}</p>
                )}
            </div>
        ),
    },
];

function DistributionSummary({ totalProducts, totalQuantity, receivedCount, pendingCount }) {
    const rows = [
        { label: 'Products', value: totalProducts, muted: true },
        { label: 'Received', value: receivedCount, muted: true },
        { label: 'Pending', value: pendingCount, muted: true },
        { label: 'Total Distributed', value: formatQty(totalQuantity), bold: true, divider: true },
    ];

    return (
        <div className="flex justify-end">
            <div className="w-full max-w-sm overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
                <div className="border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                    <p className="text-xs font-semibold uppercase tracking-wider text-blue-50">Distribution Summary</p>
                </div>
                <div className="space-y-1.5 p-4 text-sm">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className={[
                                'flex justify-between',
                                row.divider ? 'border-t border-border pt-2 font-bold' : '',
                                row.bold ? 'font-bold' : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        >
                            <span className={row.muted ? 'text-muted-foreground' : ''}>{row.label}</span>
                            <span>{row.value}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

export function DistributionDocument({
    docTitle,
    invoiceNumber,
    date,
    fromBranchName,
    toBranchName,
    branchSection,
    items = [],
    comment,
    receivedCount = 0,
    pendingCount = 0,
    selectionColumn,
}) {
    const { logo } = usePage().props;
    const displayBranch = fromBranchName || 'Coolness Point';
    const totalQuantity = items.reduce((sum, row) => sum + parseFloat(row.quantity ?? 0), 0);

    const columns = selectionColumn
        ? [selectionColumn, ...distributionLineColumns]
        : distributionLineColumns;

    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 print:border-0 print:bg-transparent print:shadow-none dark:ring-blue-400/14">
            <div className="flex flex-col gap-4 border-b border-blue-900/80 bg-blue-950 px-5 py-4 text-white sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-4">
                    {logo ? (
                        <div className="flex size-14 shrink-0 items-center justify-center rounded-lg bg-white/10 p-2">
                            <img src={logo} alt={displayBranch} className="max-h-full max-w-full object-contain" />
                        </div>
                    ) : null}
                    <div>
                        <h2 className="text-lg font-bold text-white">{displayBranch}</h2>
                        <p className="text-sm text-blue-100/80">{docTitle}</p>
                        {toBranchName ? (
                            <p className="mt-0.5 text-xs text-blue-100/70">To: {toBranchName}</p>
                        ) : null}
                    </div>
                </div>
                <div className="rounded-lg bg-white/10 px-4 py-2.5 text-sm sm:text-right">
                    <p className="font-mono text-base font-bold text-white">{invoiceNumber}</p>
                    <p className="text-blue-100/80">Date: {formatBdDate(date)}</p>
                </div>
            </div>

            <div className="p-5 print:p-0">
                {branchSection}

                <div className="mb-6">
                    <DataTable
                        columns={columns}
                        rows={items}
                        rowKey={(row, index) => row.id ?? index}
                        emptyMessage="No products distributed."
                        caption="Distribution line items"
                    />
                </div>

                <DistributionSummary
                    totalProducts={items.length}
                    totalQuantity={totalQuantity}
                    receivedCount={receivedCount}
                    pendingCount={pendingCount}
                />

                <div className="mt-4 overflow-hidden rounded-lg border border-border">
                    <div className="border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                        <p className="text-xs font-semibold uppercase tracking-wider text-blue-50">Note</p>
                    </div>
                    <p className="whitespace-pre-wrap p-4 text-sm text-foreground">
                        {String(comment ?? '').trim() || '—'}
                    </p>
                </div>
            </div>
        </div>
    );
}
