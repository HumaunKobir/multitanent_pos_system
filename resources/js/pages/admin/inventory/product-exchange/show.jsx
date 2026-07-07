import {
    headerActionClassName,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { formatProductLabel } from '@/components/inventory/inventory-form';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAppToast } from '@/contexts/app-toast-context';
import { useCan } from '@/hooks/use-can';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowLeftRight, Edit, Trash2, User } from 'lucide-react';
import { useEffect, useState } from 'react';

const PAYMENT_STATUS_LABELS = {
    unpaid: 'Unpaid',
    partially_paid: 'Partially Paid',
    fully_paid: 'Fully Paid',
    settled: 'Settled',
};

const PAYMENT_TYPE_LABELS = {
    0: 'Cash / Bank',
    5: 'Customer Account',
};

function SummaryRow({ label, value, accent = '', children }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            {children ?? <span className={accent}>{value}</span>}
        </div>
    );
}

function SummaryCard({ title, children }) {
    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
            <div className="border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                <p className="text-xs font-semibold uppercase tracking-wider text-blue-50">
                    {title}
                </p>
            </div>
            <div className="space-y-1.5 p-4 text-sm">{children}</div>
        </div>
    );
}

export default function ProductExchangeShow({ exchange, totals = {} }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    const invoiceNumber =
        exchange.invoice_number ??
        `INVX${String(exchange.id).padStart(8, '0')}`;
    const actionClass = headerActionClassName();
    const isRefund = totals.is_refund ?? false;
    const settlementLabel = isRefund ? 'Refund to Customer' : 'Customer Pays';
    const paidLabel = isRefund ? 'Refund Paid' : 'Paid';
    const dueLabel = isRefund ? 'Remaining Refund' : 'Due';
    const paymentStatus =
        PAYMENT_STATUS_LABELS[totals.payment_status ?? exchange.payment_status] ??
        '—';
    const paymentTypeLabel =
        PAYMENT_TYPE_LABELS[exchange.payment_type] ?? '—';

    return (
        <>
            <Head title={`Product Exchange — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <InvoiceShowHeader
                    icon={ArrowLeftRight}
                    title="Product Exchange"
                    invoiceNumber={invoiceNumber}
                >
                    {exchange.can_access_edit !== false && (
                        <Can permission="inventory.product-exchange.update">
                            <Button size="sm" asChild className={actionClass}>
                                <Link
                                    href={route(
                                        'inventory.product-exchange.edit',
                                        exchange.id,
                                    )}
                                >
                                    <Edit className="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                        </Can>
                    )}
                    <Can permission="inventory.product-exchange.delete">
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => setDeleting(true)}
                            className="border border-red-500/50 bg-red-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-md"
                        >
                            <Trash2 className="size-3.5" />
                            Delete
                        </Button>
                    </Can>
                    <Button size="sm" asChild className={actionClass}>
                        <Link href={route('inventory.product-exchange.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </InvoiceShowHeader>

                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
                    <div className="border-b border-blue-900/80 bg-blue-950 px-5 py-3 text-white">
                        <p className="text-sm text-blue-100/80">
                            Date: {formatBdDate(exchange.date)} · Source sale #
                            {exchange.sell_id}
                        </p>
                    </div>

                    <div className="p-5">
                        <PartyInfoCard
                            icon={User}
                            label="Customer"
                            name={exchange.customer?.name}
                            phone={exchange.customer?.phone}
                            address={exchange.customer?.address}
                            emptyText="Walk-in Customer"
                        />

                        <div className="mb-6 overflow-hidden rounded-lg border border-border">
                            <table className="w-full text-xs">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="p-2 text-left">Old Product</th>
                                        <th className="p-2 text-left">New Product</th>
                                        <th className="p-2 text-right">Exch. Qty</th>
                                        <th className="p-2 text-right">Return Qty</th>
                                        <th className="p-2 text-right">Old Price</th>
                                        <th className="p-2 text-right">New Price</th>
                                        <th className="p-2 text-right">Line Disc.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(exchange.products ?? []).map((line) => (
                                        <tr key={line.id} className="border-t">
                                            <td className="p-2">
                                                {formatProductLabel(
                                                    line.old_product?.name,
                                                    line.old_product?.code,
                                                )}
                                            </td>
                                            <td className="p-2">
                                                {parseFloat(
                                                    line.old_quantity || 0,
                                                ) > 0
                                                    ? formatProductLabel(
                                                          line.new_product?.name,
                                                          line.new_product?.code,
                                                      )
                                                    : '—'}
                                                {parseFloat(
                                                    line.old_quantity || 0,
                                                ) > 0 &&
                                                    line.new_promotion?.name && (
                                                        <p className="text-[10px] text-purple-700 dark:text-purple-400">
                                                            Promo: {line.new_promotion.name}
                                                        </p>
                                                    )}
                                            </td>
                                            <td className="p-2 text-right">
                                                {parseFloat(
                                                    line.old_quantity || 0,
                                                ) > 0
                                                    ? line.new_quantity
                                                    : '—'}
                                            </td>
                                            <td className="p-2 text-right">
                                                {parseFloat(
                                                    line.return_quantity || 0,
                                                ) > 0
                                                    ? parseFloat(
                                                          line.return_quantity,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="p-2 text-right">
                                                ৳
                                                {parseFloat(
                                                    line.old_unit_price || 0,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="p-2 text-right">
                                                {parseFloat(
                                                    line.old_quantity || 0,
                                                ) > 0
                                                    ? `৳${parseFloat(line.new_unit_price || 0).toFixed(2)}`
                                                    : '—'}
                                            </td>
                                            <td className="p-2 text-right">
                                                {parseFloat(
                                                    line.new_line_discount || 0,
                                                ) > 0
                                                    ? `-৳${parseFloat(line.new_line_discount).toFixed(2)}`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <SummaryCard title="Exchange Summary">
                                <SummaryRow
                                    label="Old total"
                                    value={`৳${(totals.old_total ?? 0).toFixed(2)}`}
                                />
                                <SummaryRow
                                    label="New gross"
                                    value={`৳${(totals.gross ?? 0).toFixed(2)}`}
                                />
                                <SummaryRow
                                    label="Price difference"
                                    value={`৳${(totals.gross_price_difference ?? 0).toFixed(2)}`}
                                    accent={
                                        (totals.gross_price_difference ?? 0) > 0
                                            ? 'font-semibold text-primary'
                                            : (totals.gross_price_difference ?? 0) < 0
                                              ? 'font-semibold text-destructive'
                                              : ''
                                    }
                                />
                                {(totals.new_discount_total ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label="Discounts"
                                        value={`-৳${totals.new_discount_total.toFixed(2)}`}
                                        accent="text-destructive"
                                    />
                                )}
                                <SummaryRow
                                    label="Net new"
                                    value={`৳${(totals.net ?? 0).toFixed(2)}`}
                                    accent="font-semibold"
                                />
                                {(totals.return_refund ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label={`Return refund${(totals.return_quantity ?? 0) > 0 ? ` (${totals.return_quantity} qty)` : ''}`}
                                        value={`-৳${totals.return_refund.toFixed(2)}`}
                                        accent="text-destructive"
                                    />
                                )}
                                {(totals.settlement ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label={settlementLabel}
                                        value={`৳${totals.settlement.toFixed(2)}`}
                                        accent={
                                            isRefund
                                                ? 'font-semibold text-destructive'
                                                : 'font-semibold text-primary'
                                        }
                                    />
                                )}
                            </SummaryCard>

                            <SummaryCard title="Payment Summary">
                                {(totals.line_discount ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label="Line discount"
                                        value={`-৳${totals.line_discount.toFixed(2)}`}
                                        accent="text-green-600"
                                    />
                                )}
                                {(totals.promotion_discount ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label="Promotion discount"
                                        value={`-৳${totals.promotion_discount.toFixed(2)}`}
                                        accent="text-purple-700 dark:text-purple-400"
                                    />
                                )}
                                {(totals.special_discount ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label={
                                            totals.special_discount_name
                                                ? `Special (${totals.special_discount_name})`
                                                : 'Special discount'
                                        }
                                        value={`-৳${totals.special_discount.toFixed(2)}`}
                                        accent="text-amber-700"
                                    />
                                )}
                                {(totals.discount ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label="Invoice discount"
                                        value={`-৳${totals.discount.toFixed(2)}`}
                                        accent="text-green-600"
                                    />
                                )}
                                {(totals.coin_discount ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label="Coin discount"
                                        value={`-৳${totals.coin_discount.toFixed(2)}`}
                                    />
                                )}
                                {(totals.round_off ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label="Round off"
                                        value={`-৳${totals.round_off.toFixed(2)}`}
                                        accent="text-green-600"
                                    />
                                )}
                                {(totals.vat ?? 0) > 0.009 && (
                                    <SummaryRow
                                        label="VAT"
                                        value={`৳${totals.vat.toFixed(2)}`}
                                    />
                                )}
                                <SummaryRow
                                    label="Payment option"
                                    value={paymentTypeLabel}
                                />
                                <SummaryRow label="Status">
                                    <Badge variant="outline">{paymentStatus}</Badge>
                                </SummaryRow>
                                {(totals.settlement ?? 0) > 0.009 && (
                                    <div className="border-t border-border pt-2">
                                        <SummaryRow
                                            label={paidLabel}
                                            value={`৳${(totals.paid ?? 0).toFixed(2)}`}
                                            accent="text-green-700 dark:text-green-400"
                                        />
                                        <SummaryRow
                                            label={dueLabel}
                                            value={`৳${(totals.due ?? 0).toFixed(2)}`}
                                            accent={
                                                (totals.due ?? 0) > 0
                                                    ? 'font-semibold text-destructive'
                                                    : 'font-semibold text-green-700 dark:text-green-400'
                                            }
                                        />
                                    </div>
                                )}
                            </SummaryCard>
                        </div>

                        {exchange.comment && (
                            <p className="mt-4 text-sm text-muted-foreground">
                                Note: {exchange.comment}
                            </p>
                        )}
                    </div>
                </div>

                {can('inventory.product-exchange.delete') && (
                    <Dialog open={deleting} onOpenChange={setDeleting}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete exchange?</DialogTitle>
                                <DialogDescription>
                                    This will reverse all stock movements.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline" size="sm">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            route(
                                                'inventory.product-exchange.destroy',
                                                exchange.id,
                                            ),
                                        )
                                    }
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </>
    );
}
