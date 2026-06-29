import { SalePaymentLines } from '@/components/inventory/sale-payment-lines';
import { SellCoinFields } from '@/components/inventory/sell-coin-fields';
import { route } from '@/lib/route';
import { Link } from '@inertiajs/react';
import { ArrowLeft, MessageSquare, Percent, Search } from 'lucide-react';

import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { dateInputRightIconClassName } from '@/components/ui/date-kit';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export const inputCls = 'h-7 rounded-md border-border/60 text-xs px-2 focus:border-primary';

export function roundCurrency(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

export function InventoryCard({ title, icon: Icon, children }) {
    return (
        <div className="rounded-lg border bg-card shadow-sm">
            <div className="flex items-center gap-2.5 rounded-t-lg bg-blue-950 px-4 py-2.5">
                {Icon && (
                    <div className="flex size-6 items-center justify-center rounded bg-white/15">
                        <Icon className="size-3.5 text-white" />
                    </div>
                )}
                <h2 className="text-sm font-semibold uppercase tracking-wide text-white">{title}</h2>
            </div>
            <div className="p-2">{children}</div>
        </div>
    );
}

export function InventoryField({ label, required, error, children }) {
    return (
        <div>
            <Label className="mb-1 block text-xs font-medium">
                {label}
                {required && <RequiredMark />}
            </Label>
            {children}
            {error && <p className="mt-0.5 text-xs text-destructive">{error}</p>}
        </div>
    );
}

export function InventoryPageHeader({ title, subtitle, icon: Icon, backRoute }) {
    return (
        <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
            <div className="flex items-center gap-3">
                {Icon && (
                    <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                        <Icon className="size-4 text-white" />
                    </div>
                )}
                <div>
                    <h1 className="text-base font-semibold text-white">{title}</h1>
                    {subtitle && <p className="text-xs text-white/60">{subtitle}</p>}
                </div>
            </div>
            <Button
                size="sm"
                asChild
                className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
            >
                <Link href={route(backRoute)}>
                    <ArrowLeft className="size-3.5" />
                    Back
                </Link>
            </Button>
        </div>
    );
}

export function InventoryFormActions({ cancelRoute, submitLabel, processing, disabled }) {
    return (
        <div className="flex justify-end gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                asChild
            >
                <Link href={route(cancelRoute)}>Cancel</Link>
            </Button>
            <Button
                type="submit"
                size="sm"
                disabled={processing || disabled}
                className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/50"
            >
                {processing ? 'Saving…' : submitLabel}
            </Button>
        </div>
    );
}

/**
 * payment_mode: 'party' | `cash-{accountId}`
 * Backend receives payment_type: 5 = party (supplier account), 0 = cash/bank refund.
 */
export function PaymentSummaryCard({
    Icon,
    grossAmount,
    paidAmount,
    onPaidAmountChange,
    paymentMode,
    onPaymentModeChange,
    paymentAccounts = [],
    partyLabel = 'On Account',
    paidError,
    showDue = true,
    subtotalAmount = null,
    discountAmount = 0,
    vatAmount = 0,
    vatPercent = 0,
    onDiscountAmountChange = null,
    discountError = null,
    parentPaymentInfo = null,
    paidLabel = 'Paid Amount',
    paidReadOnly = false,
    partyPaidHint = 'The full return amount settles on the supplier account. No cash or bank entry is posted.',
    settlementLineLabel = null,
    dueLabel = 'Due Amount',
    showPaidAmount = true,
}) {
    const isParty = paymentMode === 'party';
    const paid = isParty ? 0 : parseFloat(paidAmount || 0);
    const due = Math.max(0, grossAmount - paid);
    const showSettlementLine = settlementLineLabel && grossAmount > 0.009;
    const showDiscountBreakdown = subtotalAmount != null && (discountAmount > 0.009 || vatAmount > 0.009);

    function handlePaymentModeChange(mode) {
        onPaymentModeChange(mode);

        if (mode === 'party') {
            onPaidAmountChange('0');
        }
    }

    return (
        <InventoryCard title="Summary & Payment" icon={Icon}>
            <div className="space-y-3 text-xs">
                {showDiscountBreakdown && (
                    <>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Gross Amount</span>
                            <span>৳{subtotalAmount.toFixed(2)}</span>
                        </div>
                        {onDiscountAmountChange ? (
                            <div>
                                <div className="flex items-center justify-between gap-4">
                                    <Label className="text-xs text-muted-foreground">Discount</Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={discountAmount}
                                        onChange={(e) => onDiscountAmountChange(e.target.value)}
                                        className={`${inputCls} w-28 text-right`}
                                    />
                                </div>
                                {discountError && <p className="mt-1 text-xs text-destructive">{discountError}</p>}
                            </div>
                        ) : discountAmount > 0.009 ? (
                            <div className="flex justify-between text-destructive">
                                <span>Discount</span>
                                <span>-৳{discountAmount.toFixed(2)}</span>
                            </div>
                        ) : null}
                        {vatAmount > 0.009 && (
                            <div className="flex justify-between text-emerald-600">
                                <span>VAT {vatPercent > 0.009 ? `(${vatPercent.toFixed(2)}%)` : ''}</span>
                                <span>+৳{vatAmount.toFixed(2)}</span>
                            </div>
                        )}
                    </>
                )}
                <div className="flex justify-between">
                    <span className="text-muted-foreground">Total Amount</span>
                    <span className="font-semibold">৳{grossAmount.toFixed(2)}</span>
                </div>

                {showSettlementLine && (
                    <div className="flex justify-between border-t border-border pt-2">
                        <span className="text-muted-foreground">{settlementLineLabel}</span>
                        <span className="font-semibold">৳{grossAmount.toFixed(2)}</span>
                    </div>
                )}

                {parentPaymentInfo && (parentPaymentInfo.paid > 0.009 || parentPaymentInfo.due > 0.009) && (
                    <p className="text-[10px] leading-relaxed text-muted-foreground">
                        Original sale paid ৳{parentPaymentInfo.paid.toFixed(2)}
                        {parentPaymentInfo.due > 0.009 ? ` · due ৳${parentPaymentInfo.due.toFixed(2)}` : ''}. Refund
                        cannot exceed the paid portion; remaining return value reduces customer due.
                    </p>
                )}

                <InventoryField label="Payment Option">
                    <select
                        className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs shadow-xs outline-none focus:border-primary focus:ring-[3px] focus:ring-ring/50"
                        value={paymentMode}
                        onChange={(e) => handlePaymentModeChange(e.target.value)}
                    >
                        <option value="party">{partyLabel}</option>
                        {paymentAccounts.map((acc) => (
                            <option key={acc.id} value={`cash-${acc.id}`}>
                                {acc.label}
                            </option>
                        ))}
                    </select>
                    {isParty ? (
                        <p className="mt-1 text-[10px] text-muted-foreground">{partyPaidHint}</p>
                    ) : (
                        <p className="mt-1 text-[10px] text-muted-foreground">
                            Cash / bank account (asset ledger). Enter the refund received in Paid Amount.
                        </p>
                    )}
                </InventoryField>

                {showPaidAmount && (
                    <>
                        <div className="flex items-center justify-between gap-4">
                            <Label className="text-xs text-muted-foreground">{paidLabel}</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={paidAmount}
                                onChange={(e) => onPaidAmountChange(e.target.value)}
                                readOnly={paidReadOnly || isParty}
                                className={`${inputCls} w-28 text-right ${paidReadOnly || isParty ? 'bg-muted/50' : ''}`}
                            />
                        </div>
                        {paidError && <p className="text-xs text-destructive">{paidError}</p>}
                    </>
                )}

                {showDue && (
                    <div className="flex justify-between border-t border-border pt-2">
                        <span className="font-semibold text-destructive">{dueLabel}</span>
                        <span className="font-bold text-destructive">৳{due.toFixed(2)}</span>
                    </div>
                )}
            </div>
        </InventoryCard>
    );
}

export function SaleReturnRefundCard({
    Icon,
    grossAmount,
    subtotalAmount = null,
    discountAmount = 0,
    vatAmount = 0,
    vatPercent = '',
    onVatPercentChange,
    parentPaymentInfo = null,
    payments = [],
    paymentAccounts = [],
    onPaymentsChange,
    errors = {},
    exceedsSale = false,
    maxAmount = null,
}) {
    const showDiscountBreakdown = subtotalAmount != null;

    return (
        <InventoryCard title="Summary & Payment" icon={Icon}>
            <div className="space-y-3 text-xs">
                {showDiscountBreakdown && (
                    <>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Gross Amount</span>
                            <span>৳{subtotalAmount.toFixed(2)}</span>
                        </div>
                        {discountAmount > 0.009 && (
                            <div className="flex justify-between text-destructive">
                                <span>Discount</span>
                                <span>-৳{discountAmount.toFixed(2)}</span>
                            </div>
                        )}
                        {onVatPercentChange != null && (
                            <div className="flex items-center justify-between gap-2">
                                <label className="flex items-center gap-1 text-blue-600 dark:text-blue-400">
                                    <Percent className="size-3" />
                                    VAT %
                                </label>
                                <div className="flex items-center gap-1">
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={vatPercent}
                                        onChange={(e) => onVatPercentChange(e.target.value)}
                                        placeholder="0"
                                        className={`${inputCls} w-20 text-right`}
                                    />
                                    {vatAmount > 0.009 && (
                                        <span className="text-blue-600 dark:text-blue-400">+৳{vatAmount.toFixed(2)}</span>
                                    )}
                                </div>
                            </div>
                        )}
                        {onVatPercentChange == null && vatAmount > 0.009 && (
                            <div className="flex justify-between text-blue-600 dark:text-blue-400">
                                <span>VAT {parseFloat(vatPercent || 0) > 0.009 ? `(${parseFloat(vatPercent).toFixed(2)}%)` : ''}</span>
                                <span>+৳{vatAmount.toFixed(2)}</span>
                            </div>
                        )}
                    </>
                )}
                <div className="flex justify-between">
                    <span className="text-muted-foreground">Total Amount</span>
                    <span className="font-semibold">৳{grossAmount.toFixed(2)}</span>
                </div>

                {exceedsSale && maxAmount != null && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-2.5 py-2 text-[10px] leading-relaxed text-destructive">
                        Return total exceeds the sale value (max ৳{maxAmount.toFixed(2)}). Increase the discount or
                        reduce the VAT before saving.
                    </div>
                )}

                {parentPaymentInfo && (parentPaymentInfo.paid > 0.009 || parentPaymentInfo.due > 0.009) && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-[10px] leading-relaxed text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
                        Original sale paid ৳{parentPaymentInfo.paid.toFixed(2)}
                        {parentPaymentInfo.due > 0.009 ? ` · due ৳${parentPaymentInfo.due.toFixed(2)}` : ''}. Return
                        value will reduce customer due.
                    </div>
                )}

                {onPaymentsChange && (
                    <div className="border-t border-border pt-3">
                        <SalePaymentLines
                            payments={payments}
                            paymentAccounts={paymentAccounts}
                            netAmount={grossAmount}
                            onChange={onPaymentsChange}
                            errors={errors}
                            inputClassName={inputCls}
                            dueLabel="Due Refund"
                        />
                    </div>
                )}
            </div>
        </InventoryCard>
    );
}

