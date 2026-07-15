import {
    clampQuantityInput,
    formatQty,
    CommentCard,
    DateField,
    InventoryCard,
    InventoryFormActions,
    InventoryPageHeader,
    LineItemsTable,
    ProductNameWithCode,
    SaleReturnRefundCard,
    SaleReturnSourceDiscounts,
    inputCls,
} from '@/components/inventory/inventory-form';
import { useAppToast } from '@/contexts/app-toast-context';
import { calcSaleReturnSummary, saleReturnLineStats } from '@/lib/sale-return-summary';
import { buildInitialSalePayments, computeSplitSalePayment, saleReturnPaymentRequiredError, serializeSalePayments } from '@/lib/sale-payment';
import { toDateInputValue } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Package, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';

export default function SaleReturnEdit({ saleReturn, paymentAccounts = [], paymentOnlyEdit = false }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [items, setItems] = useState(saleReturn.items ?? []);
    const [payments, setPayments] = useState(() =>
        buildInitialSalePayments(saleReturn.refund_payments ?? [], paymentAccounts),
    );

    const returnContext = {
        promotions: saleReturn.promotions ?? [],
        saleDate: saleReturn.sale_date,
    };

    // Seed from this return's own saved values (the controller reconstructs them for legacy returns),
    // never from the parent sale. Empty strings keep a field as an explicit manual 0.
    const [manualDiscounts, setManualDiscounts] = useState(() => {
        const savedInvoice = parseFloat(saleReturn.invoice_discount_value || 0);
        const savedRoundOff = parseFloat(saleReturn.saved_round_off_amount || 0);

        return {
            invoiceType: saleReturn.invoice_discount_type || 'flat',
            invoice: savedInvoice > 0 ? String(savedInvoice) : '',
            roundOff: savedRoundOff > 0 ? String(savedRoundOff.toFixed(2)) : '',
        };
    });
    const [vatPercent, setVatPercent] = useState(
        parseFloat(saleReturn.vat_percent || 0) > 0 ? String(parseFloat(saleReturn.vat_percent)) : '',
    );

    const form = useForm({
        date: toDateInputValue(saleReturn.date),
        comment: saleReturn.comment ?? '',
        paid_amount: '0',
        payment_type: '5',
        items: [],
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const returnSummary = saleReturn.sell_discounts
        ? calcSaleReturnSummary(items, saleReturn.sell_discounts, returnContext, {
              ...manualDiscounts,
              vatPercent: parseFloat(vatPercent || 0),
          })
        : null;

    const hasMissingReturnLines =
        parseFloat(saleReturn.refund_amount ?? 0) > 0.009 &&
        items.every((item) => parseInt(item.quantity || 0, 10) <= 0);

    function updateReturnQty(index, rawValue) {
        const item = items[index];
        const next = clampQuantityInput(rawValue, item.max_return_quantity, (max) => {
            toast.error(`Return quantity cannot exceed ${max} for this line.`);
        });
        const nextItems = items.map((it, i) => (i === index ? { ...it, quantity: next } : it));
        setItems(nextItems);
    }

    function handleManualDiscountChange(key, value) {
        setManualDiscounts((prev) => ({ ...prev, [key]: value }));
    }

    const returnNetAmount = paymentOnlyEdit
        ? parseFloat(saleReturn.refund_amount ?? 0)
        : (returnSummary?.netAmount ?? 0);
    const paymentRequired = returnNetAmount > 0.009;
    const { totalPaid } = computeSplitSalePayment(payments, returnNetAmount);

    function handleSubmit(e) {
        e.preventDefault();

        if (paymentOnlyEdit) {
            const paymentError = saleReturnPaymentRequiredError(payments, returnNetAmount);
            if (paymentError) {
                toast.error(paymentError);
                return;
            }

            const serializedPayments = serializeSalePayments(payments);

            form.transform((data) => ({
                ...data,
                paid_amount: String(totalPaid),
                payment_type: serializedPayments.length > 0 ? '0' : '5',
                payments: serializedPayments.length > 0 ? serializedPayments : undefined,
            }));
            form.put(route('inventory.sale-return.update', saleReturn.id), {
                preserveScroll: true,
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    if (first) toast.error(Array.isArray(first) ? first[0] : first);
                },
            });
            return;
        }

        const overLimit = items.some(
            (it) => parseInt(it.quantity || 0, 10) > parseInt(it.max_return_quantity || 0, 10),
        );
        if (overLimit) {
            toast.error('Return quantity cannot exceed the maximum for any line.');
            return;
        }

        const returnItems = items
            .filter((it) => parseFloat(it.quantity || 0) > 0)
            .map(({ sell_product_id, quantity }) => ({ sell_product_id, quantity }));

        if (returnItems.length === 0) {
            toast.error('Add return quantity for at least one line.');
            return;
        }

        if (returnSummary?.exceedsSale) {
            toast.error(
                `Return total ৳${returnSummary.netAmount.toFixed(2)} cannot exceed the sale value ৳${returnSummary.maxNetAmount.toFixed(2)}. Increase the discount or reduce the VAT.`,
            );
            return;
        }

        const paymentError = saleReturnPaymentRequiredError(payments, returnNetAmount);
        if (paymentError) {
            toast.error(paymentError);
            return;
        }

        const serializedPayments = serializeSalePayments(payments);

        form.transform((data) => ({
            ...data,
            paid_amount: String(totalPaid),
            payment_type: serializedPayments.length > 0 ? '0' : '5',
            items: returnItems,
            payments: serializedPayments.length > 0 ? serializedPayments : undefined,
            manual_invoice_discount_type: manualDiscounts.invoiceType || 'flat',
            manual_invoice_discount_value: String(parseFloat(manualDiscounts.invoice || 0)),
            manual_round_off: String(parseFloat(manualDiscounts.roundOff || 0)),
            manual_vat_percent: String(parseFloat(vatPercent || 0)),
        }));
        form.put(route('inventory.sale-return.update', saleReturn.id), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    return (
        <>
            <Head title={paymentOnlyEdit ? `Update Refund — ${saleReturn.sale_invoice ?? saleReturn.id}` : 'Edit Sale Return'} />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title={paymentOnlyEdit ? 'Update Refund Payment' : 'Edit Sale Return'}
                    subtitle={
                        paymentOnlyEdit
                            ? 'Update the cash refund without changing return lines.'
                            : 'Update return quantities and payment.'
                    }
                    icon={RotateCcw}
                    backRoute="inventory.sale-return.index"
                />

                {paymentOnlyEdit && (
                    <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                        A refund has already been recorded. Only payment details can be updated.
                    </div>
                )}

                {hasMissingReturnLines && (
                    <div className="mb-4 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        This return has no product lines saved. Set the return quantity for each product below and
                        save to restore the return.
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    {!paymentOnlyEdit && (
                    <InventoryCard title="Source Sale" icon={CalendarDays}>
                        <p className="text-xs text-muted-foreground">
                            Customer: {saleReturn.customer_name ?? 'Walk-in'} · Sale:{' '}
                            {saleReturn.sale_invoice ?? saleReturn.sell_id}
                        </p>
                        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <DateField
                                label="Return Date"
                                value={form.data.date}
                                onChange={(v) => form.setData('date', v)}
                                error={form.errors.date}
                            />
                        </div>
                    </InventoryCard>
                    )}

                    {!paymentOnlyEdit && items.length > 0 && (
                        <InventoryCard title="Return Items" icon={Package}>
                            <LineItemsTable
                                columns={[
                                    { id: 'product', header: 'Product' },
                                    { id: 'sold', header: 'Sold', align: 'right' },
                                    { id: 'returned', header: 'Returned', align: 'right' },
                                    { id: 'available', header: 'Available', align: 'right' },
                                    { id: 'price', header: 'Unit Price', align: 'right' },
                                    { id: 'qty', header: 'Return Qty', align: 'right' },
                                    { id: 'sub', header: 'Sub Total', align: 'right' },
                                ]}
                            >
                                {items.map((item, i) => {
                                    const stats = saleReturnLineStats(item);
                                    const sub = stats.returning * parseFloat(item.unit_price || 0);
                                    const overMax = stats.returning > stats.maxReturn;
                                    return (
                                        <tr key={i} className="hover:bg-muted/20">
                                            <td className="px-3 py-2">
                                                <ProductNameWithCode
                                                    name={item.product_name}
                                                    code={item.product_code}
                                                    variation={item.variation_label}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right">{formatQty(stats.sold)}</td>
                                            <td className="px-3 py-2 text-right">{formatQty(stats.returnedOnSale)}</td>
                                            <td className="px-3 py-2 text-right font-medium">
                                                {formatQty(stats.available)}
                                            </td>
                                            <td className="px-3 py-2 text-right">৳{parseFloat(item.unit_price).toFixed(2)}</td>
                                            <td className="px-2 py-1.5 text-right">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max={item.max_return_quantity}
                                                    step="1"
                                                    value={item.quantity}
                                                    onChange={(e) => updateReturnQty(i, e.target.value)}
                                                    onBlur={(e) => updateReturnQty(i, e.target.value)}
                                                    className={`${inputCls} ml-auto w-24 text-right ${overMax ? 'border-destructive' : ''}`}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold">৳{sub.toFixed(2)}</td>
                                        </tr>
                                    );
                                })}
                            </LineItemsTable>
                        </InventoryCard>
                    )}

                    {!paymentOnlyEdit && saleReturn.sell_discounts && (
                        <SaleReturnSourceDiscounts
                            sellDiscounts={saleReturn.sell_discounts}
                            returnSummary={returnSummary}
                            manualDiscounts={manualDiscounts}
                            onManualDiscountChange={handleManualDiscountChange}
                            vatPercent={vatPercent}
                            onVatPercentChange={setVatPercent}
                        />
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {!paymentOnlyEdit && (
                        <CommentCard
                            value={form.data.comment}
                            onChange={(v) => form.setData('comment', v)}
                            error={form.errors.comment}
                        />
                        )}
                        <SaleReturnRefundCard
                            Icon={RotateCcw}
                            grossAmount={paymentOnlyEdit ? parseFloat(saleReturn.refund_amount ?? 0) : (returnSummary?.netAmount ?? 0)}
                            subtotalAmount={paymentOnlyEdit ? null : (returnSummary?.grossAmount ?? null)}
                            discountAmount={paymentOnlyEdit ? 0 : (returnSummary?.discountAmount ?? 0)}
                            vatAmount={paymentOnlyEdit ? 0 : (returnSummary?.returnVat ?? 0)}
                            vatPercent={paymentOnlyEdit ? 0 : parseFloat(vatPercent || 0)}
                            parentPaymentInfo={
                                paymentOnlyEdit
                                    ? null
                                    : returnSummary
                                      ? { paid: returnSummary.parentPaid, due: returnSummary.parentDue }
                                      : null
                            }
                            payments={payments}
                            paymentAccounts={paymentAccounts}
                            onPaymentsChange={setPayments}
                            errors={form.errors}
                            exceedsSale={paymentOnlyEdit ? false : (returnSummary?.exceedsSale ?? false)}
                            maxAmount={paymentOnlyEdit ? parseFloat(saleReturn.refund_amount ?? 0) : (returnSummary?.maxNetAmount ?? null)}
                            paymentRequired={paymentRequired}
                        />
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.sale-return.index"
                        submitLabel={paymentOnlyEdit ? 'Update Refund' : 'Update Return'}
                        processing={form.processing}
                        disabled={!paymentOnlyEdit && items.length === 0}
                    />
                </form>
            </div>
        </>
    );
}
