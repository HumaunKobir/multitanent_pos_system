import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftRight, CalendarDays, Package } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    clampQuantityInput,
    CommentCard,
    DateField,
    InventoryCard,
    InventoryFormActions,
    InventoryPageHeader,
    LineItemsTable,
    PaymentSummaryCard,
    ProductExchangeDiscountsCard,
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
import { useCustomerCoinInfo } from '@/hooks/use-customer-coin-info';
import { calcProductExchangeSummary } from '@/lib/product-exchange-summary';
import { route } from '@/lib/route';

export default function ProductExchangeEdit({
    exchange,
    sellDiscounts = null,
    paymentAccounts = [],
    promotions = [],
    specialDiscounts = [],
    paymentOnlyEdit = false,
    totals = null,
}) {
    const { flash, walkInCustomerId } = usePage().props;
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
    const prevSettlementRef = useRef(parseFloat(exchange.paid_amount || 0));
    const [manualDiscounts, setManualDiscounts] = useState(() => ({
        invoiceType: exchange.discount_type ?? 'flat',
        invoice:
            parseFloat(exchange.discount_value || 0) > 0
                ? String(exchange.discount_value)
                : '',
        specialDiscountId: exchange.special_discount_id
            ? String(exchange.special_discount_id)
            : '',
        roundOff:
            parseFloat(exchange.round_off_amount || 0) > 0
                ? String(exchange.round_off_amount)
                : '',
        coinsRedeemed:
            parseFloat(exchange.coins_redeemed || 0) > 0
                ? String(exchange.coins_redeemed)
                : '',
    }));

    const form = useForm({
        date: exchange.date ?? '',
        comment: exchange.comment ?? '',
        paid_amount: exchange.paid_amount ?? '0',
        payment_type: String(exchange.payment_type ?? '5'),
        items: [],
    });

    const { coinInfo, loading: coinInfoLoading } = useCustomerCoinInfo({
        customerId: exchange.customer_id,
        walkInCustomerId,
        coinSettings: sellDiscounts?.coin_settings,
    });

    const coinBalanceOffset = sellDiscounts
        ? (parseFloat(sellDiscounts.coins_redeemed || 0) || 0) -
          (parseFloat(sellDiscounts.coins_earned || 0) || 0)
        : 0;

    const summary = useMemo(() => {
        if (paymentOnlyEdit) {
            return null;
        }

        return calcProductExchangeSummary({
            items,
            sellDiscounts,
            sourceItems: items,
            promotions,
            saleDate: form.data.date,
            manualDiscounts,
            specialDiscounts,
            coinSettings: sellDiscounts?.coin_settings,
            coinBalanceOffset,
            customerBalance: coinInfo?.balance ?? 0,
        });
    }, [
        paymentOnlyEdit,
        items,
        sellDiscounts,
        promotions,
        form.data.date,
        manualDiscounts,
        specialDiscounts,
        coinBalanceOffset,
        coinInfo?.balance,
    ]);

    const lineTotals = paymentOnlyEdit ? totals : summary;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (paymentOnlyEdit || !summary) {
            return;
        }

        const settlement = summary.settlementAmount;

        if (paymentMode === 'party') {
            form.setData('paid_amount', '0');

            return;
        }

        const currentPaid = parseFloat(form.data.paid_amount || 0);

        if (currentPaid === 0 || currentPaid === prevSettlementRef.current) {
            form.setData(
                'paid_amount',
                settlement > 0 ? settlement.toFixed(2) : '0',
            );
        }

        prevSettlementRef.current = settlement;
    }, [summary?.settlementAmount, paymentMode, paymentOnlyEdit]);

    useEffect(() => {
        if (paymentOnlyEdit || !summary || paymentMode === 'party') {
            return;
        }

        const maxRedeem = summary.maxRedeemable;
        const current = parseFloat(manualDiscounts.coinsRedeemed || 0);

        if (current > maxRedeem) {
            setManualDiscounts((prev) => ({
                ...prev,
                coinsRedeemed: maxRedeem > 0 ? String(maxRedeem) : '',
            }));
        }
    }, [summary?.maxRedeemable, paymentMode]);

    function handleManualDiscountChange(field, value) {
        setManualDiscounts((prev) => ({ ...prev, [field]: value }));
    }

    function handlePaymentModeChange(mode) {
        setPaymentMode(mode);

        if (mode === 'party') {
            form.setData('paid_amount', '0');

            return;
        }

        const settlement = summary?.settlementAmount ?? 0;
        form.setData(
            'paid_amount',
            settlement > 0 ? settlement.toFixed(2) : '0',
        );
        prevSettlementRef.current = settlement;
    }

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
                          category_id: product.category_id ?? it.category_id,
                          brand_id: product.brand_id ?? it.brand_id,
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

        if (paymentOnlyEdit) {
            form.transform((data) => ({
                comment: data.comment,
                payment_type: paymentModeToType(paymentMode),
                payment_account_id: paymentModeToAccountId(paymentMode),
                paid_amount: paymentMode === 'party' ? '0' : data.paid_amount,
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

            return;
        }

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
            paid_amount: paymentMode === 'party' ? '0' : data.paid_amount,
            discount_type: manualDiscounts.invoiceType || 'flat',
            discount_value: String(parseFloat(manualDiscounts.invoice || 0)),
            special_discount_id: manualDiscounts.specialDiscountId || null,
            round_off_amount: String(parseFloat(manualDiscounts.roundOff || 0)),
            coins_redeemed: String(summary?.coinsRedeemed ?? 0),
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

    const priceDifference = lineTotals?.gross_price_difference ?? lineTotals?.priceDifference ?? 0;
    const settlementAmount = paymentOnlyEdit
        ? (totals?.settlement ?? exchange.settlement_amount ?? 0)
        : (summary?.settlementAmount ?? 0);
    const isRefund = paymentOnlyEdit
        ? Boolean(totals?.is_refund)
        : (summary?.signedSettlement ?? priceDifference) < 0;
    const isParty = paymentMode === 'party';
    const settlementLineLabel = isRefund ? 'Refund to Customer' : 'Customer Pays';
    const dueLabel = isRefund ? 'Remaining Refund' : 'Due Amount';

    return (
        <>
            <Head title={paymentOnlyEdit ? `Update Payment — ${exchange.sale_invoice ?? exchange.id}` : 'Edit Product Exchange'} />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title={paymentOnlyEdit ? 'Update Payment' : 'Edit Product Exchange'}
                    subtitle={
                        paymentOnlyEdit
                            ? 'Products and discounts are locked — update payment only.'
                            : 'Update exchange lines and payment.'
                    }
                    icon={ArrowLeftRight}
                    backRoute="inventory.product-exchange.index"
                />

                {paymentOnlyEdit && (
                    <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        This exchange has a partial payment. Products and discounts are locked — you can only update payment amounts.
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    <InventoryCard title="Source Sale" icon={CalendarDays}>
                        <p className="text-xs text-muted-foreground">
                            Customer: {exchange.customer_name ?? 'Walk-in'} ·
                            Sale: {exchange.sale_invoice ?? exchange.sell_id}
                        </p>
                        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {paymentOnlyEdit ? (
                                <div>
                                    <Label className="text-xs text-muted-foreground">Exchange Date</Label>
                                    <p className="text-sm font-medium">{form.data.date}</p>
                                </div>
                            ) : (
                                <DateField
                                    label="Exchange Date"
                                    value={form.data.date}
                                    onChange={(v) => form.setData('date', v)}
                                    error={form.errors.date}
                                />
                            )}
                        </div>
                    </InventoryCard>

                    {items.length > 0 && (
                        <InventoryCard title="Exchange Lines" icon={Package}>
                            {!paymentOnlyEdit && replaceIndex !== null && (
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
                                    ...(paymentOnlyEdit
                                        ? []
                                        : [{ id: 'action', header: '' }]),
                                ]}
                            >
                                {items.map((item, i) => {
                                    const promoLine =
                                        summary?.promoLines?.[
                                            Number(item.sell_product_id)
                                        ];
                                    const promoDiscount = promoLine
                                        ? parseFloat(
                                              promoLine.promotion_discount || 0,
                                          )
                                        : 0;

                                    return (
                                        <tr
                                            key={i}
                                            className="hover:bg-muted/20"
                                        >
                                            <td className="px-3 py-2">
                                                <ProductNameWithCode
                                                    name={item.old_product_name}
                                                    code={item.old_product_code}
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.new_product_name ? (
                                                    <ProductNameWithCode
                                                        name={
                                                            item.new_product_name
                                                        }
                                                        code={
                                                            item.new_product_code
                                                        }
                                                        className="text-primary [&_p]:text-primary"
                                                    />
                                                ) : (
                                                    !paymentOnlyEdit && (
                                                        <button
                                                            type="button"
                                                            className="text-xs text-primary underline"
                                                            onClick={() =>
                                                                setReplaceIndex(i)
                                                            }
                                                        >
                                                            Pick product…
                                                        </button>
                                                    )
                                                )}
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                {paymentOnlyEdit ? (
                                                    item.quantity
                                                ) : (
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
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                ৳
                                                {parseFloat(
                                                    item.old_unit_price,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                {paymentOnlyEdit ? (
                                                    <>
                                                        ৳
                                                        {parseFloat(
                                                            item.new_unit_price,
                                                        ).toFixed(2)}
                                                    </>
                                                ) : (
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
                                                )}
                                            </td>
                                            <td className="px-2 py-2 text-right text-xs">
                                                {promoDiscount > 0 && (
                                                    <span className="text-purple-700 dark:text-purple-400">
                                                        -৳
                                                        {promoDiscount.toFixed(
                                                            2,
                                                        )}
                                                        {promoLine?.promotion_label
                                                            ? ` (${promoLine.promotion_label})`
                                                            : ''}
                                                    </span>
                                                )}
                                            </td>
                                            {!paymentOnlyEdit && (
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
                                            )}
                                        </tr>
                                    );
                                })}
                            </LineItemsTable>

                            <div className="mt-3 flex flex-col items-end gap-1 text-xs">
                                <span>
                                    New gross:{' '}
                                    <strong>
                                        ৳{(lineTotals?.gross ?? lineTotals?.grossAmount ?? 0).toFixed(2)}
                                    </strong>
                                </span>
                                <span>
                                    Old total:{' '}
                                    <strong>
                                        ৳{(lineTotals?.old_total ?? lineTotals?.oldTotal ?? 0).toFixed(2)}
                                    </strong>
                                </span>
                                <span>
                                    Price difference:{' '}
                                    <strong
                                        className={
                                            priceDifference >= 0
                                                ? 'text-primary'
                                                : priceDifference < 0
                                                  ? 'text-destructive'
                                                  : ''
                                        }
                                    >
                                        ৳{priceDifference.toFixed(2)}
                                    </strong>
                                </span>
                                {(lineTotals?.new_discount_total ?? lineTotals?.newDiscountTotal ?? 0) > 0.009 && (
                                    <span className="text-destructive">
                                        Discounts:{' '}
                                        <strong>
                                            -৳
                                            {(lineTotals.new_discount_total ?? lineTotals.newDiscountTotal).toFixed(2)}
                                        </strong>
                                    </span>
                                )}
                                <span>
                                    Net new:{' '}
                                    <strong>
                                        ৳
                                        ৳{(lineTotals?.net ?? lineTotals?.netNewAmount ?? 0).toFixed(2)}
                                    </strong>
                                </span>
                            </div>
                        </InventoryCard>
                    )}

                    {summary && !paymentOnlyEdit && (
                        <ProductExchangeDiscountsCard
                            summary={summary}
                            manualDiscounts={manualDiscounts}
                            onManualDiscountChange={handleManualDiscountChange}
                            specialDiscounts={specialDiscounts}
                            coinSettings={sellDiscounts?.coin_settings}
                            coinInfo={coinInfo}
                            coinInfoLoading={coinInfoLoading}
                            customerId={exchange.customer_id}
                            walkInCustomerId={walkInCustomerId}
                            coinBalanceOffset={coinBalanceOffset}
                        />
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <CommentCard
                            value={form.data.comment}
                            onChange={(v) => form.setData('comment', v)}
                            error={form.errors.comment}
                        />
                        <PaymentSummaryCard
                            Icon={ArrowLeftRight}
                            grossAmount={settlementAmount}
                            paidAmount={form.data.paid_amount}
                            onPaidAmountChange={(v) =>
                                form.setData('paid_amount', v)
                            }
                            paidReadOnly={false}
                            paidLabel={settlementLineLabel}
                            settlementLineLabel={settlementLineLabel}
                            showPaidAmount={!isParty}
                            dueLabel={dueLabel}
                            paymentMode={paymentMode}
                            onPaymentModeChange={handlePaymentModeChange}
                            paymentAccounts={paymentAccounts}
                            partyLabel="Customer Account"
                            partyPaidHint="The full exchange difference settles on the customer account. No cash or bank entry is posted."
                            paidError={form.errors.paid_amount}
                            showDue={!isParty && settlementAmount > 0}
                        />
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.product-exchange.index"
                        submitLabel={paymentOnlyEdit ? 'Update Payment' : 'Update Exchange'}
                        processing={form.processing}
                        disabled={
                            !paymentOnlyEdit &&
                            (items.length === 0 ||
                                items.some((it) => !it.new_product_id))
                        }
                    />
                </form>
            </div>
        </>
    );
}
