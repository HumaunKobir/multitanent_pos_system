import { computeSplitSalePayment } from '@/lib/sale-payment';
import { cn } from '@/lib/utils';
import { Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

function PaymentLineRow({ line, index, paymentAccounts, onUpdate, onRemove, selectClassName, inputClassName, errors }) {
    const [localAmount, setLocalAmount] = useState(line.amount ?? '');

    // Sync from parent only when the parent externally changed the amount
    // (not from our own onUpdate call that already updated local state).
    const lastSentRef = useRef(line.amount ?? '');
    const parentAmount = line.amount ?? '';
    if (parentAmount !== lastSentRef.current && parentAmount !== localAmount) {
        lastSentRef.current = parentAmount;
        setLocalAmount(parentAmount);
    }

    function handleAmountChange(e) {
        const val = e.target.value;
        setLocalAmount(val);
        lastSentRef.current = val;
        onUpdate(index, 'amount', val);
    }

    return (
        <div className="grid grid-cols-[minmax(0,1fr)_5.5rem_auto] items-start gap-1.5">
            <div>
                <select
                    className={selectClassName}
                    value={line.payment_account_id ?? ''}
                    onChange={(e) => onUpdate(index, 'payment_account_id', e.target.value)}
                >
                    <option value="">Account</option>
                    {paymentAccounts.map((account) => (
                        <option key={account.id} value={String(account.id)}>
                            {account.label}
                        </option>
                    ))}
                </select>
                {errors[`payments.${index}.payment_account_id`] && (
                    <p className="mt-0.5 text-[10px] text-destructive">
                        {errors[`payments.${index}.payment_account_id`]}
                    </p>
                )}
            </div>
            <div>
                <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={localAmount}
                    onChange={handleAmountChange}
                    className={cn(inputClassName, 'text-right tabular-nums')}
                    placeholder="0"
                />
                {errors[`payments.${index}.amount`] && (
                    <p className="mt-0.5 text-[10px] text-destructive">{errors[`payments.${index}.amount`]}</p>
                )}
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="mt-0.5 size-8 shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-red-100"
                onClick={() => onRemove(index)}
                aria-label="Remove payment line"
            >
                <Trash2 className="size-3.5" />
            </Button>
        </div>
    );
}

export function SalePaymentLines({
    payments = [],
    paymentAccounts = [],
    netAmount,
    onChange,
    errors = {},
    inputClassName,
    compact = false,
    hideSummary = false,
}) {
    const { totalPaid, dueAmount, remaining } = computeSplitSalePayment(payments, netAmount);

    function updateLine(index, field, value) {
        onChange(
            payments.map((line, lineIndex) => (lineIndex === index ? { ...line, [field]: value } : line)),
        );
    }

    function addLine() {
        onChange([...payments, { payment_account_id: '', amount: '' }]);
    }

    function removeLine(index) {
        if (payments.length <= 1) {
            onChange([{ payment_account_id: paymentAccounts[0] ? String(paymentAccounts[0].id) : '', amount: '0' }]);

            return;
        }

        onChange(payments.filter((_, lineIndex) => lineIndex !== index));
    }

    const selectClassName = cn(
        'h-8 w-full rounded-none border border-blue-200 bg-white px-2 text-xs text-blue-950 outline-none focus:border-blue-600',
        compact && 'h-8',
    );

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <Label className={cn('text-muted-foreground', compact ? 'text-[10px]' : 'text-xs')}>
                    Payment Accounts
                </Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn('h-7 gap-1 px-2 text-[10px] dark:bg-white dark:border-blue-200 dark:text-blue-950 dark:hover:bg-blue-50', compact && 'h-7')}
                    onClick={addLine}
                >
                    <Plus className="size-3" />
                    Add
                </Button>
            </div>

            {payments.map((line, index) => (
                <PaymentLineRow
                    key={index}
                    line={line}
                    index={index}
                    paymentAccounts={paymentAccounts}
                    onUpdate={updateLine}
                    onRemove={removeLine}
                    selectClassName={selectClassName}
                    inputClassName={inputClassName}
                    errors={errors}
                />
            ))}

            {!hideSummary && (
                <div className="space-y-1 border border-blue-100 bg-blue-50/50 px-2 py-1.5 text-[11px]">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground">Total Paid</span>
                        <span className="font-medium tabular-nums">৳{totalPaid.toFixed(2)}</span>
                    </div>
                    {dueAmount > 0 && (
                        <div className="flex items-center justify-between gap-2 text-red-700">
                            <span>Due</span>
                            <span className="font-medium tabular-nums">৳{dueAmount.toFixed(2)}</span>
                        </div>
                    )}
                    {remaining > 0 && totalPaid > 0 && (
                        <div className="flex items-center justify-between gap-2 text-amber-700">
                            <span>Remaining</span>
                            <span className="font-medium tabular-nums">৳{remaining.toFixed(2)}</span>
                        </div>
                    )}
                </div>
            )}

            {errors.payments && <p className="text-[10px] text-destructive">{errors.payments}</p>}
        </div>
    );
}