const returnDiscountSelectCls =
    'h-7 rounded-md border border-border/60 bg-background px-1.5 text-xs shadow-xs outline-none focus:border-primary focus:ring-[3px] focus:ring-ring/50';

/**
 * Discounts & VAT for a sale return. Invoice discount supports flat (৳) or percent (%) types,
 * round off is a flat amount, and VAT is a percentage charged on the taxable base. All three
 * default to the source sale's values and can be edited, changed, or cleared (deleted → 0).
 * VAT is intentionally rendered after round off.
 */
export function SaleReturnSourceDiscounts({
    sellDiscounts,
    returnSummary,
    manualDiscounts = {},
    onManualDiscountChange,
    vatPercent = '',
    onVatPercentChange,
}) {
    if (!sellDiscounts) {
        return null;
    }

    const ret = returnSummary?.returnDiscounts ?? {};
    const invoiceType = manualDiscounts.invoiceType || 'flat';
    const returnVat = returnSummary?.returnVat ?? 0;
    const computedInvoice = parseFloat(ret.invoice || 0);
    const computedRoundOff = parseFloat(ret.roundOff || 0);

    return (
        <InventoryCard title="Discounts & VAT" icon={Percent}>
            <div className="space-y-3 text-xs">
                <div className="grid grid-cols-[1fr_auto] items-center gap-2">
                    <div className="flex flex-col">
                        <span className="text-muted-foreground">Invoice discount</span>
                        <span className="text-[10px] text-muted-foreground">
                            Return: ৳{computedInvoice.toFixed(2)}
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        <select
                            value={invoiceType}
                            onChange={(e) => onManualDiscountChange?.('invoiceType', e.target.value)}
                            className={returnDiscountSelectCls}
                        >
                            <option value="flat">৳</option>
                            <option value="percent">%</option>
                        </select>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={manualDiscounts.invoice ?? ''}
                            onChange={(e) => onManualDiscountChange?.('invoice', e.target.value)}
                            placeholder="0"
                            className={`${inputCls} w-24 text-right`}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-[1fr_auto] items-center gap-2">
                    <div className="flex flex-col">
                        <span className="text-muted-foreground">Round off</span>
                        <span className="text-[10px] text-muted-foreground">
                            Return: ৳{computedRoundOff.toFixed(2)}
                        </span>
                    </div>
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={manualDiscounts.roundOff ?? ''}
                        onChange={(e) => onManualDiscountChange?.('roundOff', e.target.value)}
                        placeholder="0"
                        className={`${inputCls} w-24 text-right`}
                    />
                </div>

                {onVatPercentChange != null && (
                    <div className="grid grid-cols-[1fr_auto] items-center gap-2 border-t border-border pt-3">
                        <div className="flex flex-col">
                            <span className="flex items-center gap-1 text-blue-600 dark:text-blue-400">
                                <Percent className="size-3" />
                                VAT %
                            </span>
                            <span className="text-[10px] text-muted-foreground">
                                Return: ৳{returnVat.toFixed(2)}
                            </span>
                        </div>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={vatPercent}
                            onChange={(e) => onVatPercentChange(e.target.value)}
                            placeholder="0"
                            className={`${inputCls} w-24 text-right`}
                        />
                    </div>
                )}
            </div>
        </InventoryCard>
    );
}

