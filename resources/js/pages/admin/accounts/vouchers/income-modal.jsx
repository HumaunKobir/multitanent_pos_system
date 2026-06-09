import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { route } from '@/lib/route';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { FlatAccountSelect, GroupedAccountSelect } from './grouped-account-select';
import { VoucherContactSelect } from './voucher-contact-select';
import { VoucherFormFooter } from './voucher-form-footer';
import { VoucherModalShell } from './voucher-modal-shell';

const TYPE_INCOME = 2;

function emptyRow() {
    return { account_id: '', amount: '', narration: '' };
}

export default function IncomeVoucherModal({ open, onOpenChange, item, accountsPicker, assetAccounts, contacts, defaults }) {
    const isEditing = !!item?.id;

    const form = useForm({
        type: TYPE_INCOME,
        voucher_no: defaults?.voucher_no ?? '',
        date: defaults?.date ?? '',
        transaction_reference: defaults?.transaction_reference ?? '',
        party_key: '',
        payment_account_id: '',
        debit_description: '',
        narration: '',
        lines: [emptyRow()],
    });

    useEffect(() => {
        if (!open) return;
        if (item) {
            form.setData({
                type: TYPE_INCOME,
                voucher_no: item.voucher_no ?? '',
                date: item.date ?? '',
                transaction_reference: item.transaction_reference ?? '',
                party_key: item.party_key ?? '',
                payment_account_id: String(item.payment_account_id ?? ''),
                debit_description: '',
                narration: item.narration ?? '',
                lines: (item.lines?.length ? item.lines : [emptyRow()]).map((l) => ({
                    account_id: String(l.account_id ?? ''),
                    amount: String(l.amount ?? ''),
                    narration: l.narration ?? '',
                })),
            });
        } else {
            form.setData({
                type: TYPE_INCOME,
                voucher_no: defaults?.voucher_no ?? '',
                date: defaults?.date ?? '',
                transaction_reference: defaults?.transaction_reference ?? '',
                party_key: '',
                payment_account_id: '',
                debit_description: '',
                narration: '',
                lines: [emptyRow()],
            });
        }
        form.clearErrors();
    }, [open, item]);

    const total = useMemo(() => form.data.lines.reduce((s, l) => s + (parseFloat(l.amount) || 0), 0), [form.data.lines]);

    function updateLine(i, field, value) {
        const lines = [...form.data.lines];
        lines[i] = { ...lines[i], [field]: value };
        form.setData('lines', lines);
    }

    function submit(e) {
        e.preventDefault();
        const payload = {
            ...form.data,
            party_key: form.data.party_key || null,
            payment_account_id: Number(form.data.payment_account_id),
            lines: form.data.lines
                .filter((l) => l.account_id && parseFloat(l.amount) > 0)
                .map((l) => ({
                    account_id: Number(l.account_id),
                    amount: parseFloat(l.amount),
                    narration: l.narration || null,
                })),
        };
        form.transform(() => payload);

        if (isEditing) {
            form.put(route('accounts.vouchers.update', item.id), { onSuccess: () => onOpenChange(false) });
        } else {
            form.post(route('accounts.vouchers.store'), { onSuccess: () => onOpenChange(false) });
        }
    }

    return (
        <VoucherModalShell
            open={open}
            onOpenChange={onOpenChange}
            title={isEditing ? 'Edit Income Voucher' : 'Create Income Voucher'}
            subtitle="Create a new income record."
            footer={<VoucherFormFooter onCancel={() => onOpenChange(false)} processing={form.processing} submitLabel={isEditing ? 'Update Voucher' : 'Create Voucher'} />}
        >
            <form id="voucher-form" onSubmit={submit} className="space-y-4 px-5 py-4">
                {form.errors.general && <p className="text-sm text-destructive">{form.errors.general}</p>}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label>
                            Voucher No
                            <RequiredMark />
                        </Label>
                        <Input className="mt-1" value={form.data.voucher_no} onChange={(e) => form.setData('voucher_no', e.target.value)} />
                    </div>
                    <div>
                        <Label>
                            Date
                            <RequiredMark />
                        </Label>
                        <Input type="date" className="mt-1" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                    </div>
                    <div>
                        <Label>Received By</Label>
                        <VoucherContactSelect
                            contacts={contacts}
                            value={form.data.party_key}
                            onChange={(value) => form.setData('party_key', value)}
                        />
                        {form.errors.party_key && <p className="mt-1 text-sm text-destructive">{form.errors.party_key}</p>}
                    </div>
                </div>
                <div className="rounded-lg border">
                    <div className="flex items-center justify-between border-b px-3 py-2">
                        <span className="text-sm font-semibold">Income Entries</span>
                        <Button type="button" size="sm" variant="outline" onClick={() => form.setData('lines', [...form.data.lines, emptyRow()])}>
                            <Plus className="mr-1 size-3.5" /> Add Row
                        </Button>
                    </div>
                    <div className="divide-y p-3">
                        {form.data.lines.map((line, i) => (
                            <div key={i} className="grid gap-2 py-2 sm:grid-cols-[1fr_1fr_120px_32px]">
                                <GroupedAccountSelect picker={accountsPicker} value={line.account_id} onChange={(v) => updateLine(i, 'account_id', v)} placeholder="Select head..." />
                                <Input placeholder="Entry narration..." value={line.narration} onChange={(e) => updateLine(i, 'narration', e.target.value)} />
                                <Input type="number" min="0" step="0.01" placeholder="0.00" value={line.amount} onChange={(e) => updateLine(i, 'amount', e.target.value)} />
                                <button type="button" onClick={() => form.setData('lines', form.data.lines.filter((_, j) => j !== i))} className="text-destructive">
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-between border-t px-3 py-2 font-semibold">
                        <span>Total Amount</span>
                        <span>৳ {total.toFixed(2)}</span>
                    </div>
                </div>
                <div className="rounded-lg border p-3">
                    <p className="mb-2 text-sm font-semibold">Receipt Details</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>
                                Received In (Debit)
                                <RequiredMark />
                            </Label>
                            <div className="mt-1">
                                <FlatAccountSelect accounts={assetAccounts} value={form.data.payment_account_id} onChange={(v) => form.setData('payment_account_id', v)} placeholder="Select account..." />
                            </div>
                        </div>
                        <div>
                            <Label>Transaction Reference</Label>
                            <Input className="mt-1" value={form.data.transaction_reference} onChange={(e) => form.setData('transaction_reference', e.target.value)} />
                        </div>
                        <div className="sm:col-span-2">
                            <Label>Debit Description</Label>
                            <Input className="mt-1" value={form.data.debit_description} onChange={(e) => form.setData('debit_description', e.target.value)} placeholder="Receipt account narration" />
                        </div>
                    </div>
                </div>
                <div>
                    <Label>Remarks</Label>
                    <Textarea className="mt-1" placeholder="Add any remarks or notes..." value={form.data.narration} onChange={(e) => form.setData('narration', e.target.value)} />
                </div>
            </form>
        </VoucherModalShell>
    );
}
