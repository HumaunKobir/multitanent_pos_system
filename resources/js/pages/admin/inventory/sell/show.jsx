import { useAppToast } from '@/contexts/app-toast-context';
import {
    headerActionClassName,
    InvoiceDocument,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { route } from '@/lib/route';
import { buildSellPosPrintPayload, posPrint } from '@/lib/pos-print';
import { computeSellDisplayGross, computeSellNetAmount } from '@/lib/pos-discount';
import { computeSplitSalePayment } from '@/lib/sale-payment';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, Receipt, ShoppingCart, Trash2, User } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

const btnPosPrint =
    'inline-flex h-8 flex-shrink-0 items-center justify-center gap-1.5 rounded-none border border-cyan-200 bg-white/95 px-2.5 text-xs font-medium text-cyan-700 shadow-sm backdrop-blur-[1px] transition-all duration-200 hover:-translate-y-0.5 hover:bg-cyan-50 hover:shadow-md';

export default function SellShow({ sell }) {
    const { flash, logo, siteName, contact } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);
    const autoPrintedRef = useRef(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = sell.invoice_number ?? `INVS${String(sell.id).padStart(8, '0')}`;
    const products = sell.products ?? [];
    const lineDiscount = products.reduce((sum, line) => sum + parseFloat(line.discount ?? 0), 0);
    const grossAmount = parseFloat(sell.gross_amount ?? 0);
    const vat = parseFloat(sell.vat ?? 0);
    const discount = parseFloat(sell.discount ?? 0);
    const specialDiscount = parseFloat(sell.special_discount_amount ?? 0);
    const promotionDiscount = parseFloat(sell.promotion_discount_total ?? 0);
    const coinDiscount = parseFloat(sell.coin_discount_amount ?? 0);
    const roundOff = parseFloat(sell.round_off_amount ?? 0);
    const gross = computeSellDisplayGross(grossAmount, promotionDiscount, products);
    const net = computeSellNetAmount({
        grossAmount,
        vat,
        discount,
        specialDiscountAmount: specialDiscount,
        coinDiscountAmount: coinDiscount,
        roundOffAmount: roundOff,
        lineDiscountTotal: lineDiscount,
    });
    const paid = parseFloat(sell.paid_amount ?? 0);
    const paymentLines = sell.payments ?? [];
    const { dueAmount, changeAmount } = computeSplitSalePayment(paymentLines, net);
    const due = dueAmount;
    const change = changeAmount;
    const actionClass = headerActionClassName();

    const handlePosPrint = useCallback(() => {
        posPrint(
            buildSellPosPrintPayload(sell, {
                companyName: sell.branch?.name || siteName || 'Coolness Point',
                companyAddress: sell.branch?.address || contact?.address || '',
                companyPhone: sell.branch?.phone || contact?.phone || '',
                companyEmail: contact?.email || '',
                logoUrl: sell.branch?.logo_url || logo,
                branchName: sell.branch?.name || '',
                change: flash?.pos_change ?? change,
                termsAndConditions: sell.branch?.pos_terms_and_conditions ?? '',
            }),
        );
    }, [sell, logo, siteName, contact, flash?.pos_change, change]);

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
                    <button type="button" onClick={handlePosPrint} className={btnPosPrint} title="POS print">
                        <Receipt className="size-3.5" />
                        POS Print
                    </button>
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
                    logoUrl={sell.branch?.logo_url || logo}
                    items={sell.products ?? []}
                    totals={{
                        gross,
                        vat,
                        discount,
                        discountType: sell.discount_type,
                        discountValue: sell.discount_value,
                        specialDiscount,
                        specialDiscountName: sell.special_discount?.name,
                        specialDiscountType: sell.special_discount?.discount_type,
                        specialDiscountValue: sell.special_discount?.discount_value,
                        promotionDiscount,
                        coinDiscount,
                        coinsRedeemed: parseFloat(sell.coins_redeemed ?? 0),
                        coinsEarned: parseFloat(sell.coins_earned ?? 0),
                        roundOff,
                        lineDiscount,
                        net,
                        paid,
                        due,
                        change,
                    }}
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

                {paymentLines.length > 0 && (
                    <div className="mx-auto mt-3 max-w-4xl border border-blue-200 bg-white p-3 shadow-sm">
                        <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-950">Payment Breakdown</h3>
                        <div className="space-y-1 text-sm">
                            {paymentLines.map((line) => (
                                <div key={line.id ?? `${line.payment_account_id}-${line.amount}`} className="flex items-center justify-between gap-3 border-b border-border/60 py-1 last:border-0">
                                    <span className="text-muted-foreground">
                                        {line.payment_account?.code} — {line.payment_account?.name}
                                    </span>
                                    <span className="font-medium tabular-nums">৳{parseFloat(line.amount ?? 0).toFixed(2)}</span>
                                </div>
                            ))}
                            {change > 0 && (
                                <div className="flex items-center justify-between gap-3 border-t border-border/60 pt-2 font-semibold text-amber-700">
                                    <span>Change Returned</span>
                                    <span className="tabular-nums">৳{change.toFixed(2)}</span>
                                </div>
                            )}
                        </div>
                    </div>
                )}

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