/**
 * Editable discount controls for product exchange (invoice, special, round off, coins).
 */
export function ProductExchangeDiscountsCard({
    summary,
    manualDiscounts = {},
    onManualDiscountChange,
    specialDiscounts = [],
    coinSettings = null,
    coinInfo = null,
    coinInfoLoading = false,
    customerId = null,
    walkInCustomerId = null,
    coinBalanceOffset = 0,
}) {
    if (!summary) {
        return null;
    }

    const invoiceType = manualDiscounts.invoiceType || 'flat';

    return (
        <InventoryCard title="Discounts & Payment Adjustments" icon={Percent}>
            <div className="space-y-3 text-xs">
                <div className="grid grid-cols-[1fr_auto] items-center gap-2">
                    <div className="flex flex-col">
                        <span className="text-muted-foreground">Invoice discount</span>
                        <span className="text-[10px] text-muted-foreground">
                            Applied: -৳{summary.invoiceDiscountAmount.toFixed(2)}
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        <select
                            value={invoiceType}
                            onChange={(e) => onManualDiscountChange?.('invoiceType', e.target.value)}
                            className={returnDiscountSelectCls}
                        >
                            <option value="flat">৳</option>
                            <option value="percent">%</option>
                        </select>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={manualDiscounts.invoice ?? ''}
                            onChange={(e) => onManualDiscountChange?.('invoice', e.target.value)}
                            placeholder="0"
                            className={`${inputCls} w-24 text-right`}
                        />
                    </div>
                </div>

                {specialDiscounts.length > 0 && (
                    <div className="grid grid-cols-[1fr_auto] items-center gap-2">
                        <div className="flex flex-col">
                            <span className="text-muted-foreground">Special discount</span>
                            <span className="text-[10px] text-muted-foreground">
                                Applied: -৳{summary.specialDiscountAmount.toFixed(2)}
                            </span>
                        </div>
                        <select
                            value={manualDiscounts.specialDiscountId ?? ''}
                            onChange={(e) => onManualDiscountChange?.('specialDiscountId', e.target.value)}
                            className={`${returnDiscountSelectCls} min-w-36`}
                        >
                            <option value="">None</option>
                            {specialDiscounts.map((discount) => (
                                <option key={discount.id} value={discount.id}>
                                    {discount.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {summary.promotionDiscountTotal > 0 && (
                    <div className="flex justify-between text-purple-700 dark:text-purple-400">
                        <span>Promotion discount (auto)</span>
                        <span>-৳{summary.promotionDiscountTotal.toFixed(2)}</span>
                    </div>
                )}

                {summary.lineDiscountTotal > 0 && (
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Line discount (same product)</span>
                        <span>-৳{summary.lineDiscountTotal.toFixed(2)}</span>
                    </div>
                )}

                {summary.vatAmount > 0 && (
                    <div className="flex justify-between text-emerald-600">
                        <span>VAT ({summary.vatPercent.toFixed(2)}%)</span>
                        <span>+৳{summary.vatAmount.toFixed(2)}</span>
                    </div>
                )}

                <SellCoinFields
                    customerId={customerId}
                    walkInCustomerId={walkInCustomerId}
                    coinSettings={coinSettings}
                    coinInfo={coinInfo}
                    coinInfoLoading={coinInfoLoading}
                    coinsRedeemed={manualDiscounts.coinsRedeemed ?? ''}
                    onCoinsRedeemedChange={(value) => onManualDiscountChange?.('coinsRedeemed', value)}
                    netBeforeCoin={summary.netBeforeCoin}
                    earnBase={summary.netNewAmount}
                    balanceOffset={coinBalanceOffset}
                    inputClassName={inputCls}
                />

                <div className="grid grid-cols-[1fr_auto] items-center gap-2 border-t border-border pt-3">
                    <div className="flex flex-col">
                        <span className="text-muted-foreground">Round off</span>
                        <span className="text-[10px] text-muted-foreground">
                            Applied: -৳{summary.roundOffAmount.toFixed(2)}
                        </span>
                    </div>
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={manualDiscounts.roundOff ?? ''}
                        onChange={(e) => onManualDiscountChange?.('roundOff', e.target.value)}
                        placeholder="0"
                        className={`${inputCls} w-24 text-right`}
                    />
                </div>
            </div>
        </InventoryCard>
    );
}

/**
 * Read-only informational summary of discounts that exist on the source sale but are
 * NOT implemented in the exchange (promotion, special, coin). Shown so the user knows
 * the sale had them without carrying them over to the exchange totals.
 */
export function SaleSourceDiscountsInfo({ sellDiscounts }) {
    if (!sellDiscounts) {
        return null;
    }

    const promotionDiscount = parseFloat(sellDiscounts.promotion_discount_total || 0);
    const specialDiscount = parseFloat(sellDiscounts.special_discount_amount || 0);
    const coinDiscount = parseFloat(sellDiscounts.coin_discount_amount || 0);
    const lineDiscount = parseFloat(sellDiscounts.line_discount_total || 0);

    const hasAny =
        promotionDiscount > 0 ||
        specialDiscount > 0 ||
        coinDiscount > 0 ||
        lineDiscount > 0;

    if (!hasAny) {
        return null;
    }

    return (
        <div className="mt-3 rounded-md border border-amber-300/60 bg-amber-50 p-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200">
            <p className="mb-1 font-semibold">
                Source sale discounts (not applied to this exchange):
            </p>
            <div className="flex flex-wrap gap-x-4 gap-y-0.5">
                {lineDiscount > 0 && (
                    <span>
                        Line discount: ৳{lineDiscount.toFixed(2)}
                    </span>
                )}
                {promotionDiscount > 0 && (
                    <span>
                        Promotion discount: ৳{promotionDiscount.toFixed(2)}
                    </span>
                )}
                {specialDiscount > 0 && (
                    <span>
                        Special discount: ৳{specialDiscount.toFixed(2)}
                    </span>
                )}
                {coinDiscount > 0 && (
                    <span>
                        Coin discount: ৳{coinDiscount.toFixed(2)}
                    </span>
                )}
            </div>
        </div>
    );
}

export function paymentModeToType(paymentMode) {
    return paymentMode === 'party' ? '5' : '0';
}

export function paymentModeToAccountId(paymentMode) {
    if (paymentMode === 'party' || !paymentMode.startsWith('cash-')) {
        return null;
    }

    const id = paymentMode.replace('cash-', '');

    return id ? Number(id) : null;
}

export function paymentTypeToMode(paymentType, paymentAccountId = null) {
    if (String(paymentType ?? '0') === '5') {
        return 'party';
    }

    return paymentAccountId ? `cash-${paymentAccountId}` : 'cash-0';
}

export function InvoiceLookupField({ label, placeholder, value, onChange, onSearch, error, hint }) {
    function handleKeyDown(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            onSearch();
        }
    }

    return (
        <InventoryField label={label} required error={error}>
            <div className="flex gap-2">
                <Input
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    className="h-8 text-xs"
                />
                <Button type="button" size="sm" variant="secondary" onClick={onSearch}>
                    <Search className="size-3.5" />
                    Load
                </Button>
            </div>
            {hint && <p className="mt-1 text-[10px] text-muted-foreground">{hint}</p>}
        </InventoryField>
    );
}

export function formatProductLabel(name, code) {
    if (!name) {
        return '—';
    }

    return code ? `${name} (${code})` : name;
}

export function ProductNameWithCode({ name, code, className = '' }) {
    return (
        <div className={className}>
            <p className="font-medium">{name ?? '—'}</p>
            {code ? <p className="text-[10px] text-muted-foreground">{code}</p> : null}
        </div>
    );
}

/** Whole-number quantity for display/input (no decimals). */
export function formatQty(value) {
    if (value === '' || value === null || value === undefined) {
        return '';
    }

    const n = Math.floor(Number(value));

    return Number.isFinite(n) ? String(Math.max(0, n)) : '';
}

/**
 * Clamps quantity to [0, max] as integers. Calls onExceed when above max.
 */
export function clampQuantityInput(rawValue, maxQty, onExceed) {
    if (rawValue === '' || rawValue === null || rawValue === undefined) {
        return '';
    }

    const parsed = Math.floor(Number(rawValue));

    if (!Number.isFinite(parsed)) {
        return '';
    }

    const max = Math.floor(Number(maxQty));
    let qty = Math.max(0, parsed);

    if (Number.isFinite(max) && qty > max) {
        onExceed?.(max);
        qty = max;
    }

    return String(qty);
}

export function LineItemsTable({ columns, children, emptyMessage = 'No line items.' }) {
    if (!children) {
        return <p className="py-6 text-center text-xs text-muted-foreground">{emptyMessage}</p>;
    }

    return (
        <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full table-auto text-xs">
                <thead className="bg-muted/40 text-xs uppercase tracking-wide">
                    <tr>
                        {columns.map((col) => (
                            <th
                                key={col.id}
                                className={`whitespace-nowrap px-3 py-2 font-semibold ${col.align === 'right' ? 'text-right' : 'text-left'}`}
                            >
                                {col.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">{children}</tbody>
            </table>
        </div>
    );
}

export function DateField({ label, value, onChange, error, required = true }) {
    return (
        <InventoryField label={label} required={required} error={error}>
            <Input
                type="date"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={`h-8 text-xs ${dateInputRightIconClassName}`}
            />
        </InventoryField>
    );
}

export function CommentCard({ value, onChange, error }) {
    return (
        <InventoryCard title="Comment" icon={MessageSquare}>
            <InventoryField label="Note" error={error}>
                <textarea
                    rows={5}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="Optional note…"
                    className="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs outline-none focus:border-primary focus:ring-[3px] focus:ring-ring/50"
                />
            </InventoryField>
        </InventoryCard>
    );
}
