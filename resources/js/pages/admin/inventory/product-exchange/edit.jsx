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
import {
    buildEditExchangeDiscounts,
    calcProductExchangeSummary,
    syncExchangeEditPaidAmount,
} from '@/lib/product-exchange-summary';
import { formatBdDate, toDateInputValue } from '@/lib/format-bd-date';
import { route } from '@/lib/route';

function mapExchangeLine(item) {
    return {
        ...item,
        original_old_unit_price:
            item.original_old_unit_price ?? item.old_unit_price,
    };
}

export default function ProductExchangeEdit({
    exchange,
    sellDiscounts: sellDiscountsProp = null,
    sell_discounts: sellDiscountsSnake = null,
    paymentAccounts = [],
    promotions = [],
    specialDiscounts = [],
    paymentOnlyEdit = false,
    totals = null,
}) {
    const sellDiscounts = sellDiscountsProp ?? sellDiscountsSnake;
    const { flash, walkInCustomerId } = usePage().props;
    const toast = useAppToast();
    const [sourceItems] = useState(() =>
        (exchange.items ?? []).map(mapExchangeLine),
    );
    const [items, setItems] = useState(() =>
        (exchange.items ?? []).map(mapExchangeLine),
    );
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
    const prevSettlementRef = useRef(
        parseFloat(exchange.settlement_amount ?? exchange.paid_amount ?? 0),
    );
    const isInitialPaidSync = useRef(true);
    const wasOverpaidRef = useRef(false);
    const [manualDiscounts, setManualDiscounts] = useState(() =>
        buildEditExchangeDiscounts(
            exchange,
            sellDiscountsProp ?? sellDiscountsSnake,
            (exchange.items ?? []).map(mapExchangeLine),
        ),
    );

    const form = useForm({
        date: toDateInputValue(exchange.date),
        comment: exchange.comment ?? '',
        // If this exchange already had money recorded against it, the field starts
        // at 0 (no additional payment yet) — the already-paid portion is tracked
        // separately via priorPaidAmount and the sync effect below only bumps this
        // up if the exchange was previously fully settled.
        paid_amount:
            parseFloat(exchange.paid_amount ?? 0) > 0.009
                ? '0'
                : (exchange.paid_amount ?? '0'),
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
            sourceItems,
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
        sourceItems,
        sellDiscounts,
        promotions,
        form.data.date,
        manualDiscounts,
        specialDiscounts,
        coinBalanceOffset,
        coinInfo?.balance,
    ]);

    const lineTotals = paymentOnlyEdit ? totals : summary;

    const signedSettlement = paymentOnlyEdit
        ? (totals?.is_refund
            ? -parseFloat(totals?.settlement ?? exchange.settlement_amount ?? 0)
            : parseFloat(totals?.settlement ?? exchange.settlement_amount ?? 0))
        : (summary?.signedSettlement ?? parseFloat(exchange.price_difference ?? 0));
    const priceDifference = paymentOnlyEdit
        ? signedSettlement
        : (summary?.priceDifference ?? parseFloat(exchange.price_difference ?? 0));
    const settlementAmount = paymentOnlyEdit
        ? Math.abs(signedSettlement) < 0.009
            ? 0
            : Math.abs(signedSettlement)
        : (summary?.settlementAmount ?? 0);
    const isRefund = paymentOnlyEdit
        ? Boolean(totals?.is_refund ?? exchange.is_refund)
        : (summary?.signedSettlement ?? priceDifference) < -0.009;
    const isParty = paymentMode === 'party';

    // The amount already disbursed/collected for this exchange before this edit
    // session started. Only offset against it while the settlement direction
    // (refund vs. customer-pays) hasn't flipped — a flip means the prior payment
    // isn't part of the same money flow anymore.
    const priorPaidAmount = parseFloat(exchange.paid_amount ?? 0) || 0;
    const priorIsRefund = Boolean(exchange.is_refund);
    const showPriorPayment = priorPaidAmount > 0.009 && priorIsRefund === isRefund;
    const fieldPaidAmount = isParty ? 0 : parseFloat(form.data.paid_amount || 0) || 0;
    // Gross cash already refunded above the (shrunk) settlement — before any
    // repayment the customer hands back in this edit session.
    const grossOverpaidAmount =
        isRefund && !isParty && showPriorPayment
            ? Math.max(0, priorPaidAmount - settlementAmount)
            : 0;
    const isOverpaid = grossOverpaidAmount > 0.009;
    // When overpaid, the amount field is customer repayment — not more refund.
    const customerPaymentNow = isOverpaid
        ? Math.min(fieldPaidAmount, grossOverpaidAmount)
        : 0;
    const overpaidAmount = Math.max(0, grossOverpaidAmount - customerPaymentNow);
    const totalPaidAmount = isOverpaid
        ? priorPaidAmount
        : showPriorPayment
          ? priorPaidAmount + fieldPaidAmount
          : fieldPaidAmount;

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
        const signed = summary.signedSettlement ?? 0;
        const nowOverpaid =
            paymentMode !== 'party' &&
            showPriorPayment &&
            signed < -0.009 &&
            priorPaidAmount > settlement + 0.009;

        if (paymentMode === 'party') {
            form.setData('paid_amount', '0');
            prevSettlementRef.current = settlement;
            wasOverpaidRef.current = false;

            return;
        }

        // Overpaid state uses the amount field as customer repayment. Reset once
        // when entering that state, then leave the field alone for the user.
        if (nowOverpaid) {
            if (!wasOverpaidRef.current) {
                form.setData('paid_amount', '0');
            }

            wasOverpaidRef.current = true;
            isInitialPaidSync.current = false;
            prevSettlementRef.current = settlement;

            return;
        }

        wasOverpaidRef.current = false;

        const syncedTotal = syncExchangeEditPaidAmount({
            currentPaid: totalPaidAmount,
            previousSettlement: prevSettlementRef.current,
            nextSettlement: settlement,
            skipAutoFill: isInitialPaidSync.current,
        });

        if (syncedTotal !== null) {
            const nextTotal = parseFloat(syncedTotal);
            const nextField = showPriorPayment
                ? Math.max(0, nextTotal - priorPaidAmount)
                : nextTotal;
            form.setData('paid_amount', nextField > 0.009 ? nextField.toFixed(2) : '0');
        }

        if (isInitialPaidSync.current) {
            isInitialPaidSync.current = false;
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

        if (isOverpaid) {
            form.setData('paid_amount', '0');
            prevSettlementRef.current = settlement;

            return;
        }

        if (totalPaidAmount <= 0.009) {
            const target = showPriorPayment
                ? Math.max(0, settlement - priorPaidAmount)
                : settlement;
            form.setData('paid_amount', target > 0.009 ? target.toFixed(2) : '0');
        }

        prevSettlementRef.current = settlement;
    }

    function applyReplacement(product) {
        if (replaceIndex === null) {
            return;
        }

        setItems((prev) =>
            prev.map((it, i) => {
                if (i !== replaceIndex) {
                    return it;
                }

                const soldQty = parseInt(it.sold_quantity || 0, 10);
                const returnQty = parseInt(it.return_quantity || 0, 10);
                const currentQty = parseInt(it.quantity || 0, 10);
                const defaultQty = Math.max(1, soldQty - returnQty);

                return {
                    ...it,
                    new_product_id: product.product_id,
                    new_product_name: product.product_name,
                    new_product_code: product.product_code,
                    new_variation_id: product.variation_id ?? null,
                    new_variation_label: product.variation_label ?? null,
                    new_unit_price: String(product.unit_price ?? 0),
                    category_id: product.category_id ?? it.category_id,
                    brand_id: product.brand_id ?? it.brand_id,
                    quantity:
                        currentQty > 0 ? it.quantity : String(defaultQty),
                };
            }),
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
        const maxSwap = Math.max(
            0,
            parseInt(item.sold_quantity || 0, 10) -
                parseInt(item.return_quantity || 0, 10),
        );
        const next = clampQuantityInput(rawValue, maxSwap, (max) => {
            toast.error(
                `Exchange quantity cannot exceed ${max} for this line.`,
            );
        });
        updateItem(index, 'quantity', next);
    }

    function updateReturnQty(index, rawValue) {
        const item = items[index];
        const maxReturn = Math.max(
            0,
            parseInt(item.sold_quantity || 0, 10) -
                parseInt(item.quantity || 0, 10),
        );
        const next = clampQuantityInput(rawValue, maxReturn, (max) => {
            toast.error(`Return quantity cannot exceed ${max} for this line.`);
        });
        updateItem(index, 'return_quantity', next);
    }

    function handleSubmit(e) {
        e.preventDefault();

        if (paymentOnlyEdit) {
            form.transform((data) => ({
                comment: data.comment,
                payment_type: paymentModeToType(paymentMode),
                payment_account_id: paymentModeToAccountId(paymentMode),
                paid_amount: paymentMode === 'party'
                    ? '0'
                    : (totalPaidAmount > 0.009 ? totalPaidAmount.toFixed(2) : '0'),
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
                parseInt(it.quantity || 0, 10) +
                    parseInt(it.return_quantity || 0, 10) >
                parseInt(it.sold_quantity || 0, 10),
        );

        if (overLimit) {
            toast.error(
                'Exchange and return quantity cannot exceed the sold quantity for any line.',
            );

            return;
        }

        const exchangeItems = items
            .filter(
                (it) =>
                    parseInt(it.quantity || 0, 10) > 0 ||
                    parseInt(it.return_quantity || 0, 10) > 0,
            )
            .map((it) => ({
                sell_product_id: it.sell_product_id,
                product_id:
                    parseInt(it.quantity || 0, 10) > 0
                        ? it.new_product_id
                        : null,
                variation_id: it.new_variation_id || null,
                unit_price: it.new_unit_price,
                quantity: it.quantity || '0',
                return_quantity: it.return_quantity || '0',
            }));

        if (exchangeItems.length === 0) {
            toast.error('Add an exchange or return quantity for at least one line.');

            return;
        }

        if (
            exchangeItems.some(
                (it) => parseInt(it.quantity || 0, 10) > 0 && !it.product_id,
            )
        ) {
            toast.error('Select a replacement product for each exchange line.');

            return;
        }

        form.transform((data) => ({
            ...data,
            payment_type: paymentModeToType(paymentMode),
            payment_account_id: paymentModeToAccountId(paymentMode),
            paid_amount: paymentMode === 'party'
                ? '0'
                : (totalPaidAmount > 0.009 ? totalPaidAmount.toFixed(2) : '0'),
            customer_payment_amount: paymentMode === 'party' || !isOverpaid
                ? '0'
                : (customerPaymentNow > 0.009 ? customerPaymentNow.toFixed(2) : '0'),
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

    const settlementLineLabel = isRefund ? 'Refund to Customer' : 'Customer Pays';
    const paidLabel = isOverpaid
        ? 'Customer Payment'
        : showPriorPayment
          ? (isRefund ? 'Additional Refund Now' : 'Additional Payment Now')
          : (isRefund ? 'Refund Paid' : 'Paid Amount');
    const dueLabel = isRefund ? 'Remaining Refund' : 'Due Amount';
    const priorPaidLabel = isRefund ? 'Already Refunded' : 'Already Received';
    const overpaidLabel = customerPaymentNow > 0.009
        ? 'Remaining Customer Due'
        : 'Customer Pays This Back — Added to Due';

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
                                    <p className="text-sm font-medium">{formatBdDate(form.data.date)}</p>
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
                                        id: 'soldQty',
                                        header: 'Sold',
                                        align: 'right',
                                    },
                                    {
                                        id: 'qty',
                                        header: 'Exchange Qty',
                                        align: 'right',
                                    },
                                    {
                                        id: 'returnQty',
                                        header: 'Return Qty',
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
                                    const maxReturnQty = Math.max(
                                        0,
                                        parseInt(item.sold_quantity || 0, 10) -
                                            parseInt(item.quantity || 0, 10),
                                    );

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
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                {item.sold_quantity}
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                {paymentOnlyEdit ? (
                                                    item.quantity
                                                ) : (
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max={Math.max(
                                                            0,
                                                            parseInt(
                                                                item.sold_quantity ||
                                                                    0,
                                                                10,
                                                            ) -
                                                                parseInt(
                                                                    item.return_quantity ||
                                                                        0,
                                                                    10,
                                                                ),
                                                        )}
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
                                                        className={`${inputCls} ml-auto w-20 text-right ${parseInt(item.quantity || 0, 10) + parseInt(item.return_quantity || 0, 10) > parseInt(item.sold_quantity || 0, 10) ? 'border-destructive' : ''}`}
                                                    />
                                                )}
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                {paymentOnlyEdit ? (
                                                    item.return_quantity
                                                ) : (
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max={maxReturnQty}
                                                        step="1"
                                                        value={
                                                            item.return_quantity
                                                        }
                                                        onChange={(e) =>
                                                            updateReturnQty(
                                                                i,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={`${inputCls} ml-auto w-20 text-right`}
                                                    />
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                ৳
                                                {parseFloat(
                                                    item.original_old_unit_price ??
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
                                    Sold line total:{' '}
                                    <strong>
                                        ৳
                                        {(
                                            lineTotals?.soldLineTotal ??
                                            lineTotals?.sold_line_total ??
                                            0
                                        ).toFixed(2)}
                                    </strong>
                                </span>
                                {(lineTotals?.soldLineTotal ??
                                    lineTotals?.sold_line_total ??
                                    0) -
                                    (lineTotals?.oldExchangeTotal ??
                                        lineTotals?.old_exchange_total ??
                                        lineTotals?.old_total ??
                                        lineTotals?.oldTotal ??
                                        0) >
                                    0.009 && (
                                    <span>
                                        Exchange old total:{' '}
                                        <strong>
                                            ৳
                                            {(
                                                lineTotals?.oldExchangeTotal ??
                                                lineTotals?.old_exchange_total ??
                                                lineTotals?.old_total ??
                                                lineTotals?.oldTotal ??
                                                0
                                            ).toFixed(2)}
                                        </strong>
                                    </span>
                                )}
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
                                {settlementAmount > 0.009 && (
                                    <span>
                                        {settlementLineLabel}:{' '}
                                        <strong
                                            className={
                                                isRefund
                                                    ? 'text-destructive'
                                                    : 'text-primary'
                                            }
                                        >
                                            ৳{settlementAmount.toFixed(2)}
                                        </strong>
                                    </span>
                                )}
                                {(lineTotals?.new_discount_total ?? lineTotals?.newDiscountTotal ?? 0) > 0.009 && (
                                    <span className="text-destructive">
                                        Discounts:{' '}
                                        <strong>
                                            -৳
                                            {(lineTotals.new_discount_total ?? lineTotals.newDiscountTotal).toFixed(2)}
                                        </strong>
                                    </span>
                                )}
                                {(lineTotals?.return_refund ?? lineTotals?.returnRefund ?? 0) > 0.009 && (
                                    <span className="text-destructive">
                                        Return refund:{' '}
                                        <strong>
                                            -৳
                                            {(lineTotals.return_refund ?? lineTotals.returnRefund).toFixed(2)}
                                        </strong>
                                    </span>
                                )}
                                <span>
                                    Net new:{' '}
                                    <strong>
                                        ৳
                                        {(lineTotals?.net ?? lineTotals?.netNewAmount ?? 0).toFixed(2)}
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
                            sellDiscounts={sellDiscounts}
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
                            paidLabel={paidLabel}
                            settlementLineLabel={settlementLineLabel}
                            showPaidAmount={!isParty && (isOverpaid || settlementAmount > 0.009 || showPriorPayment)}
                            dueLabel={dueLabel}
                            dueAmountOverride={Math.max(0, settlementAmount - totalPaidAmount)}
                            priorPaidAmount={showPriorPayment && !isParty ? priorPaidAmount : null}
                            priorPaidLabel={priorPaidLabel}
                            overpaidAmount={isOverpaid ? overpaidAmount : null}
                            overpaidLabel={overpaidLabel}
                            paymentMode={paymentMode}
                            onPaymentModeChange={handlePaymentModeChange}
                            paymentAccounts={paymentAccounts}
                            partyLabel="Customer Account"
                            partyPaidHint={
                                isRefund
                                    ? 'The full refund settles on the customer account. No cash or bank entry is posted.'
                                    : 'The full exchange difference settles on the customer account. No cash or bank entry is posted.'
                            }
                            paymentHint={
                                isParty
                                    ? null
                                    : isOverpaid
                                      ? 'Cash / bank account receiving the customer repayment. Leave at 0 to add the full amount to customer due.'
                                      : isRefund
                                        ? 'Cash / bank account the refund is paid from.'
                                        : 'Cash / bank account (asset ledger). Enter the amount received in Paid Amount.'
                            }
                            paidError={form.errors.paid_amount || form.errors.customer_payment_amount}
                            showDue={!isParty && settlementAmount > 0 && !isOverpaid}
                        />
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.product-exchange.index"
                        submitLabel={paymentOnlyEdit ? 'Update Payment' : 'Update Exchange'}
                        processing={form.processing}
                        disabled={
                            !paymentOnlyEdit &&
                            (items.length === 0 ||
                                !items.some(
                                    (it) =>
                                        parseInt(it.quantity || 0, 10) > 0 ||
                                        parseInt(
                                            it.return_quantity || 0,
                                            10,
                                        ) > 0,
                                ) ||
                                items.some(
                                    (it) =>
                                        parseInt(it.quantity || 0, 10) > 0 &&
                                        !it.new_product_id,
                                ))
                        }
                    />
                </form>
            </div>
        </>
    );
}
