import {
    clampQuantityInput,
    formatQty,
    CommentCard,
    DateField,
    InventoryCard,
    InventoryFormActions,
    InventoryPageHeader,
    InvoiceLookupField,
    LineItemsTable,
    PaymentSummaryCard,
    ProductNameWithCode,
    inputCls,
    paymentModeToAccountId,
    paymentModeToType,
    roundCurrency,
} from '@/components/inventory/inventory-form';
import { Badge } from '@/components/ui/badge';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, HandCoins, Package } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function PurchaseReturnCreate({ today, paymentAccounts = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [source, setSource] = useState(null);
    const [invoiceQuery, setInvoiceQuery] = useState('');
    const [lookupError, setLookupError] = useState('');
    const [items, setItems] = useState([]);
    const [paymentMode, setPaymentMode] = useState('party');

    const form = useForm({
        purchase_id: '',
        date: today,
        comment: '',
        paid_amount: '0',
        discount: '0',
        payment_type: '5',
        payment_account_id: null,
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
    const vatPercent = parseFloat(source?.vat_percent || 0);
    const taxableAmount = Math.max(0, subtotalAmount - discountAmount);
    const vatAmount = roundCurrency(taxableAmount * vatPercent / 100);
    const grossAmount = taxableAmount + vatAmount;

    // How much of the return will be offset against the purchase's existing due.
    const purchaseDue = parseFloat(source?.due_amount || 0);
    const purchasePaid = parseFloat(source?.paid_amount || 0);
    const purchaseNet = parseFloat(source?.net_amount || 0);
    const dueOffset = source ? Math.min(purchaseDue, grossAmount) : 0;
    // Effective due for party mode (paid=0 in party mode).
    const effectiveReturnDue = Math.max(0, grossAmount - dueOffset);

    async function lookupPurchase() {
        setLookupError('');
        const res = await fetch(`${route('api.purchases.lookup')}?invoice=${encodeURIComponent(invoiceQuery)}`, {
            credentials: 'include',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        if (!res.ok) {
            setLookupError(json.message ?? 'Purchase not found.');
            return;
        }
        setSource(json);
        const lines = json.items
            .filter((i) => i.max_return_quantity > 0)
            .map((i) => ({
                purchase_product_id: i.purchase_product_id,
                product_name: i.product_name,
                product_code: i.product_code,
                max_return_quantity: i.max_return_quantity,
                unit_price: i.unit_price,
                quantity: String(i.max_return_quantity),
            }));
        setItems(lines);
        form.setData({ ...form.data, purchase_id: String(json.id), discount: String(json.discount ?? 0) });
    }

    function updateItem(index, field, value) {
        setItems((prev) => prev.map((it, i) => (i === index ? { ...it, [field]: value } : it)));
    }

    function updateReturnQty(index, rawValue) {
        const item = items[index];
        const next = clampQuantityInput(rawValue, item.max_return_quantity, (max) => {
            toast.error(`Return quantity cannot exceed ${max} for this line.`);
        });
        updateItem(index, 'quantity', next);
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
        form.post(route('inventory.purchase-return.store'), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    return (
        <>
            <Head title="Purchase Return" />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Purchase Return"
                    subtitle="Return items to supplier and adjust stock."
                    icon={HandCoins}
                    backRoute="inventory.purchase-return.index"
                />

                <form onSubmit={handleSubmit} className="space-y-4">
                    <InventoryCard title="Source Purchase" icon={CalendarDays}>
                        <InvoiceLookupField
                            label="Purchase Invoice"
                            placeholder="INVP00000001 or 1"
                            value={invoiceQuery}
                            onChange={setInvoiceQuery}
                            onSearch={lookupPurchase}
                            error={lookupError || form.errors.purchase_id}
                            hint={
                                source
                                    ? `Supplier: ${source.supplier?.company_name && source.supplier?.name ? `${source.supplier.company_name} (${source.supplier.name})` : source.supplier?.company_name || source.supplier?.name || '—'} · ${source.invoice_number}`
                                    : 'Enter invoice number and press Enter or click Load.'
                            }
                        />

                        {source && (
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
                            dueOffsetAmount={source && dueOffset > 0 ? dueOffset : null}
                            paymentHint={
                                source && dueOffset > 0
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
                        submitLabel="Save Return"
                        processing={form.processing}
                        disabled={!form.data.purchase_id || items.length === 0}
                    />
                </form>
            </div>
        </>
    );
}
