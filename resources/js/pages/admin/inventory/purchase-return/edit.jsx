import {
    clampQuantityInput,
    formatQty,
    CommentCard,
    DateField,
    InventoryCard,
    InventoryFormActions,
    InventoryPageHeader,
    LineItemsTable,
    PaymentSummaryCard,
    ProductNameWithCode,
    inputCls,
    paymentModeToAccountId,
    paymentModeToType,
    paymentTypeToMode,
    roundCurrency,
} from '@/components/inventory/inventory-form';
import { Badge } from '@/components/ui/badge';
import { useAppToast } from '@/contexts/app-toast-context';
import { toDateInputValue } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, HandCoins, Package } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';

export default function PurchaseReturnEdit({ purchaseReturn, paymentAccounts = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [items, setItems] = useState(purchaseReturn.items ?? []);
    const [paymentMode, setPaymentMode] = useState(
        paymentTypeToMode(purchaseReturn.payment_type, purchaseReturn.payment_account_id),
    );

    const form = useForm({
        date: toDateInputValue(purchaseReturn.date),
        comment: purchaseReturn.comment ?? '',
        paid_amount: purchaseReturn.paid_amount ?? '0',
        discount: String(purchaseReturn.discount ?? '0'),
        payment_type: String(purchaseReturn.payment_type ?? '5'),
        payment_account_id: purchaseReturn.payment_account_id ?? null,
        items: [],
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const subtotalAmount = items.reduce(
        (s, it) => s + parseFloat(it.quantity || 0) * parseFloat(it.unit_price || 0),
        0,
    );
    const discountAmount = parseFloat(form.data.discount || 0);
    const vatPercent = parseFloat(purchaseReturn?.purchase_vat_percent || 0);
    const vatAmount = roundCurrency(subtotalAmount * vatPercent / 100);
    const grossAmount = subtotalAmount + vatAmount - discountAmount;

    // Purchase payment info (restored due = original due before this return was applied).
    const purchaseDue = parseFloat(purchaseReturn.purchase_due_amount || 0);
    const purchasePaid = parseFloat(purchaseReturn.purchase_paid_amount || 0);
    const purchaseNet = parseFloat(purchaseReturn.purchase_gross_amount || 0)
        + parseFloat(purchaseReturn.purchase_vat || 0)
        - parseFloat(purchaseReturn.purchase_discount || 0);
    const dueOffset = Math.min(purchaseDue, grossAmount);
    const effectiveReturnDue = Math.max(0, grossAmount - dueOffset);

    function updateReturnQty(index, rawValue) {
        const item = items[index];
        const next = clampQuantityInput(rawValue, item.max_return_quantity, (max) => {
            toast.error(`Return quantity cannot exceed ${max} for this line.`);
        });
        setItems((prev) => prev.map((it, i) => (i === index ? { ...it, quantity: next } : it)));
    }

    function handleSubmit(e) {
        e.preventDefault();

        const overLimit = items.some(
            (it) => parseFloat(it.quantity || 0) > parseFloat(it.max_return_quantity),
        );
        if (overLimit) {
            toast.error('Return quantity cannot exceed the maximum for any line.');
            return;
        }

        const returnItems = items
            .filter((it) => parseInt(it.quantity || 0, 10) > 0)
            .map(({ purchase_product_id, quantity }) => ({
                purchase_product_id,
                quantity,
            }));

        if (returnItems.length === 0) {
            toast.error('Add return quantity for at least one line.');
            return;
        }

        form.transform((data) => ({
            ...data,
            discount: String(parseFloat(data.discount || 0) || 0),
            payment_type: paymentModeToType(paymentMode),
            payment_account_id: paymentModeToAccountId(paymentMode),
            items: returnItems,
        }));
        form.put(route('inventory.purchase-return.update', purchaseReturn.id), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    return (
        <>
            <Head title={`Edit Purchase Return — ${purchaseReturn.invoice_number}`} />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Edit Purchase Return"
                    subtitle={purchaseReturn.invoice_number}
                    icon={HandCoins}
                    backRoute="inventory.purchase-return.index"
                />

                <form onSubmit={handleSubmit} className="space-y-4">
                    <InventoryCard title="Source Purchase" icon={CalendarDays}>
                        <p className="text-xs text-muted-foreground">
                            Supplier: {purchaseReturn.supplier_name ?? '—'} · Purchase:{' '}
                            {purchaseReturn.purchase_invoice ?? purchaseReturn.purchase_id}
                        </p>

                        {purchaseNet > 0 && (
                            <div className="mt-3 flex flex-wrap items-center gap-3 rounded-md border border-border bg-muted/40 px-3 py-2 text-xs">
                                <span className="font-medium text-foreground">Purchase Payment</span>
                                <span className="text-muted-foreground">Net ৳{purchaseNet.toFixed(2)}</span>
                                <span className="text-green-700 dark:text-green-400">Paid ৳{purchasePaid.toFixed(2)}</span>
                                {purchaseDue > 0 ? (
                                    <span className="font-semibold text-red-600">Due ৳{purchaseDue.toFixed(2)}</span>
                                ) : (
                                    <span className="text-green-700 dark:text-green-400">No Due</span>
                                )}
                                <Badge
                                    className={
                                        purchaseDue <= 0
                                            ? 'bg-green-600 text-white'
                                            : purchasePaid <= 0
                                              ? 'bg-red-600 text-white'
                                              : 'bg-orange-500 text-white'
                                    }
                                >
                                    {purchaseDue <= 0 ? 'Paid' : purchasePaid <= 0 ? 'Unpaid' : 'Partially Paid'}
                                </Badge>
                            </div>
                        )}

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
                                                {item.max_return_quantity}
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

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <CommentCard
                            value={form.data.comment}
                            onChange={(v) => form.setData('comment', v)}
                            error={form.errors.comment}
                        />
                        <PaymentSummaryCard
                            Icon={HandCoins}
                            grossAmount={grossAmount}
                            subtotalAmount={subtotalAmount}
                            discountAmount={form.data.discount}
                            vatAmount={vatAmount}
                            vatPercent={vatPercent}
                            onDiscountAmountChange={(v) => form.setData('discount', v)}
                            discountError={form.errors.discount}
                            paidAmount={form.data.paid_amount}
                            onPaidAmountChange={(v) => form.setData('paid_amount', v)}
                            paymentMode={paymentMode}
                            onPaymentModeChange={setPaymentMode}
                            paymentAccounts={paymentAccounts}
                            partyLabel="Supplier Account"
                            paidError={form.errors.paid_amount}
                            dueOffsetAmount={purchaseNet > 0 && dueOffset > 0 ? dueOffset : null}
                            paymentHint={
                                purchaseNet > 0 && dueOffset > 0
                                    ? paymentMode === 'party'
                                        ? effectiveReturnDue > 0
                                            ? `Purchase due ৳${purchaseDue.toFixed(2)} is reversed. Remaining ৳${effectiveReturnDue.toFixed(2)} stays as return due on the supplier account.`
                                            : `Purchase due ৳${purchaseDue.toFixed(2)} fully reversed — no new return due is created.`
                                        : `Purchase due ৳${purchaseDue.toFixed(2)} is reversed from the return total. Enter the cash amount received from the supplier.`
                                    : undefined
                            }
                        />
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.purchase-return.index"
                        submitLabel="Update Return"
                        processing={form.processing}
                        disabled={items.length === 0}
                    />
                </form>
            </div>
        </>
    );
}
