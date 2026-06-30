import { useAppToast } from '@/contexts/app-toast-context';
import {
    headerActionClassName,
    InvoiceDocument,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { DocumentPaymentBreakdown } from '@/components/inventory/document-payment-breakdown';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, HandCoins, Trash2, User } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

export default function PurchaseShow({ purchase }) {
    const { flash, logo } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = purchase.invoice_number ?? `INVP${String(purchase.id).padStart(8, '0')}`;
    const gross = parseFloat(purchase.gross_amount ?? 0);
    const vat = parseFloat(purchase.vat ?? 0);
    const discount = parseFloat(purchase.discount ?? 0);
    const net = gross + vat - discount;
    const paid = parseFloat(purchase.paid_amount ?? 0);
    const due = parseFloat(purchase.due_amount ?? Math.max(0, net - paid));
    const actionClass = headerActionClassName();

    function handleDelete() {
        router.delete(route('inventory.purchase.destroy', purchase.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`Purchase — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <InvoiceShowHeader icon={HandCoins} title="Purchase Invoice" invoiceNumber={invoiceNumber}>
                    {purchase.can_edit && (
                        <Can permission="inventory.purchase.update">
                            <Button size="sm" asChild className={actionClass}>
                                <Link href={route('inventory.purchase.edit', purchase.id)}>
                                    <Edit className="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                        </Can>
                    )}
                    <Can permission="inventory.purchase.delete">
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
                        <Link href={route('inventory.purchase.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </InvoiceShowHeader>

                <InvoiceDocument
                    docTitle="Purchase Invoice"
                    invoiceNumber={invoiceNumber}
                    date={purchase.date}
                    branchName={purchase.branch?.name}
                    logoUrl={purchase.branch?.logo_url || logo}
                    items={purchase.purchase_products ?? []}
                    totals={{ gross, vat, discount, net, paid, due }}
                    comment={purchase.comment}
                    isReturned={!!purchase.is_fully_returned}
                    partySection={
                        <PartyInfoCard
                            icon={User}
                            label="Supplier"
                            name={purchase.supplier?.name}
                            phone={purchase.supplier?.phone}
                            companyName={purchase.supplier?.company_name}
                            address={purchase.supplier?.address}
                        />
                    }
                />

                <DocumentPaymentBreakdown
                    directPayment={purchase.direct_payment}
                    directPaymentLabel="Purchase Payment"
                    partyPayments={purchase.supplier_payment_details ?? []}
                    partyPaymentLabel="Supplier Payment"
                />

                {can('inventory.purchase.delete') && (
                    <Dialog open={deleting} onOpenChange={setDeleting}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete purchase?</DialogTitle>
                                <DialogDescription>
                                    This will permanently delete the purchase and roll back stock changes if possible.
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
