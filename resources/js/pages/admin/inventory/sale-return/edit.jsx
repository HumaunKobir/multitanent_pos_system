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
import { computeSplitSalePayment, serializeSalePayments, splitPaymentValidationError } from '@/lib/sale-payment';
import { calcSaleReturnSummary } from '@/lib/sale-return-summary';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Package, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';

export default function SaleReturnEdit({ saleReturn, paymentAccounts = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [items, setItems] = useState(saleReturn.items ?? []);

    const returnContext = {
        promotions: saleReturn.promotions ?? [],
        saleDate: saleReturn.sale_date,
        coinSettings: saleReturn.coin_settings,
    };

    const initialManualDiscounts = (() => {
        const sd = saleReturn.sell_discounts ?? {};
        return {
            invoice: parseFloat(sd.invoice_discount || 0) > 0 ? String(parseFloat(sd.invoice_discount).toFixed(2)) : null,
            special: parseFloat(sd.special_discount_amount || 0) > 0 ? String(parseFloat(sd.special_discount_amount).toFixed(2)) : null,
            roundOff: parseFloat(sd.round_off_amount || 0) > 0 ? String(parseFloat(sd.round_off_amount).toFixed(2)) : null,
        };
    })();

    const [manualDiscounts, setManualDiscounts] = useState(initialManualDiscounts);

    const initialPayments = saleReturn.refund_payments?.length
        ? saleReturn.refund_payments.map((p) => ({
              payment_account_id: String(p.payment_account_id),
              amount: String(parseFloat(p.amount || 0).toFixed(2)),
          }))
        : [{ payment_account_id: paymentAccounts[0] ? String(paymentAccounts[0].id) : '', amount: String(parseFloat(saleReturn.paid_amount || 0).toFixed(2)) }];

    const form = useForm({
        date: saleReturn.date ?? '',
        comment: saleReturn.comment ?? '',
        paid_amount: saleReturn.paid_amount ?? '0',
        payment_type: String(saleReturn.payment_type ?? '0'),
        payments: initialPayments,
        items: [],
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const returnSummary = saleReturn.sell_discounts
        ? calcSaleReturnSummary(items, saleReturn.sell_discounts, returnContext, manualDiscounts)
        : null;

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

    function handlePaymentsChange(payments) {
        const maxRefund = returnSummary?.suggestedPaid ?? 0;
        const { totalPaid } = computeSplitSalePayment(payments, maxRefund);
        form.setData({
            ...form.data,
            payments,
            paid_amount: totalPaid.toFixed(2),
            payment_type: totalPaid > 0 ? '0' : '5',
        });
    }

    function handleSubmit(e) {
        e.preventDefault();

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

        const paymentError = splitPaymentValidationError(form.data.payments);
        if (paymentError) {
            toast.error(paymentError);
            return;
        }

        const maxRefund = returnSummary?.suggestedPaid ?? 0;
        const { totalPaid } = computeSplitSalePayment(form.data.payments, maxRefund);
        const serializedPayments = serializeSalePayments(form.data.payments);

        form.transform((data) => ({
            ...data,
            paid_amount: String(totalPaid),
            payment_type: totalPaid > 0 ? '0' : '5',
            payments: serializedPayments.length > 0 ? serializedPayments : undefined,
            items: returnItems,
            manual_invoice_discount: manualDiscounts.invoice != null ? String(parseFloat(manualDiscounts.invoice || 0)) : undefined,
            manual_special_discount: manualDiscounts.special != null ? String(parseFloat(manualDiscounts.special || 0)) : undefined,
            manual_round_off: manualDiscounts.roundOff != null ? String(parseFloat(manualDiscounts.roundOff || 0)) : undefined,
            manual_coin_discount: manualDiscounts.coin != null && manualDiscounts.coin !== '' ? String(manualDiscounts.coin) : undefined,
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
            <Head title="Edit Sale Return" />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Edit Sale Return"
                    subtitle="Update return quantities and payment."
                    icon={RotateCcw}
                    backRoute="inventory.sale-return.index"
                />

                <form onSubmit={handleSubmit} className="space-y-4">
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

                    {items.length > 0 && (
                        <InventoryCard title="Return Items" icon={Package}>
                            <LineItemsTable
                                columns={[
                                    { id: 'product', header: 'Product' },
                                    { id: 'max', header: 'Max', align: 'right' },
                                    { id: 'price', header: 'Unit Price', align: 'right' },
                                    { id: 'qty', header: 'Return Qty', align: 'right' },
                                    { id: 'sub', header: 'Sub Total', align: 'right' },
                                ]}
                            >
                                {items.map((item, i) => {
                                    const sub = parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0);
                                    const overMax =
                                        parseFloat(item.quantity || 0) > parseFloat(item.max_return_quantity);
                                    return (
                                        <tr key={i} className="hover:bg-muted/20">
                                            <td className="px-3 py-2">
                                                <ProductNameWithCode name={item.product_name} code={item.product_code} />
                                            </td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                {formatQty(item.max_return_quantity)}
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

                    {saleReturn.sell_discounts && (
                        <SaleReturnSourceDiscounts
                            sellDiscounts={saleReturn.sell_discounts}
                            returnSummary={returnSummary}
                            manualDiscounts={manualDiscounts}
                            onManualDiscountChange={handleManualDiscountChange}
                        />
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <CommentCard
                            value={form.data.comment}
                            onChange={(v) => form.setData('comment', v)}
                            error={form.errors.comment}
                        />
                        <SaleReturnRefundCard
                            Icon={RotateCcw}
                            grossAmount={returnSummary?.netAmount ?? 0}
                            subtotalAmount={returnSummary?.grossAmount ?? null}
                            discountAmount={returnSummary?.discountAmount ?? 0}
                            parentPaymentInfo={
                                returnSummary
                                    ? { paid: returnSummary.parentPaid, due: returnSummary.parentDue }
                                    : null
                            }
                            maxRefundAmount={returnSummary?.suggestedPaid ?? 0}
                            payments={form.data.payments}
                            onPaymentsChange={handlePaymentsChange}
                            paymentAccounts={paymentAccounts}
                            errors={form.errors}
                        />
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.sale-return.index"
                        submitLabel="Update Return"
                        processing={form.processing}
                        disabled={items.length === 0}
                    />
                </form>
            </div>
        </>
    );
}
