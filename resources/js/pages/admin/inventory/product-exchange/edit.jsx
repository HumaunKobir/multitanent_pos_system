import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftRight, CalendarDays, Package } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
    paymentModeToType,
    paymentModeToAccountId,
    paymentTypeToMode,
} from '@/components/inventory/inventory-form';
import { ProductSearchBox } from '@/components/inventory/product-search-box';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppToast } from '@/contexts/app-toast-context';
import { computeDiscountAmount } from '@/lib/pos-discount';
import { applyPromotionsToCart } from '@/lib/pos-promotion';
import { route } from '@/lib/route';

export default function ProductExchangeEdit({
    exchange,
    sellDiscounts = null,
    paymentAccounts = [],
    promotions = [],
}) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [items, setItems] = useState(exchange.items ?? []);
    const [paymentMode, setPaymentMode] = useState(() => {
        const mode = paymentTypeToMode(
            exchange.payment_type,
            exchange.payment_account_id,
        );

        if (mode === 'cash-0' && paymentAccounts.length > 0) {
            return `cash-${paymentAccounts[0].id}`;
        }

        return mode;
    });
    const [replaceIndex, setReplaceIndex] = useState(null);

    const form = useForm({
        date: exchange.date ?? '',
        comment: exchange.comment ?? '',
        paid_amount: exchange.paid_amount ?? '0',
        payment_type: String(exchange.payment_type ?? '5'),
        items: [],
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    // Apply promotions on the new products (client-side preview; server re-resolves authoritatively).
    const promoResult = useMemo(() => {
        const promoItems = items
            .filter((it) => it.new_product_id)
            .map((it) => ({
                product_id: Number(it.new_product_id),
                variation_id: it.new_variation_id ? Number(it.new_variation_id) : null,
                category_id: null,
                brand_id: null,
                quantity: parseFloat(it.quantity || 0),
                unit_price: parseFloat(it.new_unit_price || 0),
                original_unit_price: parseFloat(it.new_unit_price || 0),
                discount: 0,
            }));

        if (promoItems.length === 0) {
            return { items: [], promotion_discount_total: 0 };
        }

        return applyPromotionsToCart(promoItems, promotions, form.data.date);
    }, [items, promotions, form.data.date]);

    const promoLineMap = useMemo(() => {
        const map = {};
        promoResult.items.forEach((it) => {
            map[Number(it.product_id)] = it;
        });
        return map;
    }, [promoResult]);

    const grossAmount = items.reduce((s, it) => {
        if (!it.new_product_id) {
            return s;
        }
        const promoLine = promoLineMap[Number(it.new_product_id)];
        const catalogPrice = parseFloat(it.new_unit_price || 0);
        const effectivePrice = promoLine
            ? Math.min(catalogPrice, parseFloat(promoLine.unit_price || 0))
            : catalogPrice;
        return s + parseFloat(it.quantity || 0) * effectivePrice;
    }, 0);

    const oldTotal = items.reduce(
        (s, it) =>
            s +
            parseFloat(it.quantity || 0) * parseFloat(it.old_unit_price || 0),
        0,
    );

    const promotionDiscountTotal = parseFloat(promoResult.promotion_discount_total || 0);

    // Mirror the sale's discount rates on the new gross (no manual inputs).
    const invoiceDiscountType = sellDiscounts?.invoice_discount_type ?? 'flat';
    const invoiceDiscountValue = parseFloat(sellDiscounts?.invoice_discount_value || 0);
    const invoiceDiscountAmount = sellDiscounts
        ? computeDiscountAmount(invoiceDiscountType, invoiceDiscountValue, grossAmount)
        : 0;

    const specialDiscountAmount = sellDiscounts
        ? parseFloat(sellDiscounts.special_discount_amount || 0) > 0
            ? Math.min(
                  parseFloat(sellDiscounts.special_discount_amount || 0),
                  grossAmount,
              )
            : 0
        : 0;

    const vatPercent = sellDiscounts && parseFloat(sellDiscounts.gross_amount || 0) > 0
        ? (parseFloat(sellDiscounts.vat || 0) / parseFloat(sellDiscounts.gross_amount)) * 100
        : 0;
    const vatAmount = grossAmount * (vatPercent / 100);

    const coinsRedeemed = sellDiscounts ? parseFloat(sellDiscounts.coins_redeemed || 0) : 0;
    const coinDiscountAmount = exchange.coin_discount_amount
        ? parseFloat(exchange.coin_discount_amount || 0)
        : 0;

    const netBeforeRoundOff = Math.max(0, grossAmount + vatAmount - invoiceDiscountAmount - specialDiscountAmount - coinDiscountAmount);
    const saleRoundOff = parseFloat(sellDiscounts?.round_off_amount || 0);
    const roundOffAmount = Math.min(saleRoundOff, netBeforeRoundOff);

    const netNewAmount = Math.max(0, netBeforeRoundOff - roundOffAmount);
    const priceDifference = netNewAmount - oldTotal;

    useEffect(() => {
        form.setData('paid_amount', String(Math.max(0, priceDifference)));
    }, [priceDifference]);

    function applyReplacement(product) {
        if (replaceIndex === null) {
            return;
        }

        setItems((prev) =>
            prev.map((it, i) =>
                i === replaceIndex
                    ? {
                          ...it,
                          new_product_id: product.product_id,
                          new_product_name: product.product_name,
                          new_product_code: product.product_code,
                          new_variation_id: product.variation_id ?? null,
                          new_variation_label: product.variation_label ?? null,
                          new_unit_price: String(product.unit_price ?? 0),
                      }
                    : it,
            ),
        );
        setReplaceIndex(null);
    }

    function updateItem(index, field, value) {
        setItems((prev) =>
            prev.map((it, i) => (i === index ? { ...it, [field]: value } : it)),
        );
    }

    function updateExchangeQty(index, rawValue) {
        const item = items[index];
        const next = clampQuantityInput(rawValue, item.sold_quantity, (max) => {
            toast.error(
                `Exchange quantity cannot exceed ${max} for this line.`,
            );
        });
        updateItem(index, 'quantity', next);
    }

    function handleSubmit(e) {
        e.preventDefault();

        const overLimit = items.some(
            (it) =>
                parseInt(it.quantity || 0, 10) >
                parseInt(it.sold_quantity || 0, 10),
        );

        if (overLimit) {
            toast.error(
                'Exchange quantity cannot exceed the sold quantity for any line.',
            );

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

                if (first) {
                    toast.error(Array.isArray(first) ? first[0] : first);
                }
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
                            Customer: {exchange.customer_name ?? 'Walk-in'} ·
                            Sale: {exchange.sale_invoice ?? exchange.sell_id}
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
                                        Select replacement for:{' '}
                                        {items[replaceIndex]?.old_product_name}
                                    </Label>
                                    <ProductSearchBox
                                        onAdd={applyReplacement}
                                    />
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
                                    {
                                        id: 'qty',
                                        header: 'Qty',
                                        align: 'right',
                                    },
                                    {
                                        id: 'oldPrice',
                                        header: 'Old Price',
                                        align: 'right',
                                    },
                                    {
                                        id: 'newPrice',
                                        header: 'New Price',
                                        align: 'right',
                                    },
                                    { id: 'promo', header: 'Promo' },
                                    { id: 'action', header: '' },
                                ]}
                            >
                                {items.map((item, i) => {
                                    const promoLine = item.new_product_id ? promoLineMap[Number(item.new_product_id)] : null;
                                    const promoDiscount = promoLine ? parseFloat(promoLine.promotion_discount || 0) : 0;
                                    return (
                                        <tr key={i} className="hover:bg-muted/20">
                                            <td className="px-3 py-2">
                                                <ProductNameWithCode
                                                    name={item.old_product_name}
                                                    code={item.old_product_code}
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.new_product_name ? (
                                                    <ProductNameWithCode
                                                        name={item.new_product_name}
                                                        code={item.new_product_code}
                                                        className="text-primary [&_p]:text-primary"
                                                    />
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="text-xs text-primary underline"
                                                        onClick={() =>
                                                            setReplaceIndex(i)
                                                        }
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
                                                    onChange={(e) =>
                                                        updateExchangeQty(
                                                            i,
                                                            e.target.value,
                                                        )
                                                    }
                                                    onBlur={(e) =>
                                                        updateExchangeQty(
                                                            i,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={`${inputCls} ml-auto w-20 text-right ${parseInt(item.quantity || 0, 10) > parseInt(item.sold_quantity || 0, 10) ? 'border-destructive' : ''}`}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                ৳
                                                {parseFloat(
                                                    item.old_unit_price,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={item.new_unit_price}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            i,
                                                            'new_unit_price',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={`${inputCls} ml-auto w-24 text-right`}
                                                />
                                            </td>
                                            <td className="px-2 py-2 text-right text-xs">
                                                {promoDiscount > 0 && (
                                                    <span className="text-purple-700 dark:text-purple-400">
                                                        -৳{promoDiscount.toFixed(2)}
                                                        {promoLine?.promotion_label ? ` (${promoLine.promotion_label})` : ''}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-2 py-2 text-right">
                                                {item.new_product_name && (
                                                    <button
                                                        type="button"
                                                        className="text-xs text-muted-foreground underline"
                                                        onClick={() =>
                                                            setReplaceIndex(i)
                                                        }
                                                    >
                                                        Change
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </LineItemsTable>

                            {sellDiscounts && (
                                <div className="mt-3 rounded-md border border-blue-200 bg-blue-50 p-2 text-xs text-blue-900 dark:border-blue-500/30 dark:bg-blue-950/30 dark:text-blue-200">
                                    <p className="mb-1 font-semibold">
                                        Discounts applied from sale (read-only):
                                    </p>
                                    <div className="flex flex-wrap gap-x-4 gap-y-0.5">
                                        {invoiceDiscountAmount > 0 && (
                                            <span>
                                                Invoice discount ({invoiceDiscountType === 'percent' ? `${invoiceDiscountValue}%` : `৳${invoiceDiscountValue}`}): -৳{invoiceDiscountAmount.toFixed(2)}
                                            </span>
                                        )}
                                        {specialDiscountAmount > 0 && (
                                            <span>
                                                Special discount: -৳{specialDiscountAmount.toFixed(2)}
                                            </span>
                                        )}
                                        {vatAmount > 0 && (
                                            <span>
                                                VAT ({vatPercent.toFixed(2)}%): +৳{vatAmount.toFixed(2)}
                                            </span>
                                        )}
                                        {coinDiscountAmount > 0 && (
                                            <span>
                                                Coin discount ({coinsRedeemed} coins): -৳{coinDiscountAmount.toFixed(2)}
                                            </span>
                                        )}
                                        {roundOffAmount > 0 && (
                                            <span>
                                                Round off: -৳{roundOffAmount.toFixed(2)}
                                            </span>
                                        )}
                                        {promotionDiscountTotal > 0 && (
                                            <span className="text-purple-700 dark:text-purple-400">
                                                Promotion discount (new products): -৳{promotionDiscountTotal.toFixed(2)}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div className="mt-3 flex flex-col items-end gap-1 text-xs">
                                <span>
                                    New gross: <strong>৳{grossAmount.toFixed(2)}</strong>
                                </span>
                                <span>
                                    Old total: <strong>৳{oldTotal.toFixed(2)}</strong>
                                </span>
                                <span>
                                    Net new: <strong>৳{netNewAmount.toFixed(2)}</strong>
                                </span>
                                <span>
                                    Price difference:{' '}
                                    <strong
                                        className={
                                            priceDifference >= 0
                                                ? 'text-primary'
                                                : 'text-destructive'
                                        }
                                    >
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
                            paidAmount={String(Math.abs(priceDifference))}
                            onPaidAmountChange={() => {}}
                            paidReadOnly
                            paidLabel={
                                priceDifference < 0
                                    ? 'Refund to Customer'
                                    : 'Customer Pays'
                            }
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
                        disabled={
                            items.length === 0 ||
                            items.some((it) => !it.new_product_id)
                        }
                    />
                </form>
            </div>
        </>
    );
}
