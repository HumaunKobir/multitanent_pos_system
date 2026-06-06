import { route } from '@/lib/route';
import { Link } from '@inertiajs/react';
import { ArrowLeft, MessageSquare, Search } from 'lucide-react';
import { useState } from 'react';

import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { dateInputRightIconClassName } from '@/components/ui/date-kit';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export const inputCls = 'h-7 rounded-md border-border/60 text-xs px-2 focus:border-primary';

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
 * Backend receives payment_type only: 5 = party, 0 = cash (account selection is UI-only for now).
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
}) {
    const paid = parseFloat(paidAmount || 0);
    const due = Math.max(0, grossAmount - paid);
    const isParty = paymentMode === 'party';

    return (
        <InventoryCard title="Summary & Payment" icon={Icon}>
            <div className="space-y-3 text-xs">
                <div className="flex justify-between">
                    <span className="text-muted-foreground">Total Amount</span>
                    <span className="font-semibold">৳{grossAmount.toFixed(2)}</span>
                </div>

                <InventoryField label="Payment Option">
                    <select
                        className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs shadow-xs outline-none focus:border-primary focus:ring-[3px] focus:ring-ring/50"
                        value={paymentMode}
                        onChange={(e) => onPaymentModeChange(e.target.value)}
                    >
                        <option value="party">{partyLabel}</option>
                        {paymentAccounts.map((acc) => (
                            <option key={acc.id} value={`cash-${acc.id}`}>
                                {acc.label}
                            </option>
                        ))}
                    </select>
                    {!isParty && (
                        <p className="mt-1 text-[10px] text-muted-foreground">Cash / bank account (asset ledger).</p>
                    )}
                </InventoryField>

                <div className="flex items-center justify-between gap-4">
                    <Label className="text-xs text-muted-foreground">Paid Amount</Label>
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={paidAmount}
                        onChange={(e) => onPaidAmountChange(e.target.value)}
                        className={`${inputCls} w-28 text-right`}
                    />
                </div>
                {paidError && <p className="text-xs text-destructive">{paidError}</p>}

                {showDue && (
                    <div className="flex justify-between border-t border-border pt-2">
                        <span className="font-semibold text-destructive">Due Amount</span>
                        <span className="font-bold text-destructive">৳{due.toFixed(2)}</span>
                    </div>
                )}
            </div>
        </InventoryCard>
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
