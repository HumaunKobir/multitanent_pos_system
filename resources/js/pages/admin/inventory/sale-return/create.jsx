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
    ProductNameWithCode,
    SaleReturnRefundCard,
    SaleReturnSourceDiscounts,
    inputCls,
} from '@/components/inventory/inventory-form';
import { useAppToast } from '@/contexts/app-toast-context';
import { buildInitialReturnDiscounts, buildInitialReturnPayments, calcSaleReturnSummary, derivedVatPercent, saleReturnLineStats } from '@/lib/sale-return-summary';
import { computeSplitSalePayment, saleReturnPaymentRequiredError, serializeSalePayments } from '@/lib/sale-payment';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Package, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

export default function SaleReturnCreate({ today, paymentAccounts = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [source, setSource] = useState(null);
    const [invoiceQuery, setInvoiceQuery] = useState('');
    const [lookupError, setLookupError] = useState('');
    const [items, setItems] = useState([]);
    const [manualDiscounts, setManualDiscounts] = useState({});
    const [vatPercent, setVatPercent] = useState('');
    const [payments, setPayments] = useState(() => buildInitialReturnPayments([], 0, paymentAccounts));
    const paymentsSeededForSellId = useRef(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const form = useForm({
        sell_id: '',
        date: today,
        comment: '',
        paid_amount: '0',
        payment_type: '5',
        items: [],
    });

    function returnContextFromSource(json) {
        return {
            promotions: json.promotions ?? [],
            saleDate: json.date,
        };
    }

    const returnSummary = source?.sell_discounts
        ? calcSaleReturnSummary(items, source.sell_discounts, returnContextFromSource(source), {
              ...manualDiscounts,
              vatPercent: parseFloat(vatPercent || 0),
          })
        : null;

    async function lookupSale() {
        setLookupError('');
        form.clearErrors();
        const res = await fetch(`${route('api.sales.lookup')}?invoice=${encodeURIComponent(invoiceQuery)}`, {
            credentials: 'include',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        if (!res.ok) {
            const message = json.message ?? 'Sale not found.';
            setLookupError(message);
            setSource(null);
            setItems([]);
            setManualDiscounts({});
            setVatPercent('');
            paymentsSeededForSellId.current = null;
            setPayments(buildInitialReturnPayments([], 0, paymentAccounts));
            form.setData({
                ...form.data,
                sell_id: '',
                paid_amount: '0',
                payment_type: '5',
            });
            if (res.status === 422) {
                toast.warning(message);
            }
            return;
        }
        const lines = json.items.map((i) => ({
            line_type: i.line_type ?? 'original',
            sell_product_id: i.sell_product_id,
            product_exchange_product_id: i.product_exchange_product_id ?? null,
            product_id: i.product_id,
            variation_id: i.variation_id,
            category_id: i.category_id,
            brand_id: i.brand_id,
            product_name: i.product_name,
            product_code: i.product_code,
            variation_label: i.variation_label ?? null,
            max_return_quantity: i.max_return_quantity,
            sold_quantity: i.sold_quantity,
            returned_elsewhere: i.returned_quantity ?? 0,
            returned_quantity: i.returned_quantity ?? 0,
            exchanged_quantity: i.exchanged_quantity ?? 0,
            exchange_invoice_number: i.exchange_invoice_number ?? null,
            line_discount: i.line_discount,
            promotion_discount: i.promotion_discount,
            promotion_id: i.promotion_id,
            promotion_details: i.promotion_details ?? null,
            unit_price: i.unit_price,
            quantity: '0',
        }));
        const sd = json.sell_discounts ?? {};
        const initialManual = buildInitialReturnDiscounts(sd);
        setSource(json);
        setItems(lines);
        setManualDiscounts(initialManual);
        const defaultVat = derivedVatPercent(json.sell_discounts);
        setVatPercent(defaultVat > 0 ? String(defaultVat) : '');
        paymentsSeededForSellId.current = null;
        setPayments(buildInitialReturnPayments(json.payments ?? [], 0, paymentAccounts));
        form.setData({ ...form.data, sell_id: String(json.id), paid_amount: '0', payment_type: '5' });
    }

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

    const returnNetAmount = returnSummary?.netAmount ?? 0;
    const paymentRequired = returnNetAmount > 0.009;
    const { totalPaid } = computeSplitSalePayment(payments, returnNetAmount);

    useEffect(() => {
        if (!source?.id || !returnSummary) {
            return;
        }

        const seedKey = `${source.id}:${returnNetAmount.toFixed(2)}`;

        if (paymentsSeededForSellId.current === seedKey) {
            return;
        }

        paymentsSeededForSellId.current = seedKey;
        setPayments(buildInitialReturnPayments(source.payments ?? [], returnNetAmount, paymentAccounts));
    }, [source, returnNetAmount, paymentAccounts, returnSummary]);

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
            .map(({ sell_product_id, product_exchange_product_id, quantity }) => ({
                sell_product_id,
                ...(product_exchange_product_id ? { product_exchange_product_id } : {}),
                quantity,
            }));

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
        form.post(route('inventory.sale-return.store'), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    // A replacement is grouped under the original line it came from. When it's literally the
    // same product (e.g. a like-for-like swap), showing two rows with the same name is still
    // confusing even when adjacent — merge those into one row with two small quantity inputs
    // (original pool vs replacement pool), since they're still two independent, separately
    // capped line items on the backend. A replacement for a *different* product is left as its
    // own row underneath — there's no repeated name to disambiguate there.
    const indexedItems = items.map((item, index) => ({ item, index }));
    const originals = indexedItems.filter(({ item }) => item.line_type !== 'replacement');
    const replacementsBySellProductId = indexedItems
        .filter(({ item }) => item.line_type === 'replacement')
        .reduce((map, entry) => {
            const key = entry.item.sell_product_id;
            (map[key] ??= []).push(entry);

            return map;
        }, {});

    function isSameProduct(a, b) {
        return (
            Number(a.product_id) === Number(b.product_id) &&
            Number(a.variation_id || 0) === Number(b.variation_id || 0)
        );
    }

    const rowGroups = originals.map((originalEntry) => {
        const replacements = replacementsBySellProductId[originalEntry.item.sell_product_id] ?? [];
        const [onlyReplacement, ...rest] = replacements;

        if (onlyReplacement && rest.length === 0 && isSameProduct(originalEntry.item, onlyReplacement.item)) {
            return { type: 'merged', original: originalEntry, replacement: onlyReplacement };
        }

        return { type: 'separate', original: originalEntry, replacements };
    });

    function renderReturnRow({ item, index: i }) {
        const stats = saleReturnLineStats(item);
        const sub = stats.returning * parseFloat(item.unit_price || 0);
        const overMax = stats.returning > stats.maxReturn;
        const isFullyReturned = stats.available <= 0 && stats.returning <= 0;
        const isReplacement = item.line_type === 'replacement';

        return (
            <tr key={i} className={isFullyReturned ? 'bg-muted/30 text-muted-foreground' : 'hover:bg-muted/20'}>
                <td className="px-3 py-2">
                    <div className="flex items-center gap-1.5">
                        <ProductNameWithCode
                            name={item.product_name}
                            code={item.product_code}
                            variation={item.variation_label}
                        />
                        {isReplacement && (
                            <Badge variant="outline" className="shrink-0 text-[10px] text-primary">
                                Replacement
                            </Badge>
                        )}
                    </div>
                    {isReplacement && (
                        <p className="mt-0.5 text-[11px] text-muted-foreground">From exchange {item.exchange_invoice_number}</p>
                    )}
                </td>
                <td className="px-3 py-2 text-right">{formatQty(stats.sold)}</td>
                <td className="px-3 py-2 text-right">{formatQty(stats.returnedOnSale)}</td>
                <td className="px-3 py-2 text-right text-muted-foreground">
                    {stats.exchanged > 0 ? formatQty(stats.exchanged) : '—'}
                </td>
                <td className="px-3 py-2 text-right font-medium">{formatQty(stats.available)}</td>
                <td className="px-3 py-2 text-right">৳{parseFloat(item.unit_price).toFixed(2)}</td>
                <td className="px-2 py-1.5 text-right">
                    <Input
                        type="number"
                        min="0"
                        max={item.max_return_quantity}
                        step="1"
                        value={item.quantity}
                        disabled={isFullyReturned}
                        onChange={(e) => updateReturnQty(i, e.target.value)}
                        onBlur={(e) => updateReturnQty(i, e.target.value)}
                        className={`${inputCls} ml-auto w-24 text-right ${overMax ? 'border-destructive' : ''}`}
                    />
                </td>
                <td className="px-3 py-2 text-right font-semibold">৳{sub.toFixed(2)}</td>
            </tr>
        );
    }

    function renderMergedRow(originalEntry, replacementEntry) {
        const { item: orig, index: origIndex } = originalEntry;
        const { item: repl, index: replIndex } = replacementEntry;
        const origStats = saleReturnLineStats(orig);
        const replStats = saleReturnLineStats(repl);
        const combinedAvailable = origStats.available + replStats.available;
        const combinedSub =
            origStats.returning * parseFloat(orig.unit_price || 0) +
            replStats.returning * parseFloat(repl.unit_price || 0);
        const origOverMax = origStats.returning > origStats.maxReturn;
        const replOverMax = replStats.returning > replStats.maxReturn;
        const origDisabled = origStats.available <= 0 && origStats.returning <= 0;
        const replDisabled = replStats.available <= 0 && replStats.returning <= 0;
        const isFullyReturned = origDisabled && replDisabled;

        return (
            <tr key={`merged-${origIndex}`} className={isFullyReturned ? 'bg-muted/30 text-muted-foreground' : 'hover:bg-muted/20'}>
                <td className="px-3 py-2">
                    <ProductNameWithCode name={orig.product_name} code={orig.product_code} variation={orig.variation_label} />
                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                        Includes {formatQty(replStats.sold)} replacement unit(s) from exchange {repl.exchange_invoice_number}
                    </p>
                </td>
                <td className="px-3 py-2 text-right">{formatQty(origStats.sold)}</td>
                <td className="px-3 py-2 text-right">{formatQty(origStats.returnedOnSale)}</td>
                <td className="px-3 py-2 text-right text-muted-foreground">
                    {origStats.exchanged > 0 ? formatQty(origStats.exchanged) : '—'}
                </td>
                <td className="px-3 py-2 text-right font-medium">{formatQty(combinedAvailable)}</td>
                <td className="px-3 py-2 text-right">৳{parseFloat(orig.unit_price).toFixed(2)}</td>
                <td className="px-2 py-1.5">
                    <div className="flex flex-col items-end gap-1">
                        {origStats.maxReturn > 0 && (
                            <div className="flex items-center justify-end gap-1.5">
                                <span className="w-10 text-right text-[10px] text-muted-foreground">Orig.</span>
                                <Input
                                    type="number"
                                    min="0"
                                    max={orig.max_return_quantity}
                                    step="1"
                                    value={orig.quantity}
                                    onChange={(e) => updateReturnQty(origIndex, e.target.value)}
                                    onBlur={(e) => updateReturnQty(origIndex, e.target.value)}
                                    className={`${inputCls} w-20 text-right ${origOverMax ? 'border-destructive' : ''}`}
                                />
                            </div>
                        )}
                        {replStats.maxReturn > 0 && (
                            <div className="flex items-center justify-end gap-1.5">
                                <span className="w-10 text-right text-[10px] text-muted-foreground">Repl.</span>
                                <Input
                                    type="number"
                                    min="0"
                                    max={repl.max_return_quantity}
                                    step="1"
                                    value={repl.quantity}
                                    onChange={(e) => updateReturnQty(replIndex, e.target.value)}
                                    onBlur={(e) => updateReturnQty(replIndex, e.target.value)}
                                    className={`${inputCls} w-20 text-right ${replOverMax ? 'border-destructive' : ''}`}
                                />
                            </div>
                        )}
                        {origStats.maxReturn <= 0 && replStats.maxReturn <= 0 && (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </div>
                </td>
                <td className="px-3 py-2 text-right font-semibold">৳{combinedSub.toFixed(2)}</td>
            </tr>
        );
    }

    function renderRowGroup(group) {
        if (group.type === 'merged') {
            return [renderMergedRow(group.original, group.replacement)];
        }

        return [group.original, ...group.replacements].map(renderReturnRow);
    }

    return (
        <>
            <Head title="Sale Return" />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Sale Return"
                    subtitle="Restore stock and refund customer."
                    icon={RotateCcw}
                    backRoute="inventory.sale-return.index"
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
                        {source && (
                            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <DateField
                                    label="Return Date"
                                    value={form.data.date}
                                    onChange={(v) => form.setData('date', v)}
                                    error={form.errors.date}
                                />
                            </div>
                        )}
                    </InventoryCard>

                    {rowGroups.length > 0 && (
                        <InventoryCard title="Return Items" icon={Package}>
                            <LineItemsTable
                                columns={[
                                    { id: 'product', header: 'Product' },
                                    { id: 'sold', header: 'Sold', align: 'right' },
                                    { id: 'returned', header: 'Returned', align: 'right' },
                                    { id: 'exchanged', header: 'Exchanged', align: 'right' },
                                    { id: 'available', header: 'Available', align: 'right' },
                                    { id: 'price', header: 'Unit Price', align: 'right' },
                                    { id: 'qty', header: 'Return Qty', align: 'right' },
                                    { id: 'sub', header: 'Sub Total', align: 'right' },
                                ]}
                            >
                                {rowGroups.flatMap(renderRowGroup)}
                            </LineItemsTable>
                        </InventoryCard>
                    )}

                    {source?.sell_discounts && (
                        <SaleReturnSourceDiscounts
                            key={source.id}
                            sellDiscounts={source.sell_discounts}
                            returnSummary={returnSummary}
                            manualDiscounts={manualDiscounts}
                            onManualDiscountChange={handleManualDiscountChange}
                            vatPercent={vatPercent}
                            onVatPercentChange={setVatPercent}
                        />
                    )}

                    {source && (
                        <>
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
                                    vatAmount={returnSummary?.returnVat ?? 0}
                                    vatPercent={parseFloat(vatPercent || 0)}
                                    parentPaymentInfo={
                                        returnSummary
                                            ? { paid: returnSummary.parentPaid, due: returnSummary.parentDue }
                                            : null
                                    }
                                    payments={payments}
                                    paymentAccounts={paymentAccounts}
                                    onPaymentsChange={setPayments}
                                    errors={form.errors}
                                    exceedsSale={returnSummary?.exceedsSale ?? false}
                                    maxAmount={returnSummary?.maxNetAmount ?? null}
                                    paymentRequired={paymentRequired}
                                />
                            </div>

                            <InventoryFormActions
                                cancelRoute="inventory.sale-return.index"
                                submitLabel="Save Return"
                                processing={form.processing}
                                disabled={!form.data.sell_id || items.length === 0}
                            />
                        </>
                    )}
                </form>
            </div>
        </>
    );
}
