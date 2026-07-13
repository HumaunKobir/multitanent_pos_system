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
    InvoiceLookupField,
    LineItemsTable,
    PaymentSummaryCard,
    ProductExchangeDiscountsCard,
    ProductNameWithCode,
    inputCls,
    paymentModeToType,
    paymentModeToAccountId,
} from '@/components/inventory/inventory-form';
import { ProductSearchBox } from '@/components/inventory/product-search-box';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppToast } from '@/contexts/app-toast-context';
import { useCustomerCoinInfo } from '@/hooks/use-customer-coin-info';
import {
    buildInitialExchangeDiscounts,
    calcProductExchangeSummary,
} from '@/lib/product-exchange-summary';
import { route } from '@/lib/route';

export default function ProductExchangeCreate({
    today,
    paymentAccounts = [],
    promotions = [],
    specialDiscounts = [],
}) {
    const { flash, walkInCustomerId } = usePage().props;
    const toast = useAppToast();
    const [source, setSource] = useState(null);
    const [invoiceQuery, setInvoiceQuery] = useState('');
    const [lookupError, setLookupError] = useState('');
    const [items, setItems] = useState([]);
    const [paymentMode, setPaymentMode] = useState('party');
    const [replaceIndex, setReplaceIndex] = useState(null);
    const [manualDiscounts, setManualDiscounts] = useState({});
    const prevSettlementRef = useRef(0);

    const form = useForm({
        sell_id: '',
        date: today,
        comment: '',
        payment_type: '5',
        paid_amount: '0',
        items: [],
    });

    const { coinInfo, loading: coinInfoLoading } = useCustomerCoinInfo({
        customerId: source?.customer_id,
        walkInCustomerId,
        coinSettings: source?.coin_settings,
    });

    const coinBalanceOffset = source?.sell_discounts
        ? (parseFloat(source.sell_discounts.coins_redeemed || 0) || 0) -
          (parseFloat(source.sell_discounts.coins_earned || 0) || 0)
        : 0;

    const summary = useMemo(
        () =>
            calcProductExchangeSummary({
                items,
                sellDiscounts: source?.sell_discounts,
                sourceItems: source?.items,
                promotions: source?.promotions ?? promotions,
                saleDate: form.data.date,
                manualDiscounts,
                specialDiscounts,
                coinSettings: source?.coin_settings,
                coinBalanceOffset,
                customerBalance: coinInfo?.balance ?? 0,
            }),
        [
            items,
            source,
            promotions,
            form.data.date,
            manualDiscounts,
            specialDiscounts,
            coinBalanceOffset,
            coinInfo?.balance,
        ],
    );

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (!summary) {
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
    }, [summary?.settlementAmount, paymentMode]);

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

    useEffect(() => {
        if (!summary || paymentMode === 'party') {
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

    async function lookupSale() {
        setLookupError('');
        const res = await fetch(
            `${route('api.sales.lookup')}?invoice=${encodeURIComponent(invoiceQuery)}&for=exchange`,
            {
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const json = await res.json();

        if (!res.ok) {
            setLookupError(json.message ?? 'Sale not found.');

            return;
        }

        setSource(json);
        setManualDiscounts(buildInitialExchangeDiscounts(json.sell_discounts));
        setItems(
            json.items.map((i) => ({
                sell_product_id: i.sell_product_id,
                product_id: i.product_id,
                old_product_id: i.product_id,
                old_product_name: i.product_name,
                old_product_code: i.product_code,
                old_variation_id: i.variation_id ?? null,
                old_variation_label: i.variation_label ?? null,
                old_unit_price: i.unit_price,
                original_old_unit_price: i.unit_price,
                line_discount: i.line_discount,
                promotion_id: i.promotion_id,
                promotion_details: i.promotion_details,
                promotion_discount: i.promotion_discount,
                category_id: i.category_id,
                brand_id: i.brand_id,
                quantity: '0',
                return_quantity: '0',
                sold_quantity: i.sold_quantity,
                new_product_id: '',
                new_product_name: '',
                new_variation_id: null,
                new_unit_price: String(i.sell_price),
            })),
        );
        form.setData({ ...form.data, sell_id: String(json.id), paid_amount: '0' });
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
            paid_amount: paymentMode === 'party' ? '0' : data.paid_amount,
            discount_type: manualDiscounts.invoiceType || 'flat',
            discount_value: String(parseFloat(manualDiscounts.invoice || 0)),
            special_discount_id: manualDiscounts.specialDiscountId || null,
            round_off_amount: String(parseFloat(manualDiscounts.roundOff || 0)),
            coins_redeemed: String(summary?.coinsRedeemed ?? 0),
            items: exchangeItems,
        }));
        form.post(route('inventory.product-exchange.store'), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];

                if (first) {
                    toast.error(Array.isArray(first) ? first[0] : first);
                }
            },
        });
    }

    const priceDifference = summary?.priceDifference ?? 0;
    const settlementAmount = summary?.settlementAmount ?? 0;
    const isRefund = (summary?.signedSettlement ?? priceDifference) < 0;
    const isParty = paymentMode === 'party';
    const settlementLineLabel = isRefund ? 'Refund to Customer' : 'Customer Pays';
    const dueLabel = isRefund ? 'Remaining Refund' : 'Due Amount';

    return (
        <>
            <Head title="Product Exchange" />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Product Exchange"
                    subtitle="Replace sold items with new products."
                    icon={ArrowLeftRight}
                    backRoute="inventory.product-exchange.index"
                />

                <form onSubmit={handleSubmit} className="space-y-4">
                    <InventoryCard title="Source Sale" icon={CalendarDays}>
                        <InvoiceLookupField
                            label="Sale Invoice"
                            placeholder="INVS00000001 or 1"
                            value={invoiceQuery}
                            onChange={setInvoiceQuery}
                            onSearch={lookupSale}
                            error={lookupError || form.errors.sell_id}
                            hint={
                                source
                                    ? `Customer: ${source.customer?.name ?? 'Walk-in'} · ${source.invoice_number}`
                                    : 'Enter invoice number and press Enter or click Load.'
                            }
                        />
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
                                    { id: 'action', header: '' },
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
                                                    variation={
                                                        item.old_variation_label
                                                    }
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
                                                        variation={
                                                            item.new_variation_label
                                                        }
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
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                {item.sold_quantity}
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                <Input
                                                    type="number"
                                                    min="0"
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
                                                    className={`${inputCls} ml-auto w-20 text-right ${parseInt(item.quantity || 0, 10) + parseInt(item.return_quantity || 0, 10) > parseInt(item.sold_quantity || 0, 10) ? 'border-destructive' : ''}`}
                                                />
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max={item.sold_quantity}
                                                    step="1"
                                                    value={item.return_quantity}
                                                    onChange={(e) =>
                                                        updateReturnQty(
                                                            i,
                                                            e.target.value,
                                                        )
                                                    }
                                                    onBlur={(e) =>
                                                        updateReturnQty(
                                                            i,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={`${inputCls} ml-auto w-20 text-right`}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                ৳
                                                {parseFloat(
                                                    item.original_old_unit_price ??
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

                            <div className="mt-3 flex flex-col items-end gap-1 text-xs">
                                <span>
                                    New gross:{' '}
                                    <strong>
                                        ৳
                                        {(summary?.grossAmount ?? 0).toFixed(2)}
                                    </strong>
                                </span>
                                <span>
                                    Sold line total:{' '}
                                    <strong>
                                        ৳
                                        {(summary?.soldLineTotal ?? 0).toFixed(2)}
                                    </strong>
                                </span>
                                {(summary?.soldLineTotal ?? 0) -
                                    (summary?.oldExchangeTotal ?? 0) >
                                    0.009 && (
                                    <span>
                                        Exchange old total:{' '}
                                        <strong>
                                            ৳
                                            {(
                                                summary?.oldExchangeTotal ?? 0
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
                                {(summary?.newDiscountTotal ?? 0) > 0.009 && (
                                    <span className="text-destructive">
                                        Discounts:{' '}
                                        <strong>
                                            -৳
                                            {summary.newDiscountTotal.toFixed(
                                                2,
                                            )}
                                        </strong>
                                    </span>
                                )}
                                {(summary?.returnRefund ?? 0) > 0.009 && (
                                    <span className="text-destructive">
                                        Return refund:{' '}
                                        <strong>
                                            -৳
                                            {summary.returnRefund.toFixed(2)}
                                        </strong>
                                    </span>
                                )}
                                <span>
                                    Net new:{' '}
                                    <strong>
                                        ৳
                                        {(summary?.netNewAmount ?? 0).toFixed(
                                            2,
                                        )}
                                    </strong>
                                </span>
                            </div>
                        </InventoryCard>
                    )}

                    {summary && (
                        <ProductExchangeDiscountsCard
                            summary={summary}
                            manualDiscounts={manualDiscounts}
                            onManualDiscountChange={handleManualDiscountChange}
                            sellDiscounts={source?.sell_discounts}
                            specialDiscounts={specialDiscounts}
                            coinSettings={source?.coin_settings}
                            coinInfo={coinInfo}
                            coinInfoLoading={coinInfoLoading}
                            customerId={source?.customer_id}
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
                        submitLabel="Save Exchange"
                        processing={form.processing}
                        disabled={
                            !form.data.sell_id ||
                            items.length === 0 ||
                            !items.some(
                                (it) =>
                                    parseInt(it.quantity || 0, 10) > 0 ||
                                    parseInt(it.return_quantity || 0, 10) > 0,
                            ) ||
                            items.some(
                                (it) =>
                                    parseInt(it.quantity || 0, 10) > 0 &&
                                    !it.new_product_id,
                            )
                        }
                    />
                </form>
            </div>
        </>
    );
}
