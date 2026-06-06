import { formatBdDate } from '@/lib/format-bd-date';
import { usePage } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';

const lineItemColumns = [
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
        id: 'unit_price',
        header: 'Unit Price',
        align: 'right',
        render: (row) => <span className="font-medium">৳{parseFloat(row.unit_price ?? 0).toFixed(2)}</span>,
    },
    {
        id: 'quantity',
        header: 'Qty',
        align: 'right',
        render: (row) => parseFloat(row.quantity ?? 0),
    },
    {
        id: 'subtotal',
        header: 'Sub Total',
        align: 'right',
        render: (row) => (
            <span className="font-semibold text-primary">
                ৳{(parseFloat(row.unit_price ?? 0) * parseFloat(row.quantity ?? 0)).toFixed(2)}
            </span>
        ),
    },
];

function LineItemsTable({ items }) {
    return (
        <div className="mb-6">
            <DataTable
                columns={lineItemColumns}
                rows={items}
                rowKey={(row, index) => row.id ?? index}
                emptyMessage="No line items."
                caption="Invoice line items"
            />
        </div>
    );
}

function TotalsSummary({ gross, vat, discount, net, paid, due }) {
    const rows = [
        { label: 'Gross Amount', value: `৳${gross.toFixed(2)}`, muted: true },
        ...(discount > 0 ? [{ label: 'Discount', value: `-৳${discount.toFixed(2)}`, accent: 'text-green-600' }] : []),
        ...(vat > 0 ? [{ label: 'VAT', value: `৳${vat.toFixed(2)}`, muted: true }] : []),
        { label: 'Net Amount', value: `৳${net.toFixed(2)}`, bold: true, divider: true },
        { label: 'Paid', value: `৳${paid.toFixed(2)}`, accent: 'text-green-700 dark:text-green-400' },
        { label: 'Due', value: `৳${due.toFixed(2)}`, accent: 'font-semibold text-destructive' },
    ];

    return (
        <div className="flex justify-end">
            <div className="w-full max-w-sm overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
                <div className="border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                    <p className="text-xs font-semibold uppercase tracking-wider text-blue-50">Payment Summary</p>
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
                            <span className={row.accent ?? ''}>{row.value}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

export function InvoiceShowHeader({ icon: Icon, title, invoiceNumber, children }) {
    return (
        <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3 print:hidden">
            <div className="flex items-center gap-3">
                <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                    <Icon className="size-4 text-white" />
                </div>
                <div>
                    <h1 className="text-base font-semibold text-white">{title}</h1>
                    <p className="font-mono text-xs text-white/60">{invoiceNumber}</p>
                </div>
            </div>
            <div className="flex flex-wrap justify-end gap-2">{children}</div>
        </div>
    );
}

export function InvoiceDocument({
    docTitle,
    invoiceNumber,
    date,
    branchName,
    partySection,
    items = [],
    totals,
    comment,
}) {
    const { logo } = usePage().props;
    const displayBranch = branchName || 'Coolness Point';
    const gross = parseFloat(totals.gross ?? 0);
    const vat = parseFloat(totals.vat ?? 0);
    const discount = parseFloat(totals.discount ?? 0);
    const net = parseFloat(totals.net ?? gross + vat - discount);
    const paid = parseFloat(totals.paid ?? 0);
    const due = parseFloat(totals.due ?? Math.max(0, net - paid));

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
                    </div>
                </div>
                <div className="rounded-lg bg-white/10 px-4 py-2.5 text-sm sm:text-right">
                    <p className="font-mono text-base font-bold text-white">{invoiceNumber}</p>
                    <p className="text-blue-100/80">Date: {formatBdDate(date)}</p>
                </div>
            </div>

            <div className="p-5 print:p-0">
                {partySection}

                <LineItemsTable items={items} />

                <TotalsSummary gross={gross} vat={vat} discount={discount} net={net} paid={paid} due={due} />

                <div className="mt-4 flex items-center gap-2 print:hidden">
                    <Badge className={due > 0 ? 'bg-red-600 text-white' : 'bg-green-600 text-white'}>
                        {due > 0 ? 'Partially Paid' : 'Paid'}
                    </Badge>
                </div>

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

function PartyField({ label, value }) {
    if (!value) {
        return null;
    }

    return (
        <div>
            <p className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-sm font-medium">{value}</p>
        </div>
    );
}

export function PartyInfoCard({ icon: Icon, label, name, phone, address, companyName, emptyText = '—' }) {
    const hasParty = Boolean(name);

    return (
        <div className="mb-6 overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
            <div className="flex items-center gap-2.5 border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                {Icon && (
                    <div className="flex size-6 items-center justify-center rounded bg-white/15">
                        <Icon className="size-3.5 text-white" />
                    </div>
                )}
                <h3 className="text-sm font-semibold uppercase tracking-wide text-white">{label}</h3>
            </div>
            <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                {hasParty ? (
                    <>
                        <PartyField label="Name" value={name} />
                        <PartyField label="Phone" value={phone} />
                        <PartyField label="Company" value={companyName} />
                        <div className="sm:col-span-2 lg:col-span-3">
                            <PartyField label="Address" value={address} />
                        </div>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground sm:col-span-2 lg:col-span-3">{emptyText}</p>
                )}
            </div>
        </div>
    );
}

export function headerActionClassName() {
    return 'border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md';
}
