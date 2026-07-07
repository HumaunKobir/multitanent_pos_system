import { useAppToast } from '@/contexts/app-toast-context';
import {
    headerActionClassName,
    InvoiceDocument,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { DocumentPaymentBreakdown } from '@/components/inventory/document-payment-breakdown';
import { route } from '@/lib/route';
import { buildSellPosPrintPayload, posPrint } from '@/lib/pos-print';
import { computeSellDisplayGross, computeSellNetAmount } from '@/lib/pos-discount';
import { computeSplitSalePayment } from '@/lib/sale-payment';
import { formatBdDate } from '@/lib/format-bd-date';
import { resolveSellEditAccess } from '@/lib/sell-summary';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowLeftRight, Edit, Receipt, ShoppingCart, Trash2, User } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
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
    const hasExchange = Boolean(sell.has_exchange);
    const lineDiscount = products.reduce((sum, line) => sum + parseFloat(line.discount ?? 0), 0);
    const grossAmount = hasExchange
        ? products.reduce((sum, line) => sum + parseFloat(line.quantity ?? 0) * parseFloat(line.unit_price ?? 0), 0)
        : parseFloat(sell.gross_amount ?? 0);
    const vat = parseFloat(sell.vat ?? 0);
    const discount = parseFloat(sell.discount ?? 0);
    const specialDiscount = parseFloat(sell.special_discount_amount ?? 0);
    const promotionDiscount = parseFloat(sell.promotion_discount_total ?? 0);
    const coinDiscount = parseFloat(sell.coin_discount_amount ?? 0);
    const roundOff = parseFloat(sell.round_off_amount ?? 0);
    const gross = computeSellDisplayGross(grossAmount, promotionDiscount, products);
    const computedNet = computeSellNetAmount({
        grossAmount,
        vat,
        discount,
        specialDiscountAmount: specialDiscount,
        coinDiscountAmount: coinDiscount,
        roundOffAmount: roundOff,
        lineDiscountTotal: lineDiscount,
    });
    const net = hasExchange ? parseFloat(sell.effective_net_amount ?? computedNet) : computedNet;
    const paid = hasExchange ? parseFloat(sell.effective_paid_amount ?? 0) : parseFloat(sell.paid_amount ?? 0);
    const paymentLines = sell.payments ?? [];
    const collectionDetails = sell.collection_payment_details ?? [];
    const posPayment = computeSplitSalePayment(paymentLines, net);
    const due = hasExchange ? parseFloat(sell.effective_due_amount ?? Math.max(0, net - paid)) : Math.max(0, net - paid);
    const change = posPayment.changeAmount;
    const hasPosPayments = paymentLines.length > 0;
    const originalSale = sell.original_sale;
    const exchangeSummary = sell.exchange_summary;
    const { canEdit } = resolveSellEditAccess({ netAmount: net, paidAmount: paid, dueAmount: due });
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
                    {hasExchange && (
                        <Badge variant="outline" className="gap-1 border-amber-300 bg-amber-50 text-amber-800">
                            <ArrowLeftRight className="size-3.5" />
                            Exchanged
                        </Badge>
                    )}
                    <button type="button" onClick={handlePosPrint} className={btnPosPrint} title="POS print">
                        <Receipt className="size-3.5" />
                        POS Print
                    </button>
                    <Can permission="inventory.sell.update">
                        {canEdit && (
                            <Button size="sm" asChild className={actionClass}>
                                <Link href={route('inventory.sell.edit', sell.id)}>
                                    <Edit className="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                        )}
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

                <DocumentPaymentBreakdown
                    partyPayments={collectionDetails}
                    partyPaymentLabel="Due Collection"
                />

                {hasPosPayments && (
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

                {hasExchange && originalSale && exchangeSummary && (
                    <div className="mx-auto mt-3 max-w-4xl space-y-3">
                        <div className="border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-xs font-semibold uppercase tracking-wide text-amber-900">Exchange History</h3>
                                <Can permission="inventory.product-exchange.view">
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={route('inventory.product-exchange.show', exchangeSummary.id)}>
                                            View {exchangeSummary.invoice_number}
                                        </Link>
                                    </Button>
                                </Can>
                            </div>
                            <p className="mb-3 text-sm text-amber-950">
                                Exchanged on {formatBdDate(exchangeSummary.date)}. Settlement difference: ৳
                                {parseFloat(exchangeSummary.price_difference ?? 0).toFixed(2)}
                            </p>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="border border-border/70 bg-white p-3">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Original Sale</p>
                                    <p className="text-sm font-medium">Net: ৳{parseFloat(originalSale.net_amount ?? 0).toFixed(2)}</p>
                                    <ul className="mt-2 space-y-1 text-sm">
                                        {(originalSale.products ?? []).map((line) => (
                                            <li key={`original-${line.id}`} className="text-muted-foreground">
                                                {line.product?.name ?? 'Product'} × {parseFloat(line.quantity ?? 0)}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                                <div className="border border-border/70 bg-white p-3">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Exchange Lines</p>
                                    <ul className="space-y-2 text-sm">
                                        {(exchangeSummary.lines ?? []).map((line) => (
                                            <li key={`exchange-${line.sell_product_id}`}>
                                                <span className="text-muted-foreground">
                                                    {line.old_product?.name ?? 'Product'} × {line.old_quantity}
                                                </span>
                                                <span className="mx-1 text-muted-foreground">→</span>
                                                <span className="font-medium">
                                                    {line.new_product?.name ?? 'Product'} × {line.new_quantity}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
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
