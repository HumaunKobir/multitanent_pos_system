import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { route } from '@/lib/route';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { GroupedAccountSelect } from './grouped-account-select';
import { loadVoucherCreateDefaults } from './load-voucher-create-defaults';
import { VoucherFormFooter } from './voucher-form-footer';
import { VoucherModalShell } from './voucher-modal-shell';

const TYPE_JOURNAL = 1;

function emptyLine(side) {
    return { side, account_id: '', amount: '', narration: '' };
}

export default function JournalVoucherModal({ open, onOpenChange, item, accountsPicker, defaults }) {
    const isEditing = !!item?.id;

    const form = useForm({
        type: TYPE_JOURNAL,
        voucher_no: defaults?.voucher_no ?? '',
        date: defaults?.date ?? '',
        transaction_reference: defaults?.transaction_reference ?? '',
        narration: '',
        lines: [emptyLine('debit'), emptyLine('credit')],
    });

    useEffect(() => {
        if (!open) return;

        let cancelled = false;

        async function hydrate() {
            if (item) {
                const debits = item.lines?.filter((l) => l.side === 'debit') ?? [];
                const credits = item.lines?.filter((l) => l.side === 'credit') ?? [];
                form.setData({
                    type: TYPE_JOURNAL,
                    voucher_no: item.voucher_no ?? '',
                    date: item.date ?? '',
                    transaction_reference: item.transaction_reference ?? '',
                    narration: item.narration ?? '',
                    lines: [
                        ...(debits.length ? debits : [emptyLine('debit')]).map((l) => ({
                            side: 'debit',
                            account_id: String(l.account_id ?? ''),
                            amount: String(l.amount ?? ''),
                            narration: l.narration ?? '',
                        })),
                        ...(credits.length ? credits : [emptyLine('credit')]).map((l) => ({
                            side: 'credit',
                            account_id: String(l.account_id ?? ''),
                            amount: String(l.amount ?? ''),
                            narration: l.narration ?? '',
                        })),
                    ],
                });
                form.clearErrors();
                return;
            }

            const nextDefaults = await loadVoucherCreateDefaults('journal', defaults);
            if (cancelled) return;

            form.setData({
                type: TYPE_JOURNAL,
                voucher_no: nextDefaults.voucher_no ?? '',
                date: nextDefaults.date || defaults?.date || '',
                transaction_reference: nextDefaults.transaction_reference ?? '',
                narration: '',
                lines: [emptyLine('debit'), emptyLine('credit')],
            });
            form.clearErrors();
        }

        hydrate();

        return () => {
            cancelled = true;
        };
    }, [open, item]);

    const { debitTotal, creditTotal } = useMemo(() => {
        let debit = 0;
        let credit = 0;
        form.data.lines.forEach((l) => {
            const amt = parseFloat(l.amount) || 0;
            if (l.side === 'debit') debit += amt;
            else credit += amt;
        });
        return { debitTotal: debit, creditTotal: credit };
    }, [form.data.lines]);

    function updateLine(index, field, value) {
        const lines = [...form.data.lines];
        lines[index] = { ...lines[index], [field]: value };
        form.setData('lines', lines);
    }

    function addLine(side) {
        form.setData('lines', [...form.data.lines, emptyLine(side)]);
    }

    function removeLine(index) {
        form.setData(
            'lines',
            form.data.lines.filter((_, i) => i !== index),
        );
    }

    function submit(e) {
        e.preventDefault();
        const payload = {
            ...form.data,
            lines: form.data.lines
                .filter((l) => l.account_id && parseFloat(l.amount) > 0)
                .map((l) => ({
                    side: l.side,
                    account_id: Number(l.account_id),
                    amount: parseFloat(l.amount),
                    narration: l.narration || null,
                })),
        };
        form.transform(() => payload);

        if (isEditing) {
            form.put(route('accounts.vouchers.update', item.id), {
                onSuccess: () => {
                    onOpenChange(false);
                    form.reset();
                },
            });
        } else {
            form.post(route('accounts.vouchers.store'), {
                onSuccess: () => {
                    onOpenChange(false);
                    form.reset();
                },
            });
        }
    }

    const debitLines = form.data.lines.map((l, i) => ({ ...l, index: i })).filter((l) => l.side === 'debit');
    const creditLines = form.data.lines.map((l, i) => ({ ...l, index: i })).filter((l) => l.side === 'credit');

    function renderColumn(lines, side, label, addClass) {
        return (
            <div className="min-w-0 flex-1 rounded-lg border p-3">
                <div className="mb-3 flex items-center justify-between gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wide">{label}</span>
                    <button type="button" onClick={() => addLine(side)} className={`shrink-0 text-xs font-medium ${addClass}`}>
                        + ADD {label}
                    </button>
                </div>
                <div className="space-y-2">
                    {lines.map((line) => (
                        <div key={line.index} className="space-y-1">
                            <div className="flex min-w-0 items-center gap-2">
                                <div className="min-w-0 flex-1">
                                    <GroupedAccountSelect
                                        picker={accountsPicker}
                                        value={line.account_id}
                                        onChange={(v) => updateLine(line.index, 'account_id', v)}
                                    />
                                </div>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    className="w-28 shrink-0"
                                    value={line.amount}
                                    onChange={(e) => updateLine(line.index, 'amount', e.target.value)}
                                />
                                <button type="button" onClick={() => removeLine(line.index)} className="shrink-0 text-destructive">
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                            {(form.errors[`lines.${line.index}.account_id`] || form.errors[`lines.${line.index}.amount`]) && (
                                <p className="text-xs text-destructive">
                                    {form.errors[`lines.${line.index}.account_id`] || form.errors[`lines.${line.index}.amount`]}
                                </p>
                            )}
                        </div>
                    ))}
                </div>
                <div className="mt-3 flex justify-between border-t pt-2 text-sm font-semibold">
                    <span>TOTAL</span>
                    <span>{(side === 'debit' ? debitTotal : creditTotal).toFixed(2)}</span>
                </div>
            </div>
        );
    }

    return (
        <VoucherModalShell
            open={open}
            onOpenChange={onOpenChange}
            title={isEditing ? 'Edit Journal Voucher' : 'Create Journal Voucher'}
            subtitle="Create a new multi-entry journal record."
            maxWidthClass="sm:max-w-6xl"
            footer={
                <VoucherFormFooter
                    onCancel={() => onOpenChange(false)}
                    processing={form.processing}
                    submitLabel={isEditing ? 'Update Voucher' : 'Create Voucher'}
                />
            }
        >
            <form id="voucher-form" onSubmit={submit} className="space-y-4 px-5 py-4">
                {form.errors.general && <p className="text-sm text-destructive">{form.errors.general}</p>}
                {form.errors.lines && <p className="text-sm text-destructive">{form.errors.lines}</p>}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label>
                            Voucher No
                            <RequiredMark />
                        </Label>
                        <Input className="mt-1" value={form.data.voucher_no} readOnly={!isEditing} />
                        {form.errors.voucher_no && <p className="mt-1 text-xs text-destructive">{form.errors.voucher_no}</p>}
                    </div>
                    <div>
                        <Label>
                            Date
                            <RequiredMark />
                        </Label>
                        <Input type="date" className="mt-1" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                        {form.errors.date && <p className="mt-1 text-xs text-destructive">{form.errors.date}</p>}
                    </div>
                    <div>
                        <Label>Transaction Reference</Label>
                        <Input className="mt-1" value={form.data.transaction_reference} onChange={(e) => form.setData('transaction_reference', e.target.value)} />
                    </div>
                </div>
                <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                    {renderColumn(debitLines, 'debit', 'DEBIT', 'text-blue-600')}
                    {renderColumn(creditLines, 'credit', 'CREDIT', 'text-amber-600')}
                </div>
                <div>
                    <Label>Narration (Master)</Label>
                    <Textarea className="mt-1" placeholder="Add any narration or notes..." value={form.data.narration} onChange={(e) => form.setData('narration', e.target.value)} />
                </div>
            </form>
        </VoucherModalShell>
    );
}
