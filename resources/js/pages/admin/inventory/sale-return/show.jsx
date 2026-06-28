import {
    headerActionClassName,
    InvoiceDocument,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, RotateCcw, Trash2, User } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

export default function SaleReturnShow({ saleReturn, totals }) {
    const { flash, logo } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const invoiceNumber = saleReturn.invoice_number ?? `INVSR${String(saleReturn.id).padStart(8, '0')}`;
    const refundPayments = saleReturn.payments ?? [];
    const actionClass = headerActionClassName();

    function handleDelete() {
        router.delete(route('inventory.sale-return.destroy', saleReturn.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`Sale Return — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <InvoiceShowHeader icon={RotateCcw} title="Sale Return" invoiceNumber={invoiceNumber}>
                    <Can permission="inventory.sale-return.update">
                        <Button size="sm" asChild className={actionClass}>
                            <Link href={route('inventory.sale-return.edit', saleReturn.id)}>
                                <Edit className="size-3.5" />
                                Edit
                            </Link>
                        </Button>
                    </Can>
                    <Can permission="inventory.sale-return.delete">
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
                        <Link href={route('inventory.sale-return.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </InvoiceShowHeader>

                <InvoiceDocument
                    docTitle="Sale Return"
                    invoiceNumber={invoiceNumber}
                    date={saleReturn.date}
                    branchName={saleReturn.branch?.name}
                    logoUrl={saleReturn.branch?.logo_url || logo}
                    items={saleReturn.products ?? []}
                    totals={{
                        gross: totals?.gross ?? parseFloat(saleReturn.gross_amount ?? 0),
                        vat: totals?.vat ?? parseFloat(saleReturn.vat_amount ?? 0),
                        discount: totals?.invoice_discount ?? 0,
                        discountType: totals?.invoice_discount_type,
                        discountValue: totals?.invoice_discount_value,
                        promotionDiscount: totals?.promotion_discount ?? 0,
                        roundOff: totals?.round_off ?? 0,
                        lineDiscount: totals?.line_discount ?? 0,
                        net: totals?.net ?? parseFloat(saleReturn.net_amount ?? 0),
                        paid: totals?.refund ?? parseFloat(saleReturn.paid_amount ?? 0),
                        due: totals?.due_refund ?? 0,
                    }}
                    comment={saleReturn.comment}
                    partySection={
                        <PartyInfoCard
                            icon={User}
                            label="Customer"
                            name={saleReturn.customer?.name}
                            phone={saleReturn.customer?.phone}
                            address={saleReturn.customer?.address}
                            emptyText="Walk-in Customer"
                        />
                    }
                />

                {refundPayments.length > 0 && (
                    <div className="mx-auto mt-3 max-w-4xl border border-blue-200 bg-white p-3 shadow-sm">
                        <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-950">Refund Breakdown</h3>
                        <div className="space-y-1 text-sm">
                            {refundPayments.map((line) => (
                                <div
                                    key={line.id ?? `${line.payment_account_id}-${line.amount}`}
                                    className="flex items-center justify-between gap-3 border-b border-border/60 py-1 last:border-0"
                                >
                                    <span className="text-muted-foreground">
                                        {line.payment_account?.code} — {line.payment_account?.name}
                                    </span>
                                    <span className="font-medium tabular-nums">৳{parseFloat(line.amount ?? 0).toFixed(2)}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {can('inventory.sale-return.delete') && (
                    <Dialog open={deleting} onOpenChange={setDeleting}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete sale return?</DialogTitle>
                                <DialogDescription>
                                    This will permanently delete the sale return and reverse the restored stock.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="mt-4 gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline" size="sm">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>
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
