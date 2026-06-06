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
    inputCls,
    paymentModeToType,
    paymentModeToAccountId,
    paymentTypeToMode,
} from '@/components/inventory/inventory-form';
import { ProductSearchBox } from '@/components/inventory/product-search-box';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftRight, CalendarDays, Package } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ProductExchangeEdit({ exchange, paymentAccounts = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [items, setItems] = useState(exchange.items ?? []);
    const [paymentMode, setPaymentMode] = useState(paymentTypeToMode(exchange.payment_type));
    const [replaceIndex, setReplaceIndex] = useState(null);

    const form = useForm({
        date: exchange.date ?? '',
        comment: exchange.comment ?? '',
        paid_amount: exchange.paid_amount ?? '0',
        payment_type: String(exchange.payment_type ?? '5'),
        items: [],
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const grossAmount = items.reduce(
        (s, it) => s + parseFloat(it.quantity || 0) * parseFloat(it.new_unit_price || 0),
        0,
    );
    const oldTotal = items.reduce(
        (s, it) => s + parseFloat(it.quantity || 0) * parseFloat(it.old_unit_price || 0),
        0,
    );
    const priceDifference = grossAmount - oldTotal;

    function applyReplacement(product, variation) {
        if (replaceIndex === null) return;
        setItems((prev) =>
            prev.map((it, i) =>
                i === replaceIndex
                    ? {
                          ...it,
                          new_product_id: product.id,
                          new_product_name: product.name,
                          new_variation_id: variation?.id ?? null,
                          new_variation_label: variation?.label ?? variation?.variation_data?.label ?? null,
                          new_unit_price: String(
                              variation ? (variation.price ?? variation.sale_price) : product.sale_price,
                          ),
                      }
                    : it,
            ),
        );
        setReplaceIndex(null);
    }

    function updateItem(index, field, value) {
        setItems((prev) => prev.map((it, i) => (i === index ? { ...it, [field]: value } : it)));
    }

    function updateExchangeQty(index, rawValue) {
        const item = items[index];
        const next = clampQuantityInput(rawValue, item.sold_quantity, (max) => {
            toast.error(`Exchange quantity cannot exceed ${max} for this line.`);
        });
        updateItem(index, 'quantity', next);
    }

    function handleSubmit(e) {
        e.preventDefault();

        const overLimit = items.some((it) => parseInt(it.quantity || 0, 10) > parseInt(it.sold_quantity || 0, 10));
        if (overLimit) {
            toast.error('Exchange quantity cannot exceed the sold quantity for any line.');
            return;
        }

        const exchangeItems = items
            .filter((it) => parseInt(it.quantity || 0, 10) > 0)
            .map((it) => ({
                sell_product_id: it.sell_product_id,
                product_id: it.new_product_id,
                variation_id: it.new_variation_id || null,
                unit_price: it.new_unit_price,
                quantity: it.quantity,
            }));

        if (exchangeItems.length === 0) {
            toast.error('Add exchange quantity for at least one line.');
            return;
        }

        if (exchangeItems.some((it) => !it.product_id)) {
            toast.error('Select a replacement product for each exchange line.');
            return;
        }

        form.transform((data) => ({
            ...data,
            payment_type: paymentModeToType(paymentMode),
            payment_account_id: paymentModeToAccountId(paymentMode),
            items: exchangeItems,
        }));
        form.put(route('inventory.product-exchange.update', exchange.id), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    return (
        <>
            <Head title="Edit Product Exchange" />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Edit Product Exchange"
                    subtitle="Update exchange lines and payment."
                    icon={ArrowLeftRight}
                    backRoute="inventory.product-exchange.index"
                />

                <form onSubmit={handleSubmit} className="space-y-4">
                    <InventoryCard title="Source Sale" icon={CalendarDays}>
                        <p className="text-xs text-muted-foreground">
                            Customer: {exchange.customer_name ?? 'Walk-in'} · Sale:{' '}
                            {exchange.sale_invoice ?? exchange.sell_id}
                        </p>
                        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <DateField
                                label="Exchange Date"
                                value={form.data.date}
                                onChange={(v) => form.setData('date', v)}
                                error={form.errors.date}
                            />
                        </div>
                    </InventoryCard>

                    {items.length > 0 && (
                        <InventoryCard title="Exchange Lines" icon={Package}>
                            {replaceIndex !== null && (
                                <div className="mb-3 rounded-md border border-primary/30 bg-primary/5 p-2">
                                    <Label className="mb-1 block text-xs font-medium">
                                        Select replacement for: {items[replaceIndex]?.old_product_name}
                                    </Label>
                                    <ProductSearchBox onAdd={applyReplacement} />
                                    <button
                                        type="button"
                                        className="mt-1 text-xs text-muted-foreground underline"
                                        onClick={() => setReplaceIndex(null)}
                                    >
                                        Cancel
                                    </button>
                                </div>
                            )}
                            <LineItemsTable
                                columns={[
                                    { id: 'old', header: 'Old Product' },
                                    { id: 'new', header: 'New Product' },
                                    { id: 'qty', header: 'Qty', align: 'right' },
                                    { id: 'oldPrice', header: 'Old Price', align: 'right' },
                                    { id: 'newPrice', header: 'New Price', align: 'right' },
                                    { id: 'action', header: '' },
                                ]}
                            >
                                {items.map((item, i) => (
                                    <tr key={i} className="hover:bg-muted/20">
                                        <td className="px-3 py-2">{item.old_product_name}</td>
                                        <td className="px-3 py-2">
                                            {item.new_product_name ? (
                                                <span className="font-medium text-primary">{item.new_product_name}</span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="text-xs text-primary underline"
                                                    onClick={() => setReplaceIndex(i)}
                                                >
                                                    Pick product…
                                                </button>
                                            )}
                                        </td>
                                        <td className="px-2 py-1.5 text-right">
                                            <Input
                                                type="number"
                                                min="1"
                                                max={item.sold_quantity}
                                                step="1"
                                                value={item.quantity}
                                                onChange={(e) => updateExchangeQty(i, e.target.value)}
                                                onBlur={(e) => updateExchangeQty(i, e.target.value)}
                                                className={`${inputCls} ml-auto w-20 text-right ${parseInt(item.quantity || 0, 10) > parseInt(item.sold_quantity || 0, 10) ? 'border-destructive' : ''}`}
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right text-muted-foreground">
                                            ৳{parseFloat(item.old_unit_price).toFixed(2)}
                                        </td>
                                        <td className="px-2 py-1.5 text-right">
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={item.new_unit_price}
                                                onChange={(e) => updateItem(i, 'new_unit_price', e.target.value)}
                                                className={`${inputCls} ml-auto w-24 text-right`}
                                            />
                                        </td>
                                        <td className="px-2 py-2 text-right">
                                            {item.new_product_name && (
                                                <button
                                                    type="button"
                                                    className="text-xs text-muted-foreground underline"
                                                    onClick={() => setReplaceIndex(i)}
                                                >
                                                    Change
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </LineItemsTable>
                            <div className="mt-3 flex justify-end gap-6 text-xs">
                                <span>
                                    Price difference:{' '}
                                    <strong className={priceDifference >= 0 ? 'text-primary' : 'text-destructive'}>
                                        ৳{priceDifference.toFixed(2)}
                                    </strong>
                                </span>
                            </div>
                        </InventoryCard>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <CommentCard
                            value={form.data.comment}
                            onChange={(v) => form.setData('comment', v)}
                            error={form.errors.comment}
                        />
                        <PaymentSummaryCard
                            Icon={ArrowLeftRight}
                            grossAmount={Math.abs(priceDifference)}
                            paidAmount={form.data.paid_amount}
                            onPaidAmountChange={(v) => form.setData('paid_amount', v)}
                            paymentMode={paymentMode}
                            onPaymentModeChange={setPaymentMode}
                            paymentAccounts={paymentAccounts}
                            partyLabel="Customer Account"
                            paidError={form.errors.paid_amount}
                            showDue={false}
                        />
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.product-exchange.index"
                        submitLabel="Update Exchange"
                        processing={form.processing}
                        disabled={items.length === 0 || items.some((it) => !it.new_product_id)}
                    />
                </form>
            </div>
        </>
    );
}
