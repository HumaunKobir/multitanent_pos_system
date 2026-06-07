import { useAppToast } from '@/contexts/app-toast-context';
import {
    headerActionClassName,
    InvoiceDocument,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { route } from '@/lib/route';
import { buildSellPosPrintPayload, posPrint } from '@/lib/pos-print';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, Receipt, ShoppingCart, Trash2, User } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

export default function SellShow({ sell }) {
    const { flash, logo } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);
    const autoPrintedRef = useRef(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = sell.invoice_number ?? `INVS${String(sell.id).padStart(8, '0')}`;
    const lineDiscount = (sell.products ?? []).reduce((sum, line) => sum + parseFloat(line.discount ?? 0), 0);
    const gross = parseFloat(sell.gross_amount ?? 0);
    const vat = parseFloat(sell.vat ?? 0);
    const discount = parseFloat(sell.discount ?? 0);
    const net = gross + vat - discount - lineDiscount;
    const paid = parseFloat(sell.paid_amount ?? 0);
    const due = Math.max(0, net - paid);
    const actionClass = headerActionClassName();

    const handlePosPrint = useCallback(() => {
        posPrint(
            buildSellPosPrintPayload(sell, {
                companyName: sell.branch?.name || 'Coolness Point',
                logoUrl: logo,
                branchName: sell.branch?.name,
            }),
        );
    }, [sell, logo]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const shouldAutoPrint = params.get('pos_print') === '1' || params.get('pos_print') === 'true';

        if (!shouldAutoPrint || autoPrintedRef.current) {
            return;
        }

        autoPrintedRef.current = true;
        const timer = setTimeout(() => handlePosPrint(), 500);

        return () => clearTimeout(timer);
    }, [handlePosPrint]);

    function handleDelete() {
        router.delete(route('inventory.sell.destroy', sell.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`Sale — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <InvoiceShowHeader icon={ShoppingCart} title="Sale Invoice" invoiceNumber={invoiceNumber}>
                    <Button size="sm" onClick={handlePosPrint} className={actionClass}>
                        <Receipt className="size-3.5" />
                        POS Print
                    </Button>
                    <Can permission="inventory.sell.update">
                        <Button size="sm" asChild className={actionClass}>
                            <Link href={route('inventory.sell.edit', sell.id)}>
                                <Edit className="size-3.5" />
                                Edit
                            </Link>
                        </Button>
                    </Can>
                    <Can permission="inventory.sell.delete">
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
                        <Link href={route('inventory.sell.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </InvoiceShowHeader>

                <InvoiceDocument
                    docTitle="Sale Invoice"
                    invoiceNumber={invoiceNumber}
                    date={sell.date}
                    branchName={sell.branch?.name}
                    items={sell.products ?? []}
                    totals={{ gross, vat, discount, lineDiscount, net, paid, due }}
                    comment={sell.comment}
                    partySection={
                        <PartyInfoCard
                            icon={User}
                            label="Customer"
                            name={sell.customer?.name}
                            phone={sell.customer?.phone}
                            address={sell.customer?.address}
                            emptyText="Walk-in Customer"
                        />
                    }
                />

                {can('inventory.sell.delete') && (
                    <Dialog open={deleting} onOpenChange={setDeleting}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete sale?</DialogTitle>
                                <DialogDescription>
                                    This will permanently delete the sale and restore stock.
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
