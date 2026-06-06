import { RequiredMark } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { route } from '@/lib/route';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { FlatAccountSelect } from './grouped-account-select';
import { VoucherFormFooter } from './voucher-form-footer';
import { VoucherModalShell } from './voucher-modal-shell';

const TYPE_CONTRA = 4;

export default function ContraVoucherModal({ open, onOpenChange, item, assetAccounts, defaults }) {
    const isEditing = !!item?.id;

    const form = useForm({
        type: TYPE_CONTRA,
        voucher_no: defaults?.voucher_no ?? '',
        date: defaults?.date ?? '',
        transaction_reference: defaults?.transaction_reference ?? '',
        from_account_id: '',
        to_account_id: '',
        total_amount: '',
        narration: '',
        debit_description: '',
        credit_description: '',
    });

    useEffect(() => {
        if (!open) return;
        if (item) {
            form.setData({
                type: TYPE_CONTRA,
                voucher_no: item.voucher_no ?? '',
                date: item.date ?? '',
                transaction_reference: item.transaction_reference ?? '',
                from_account_id: String(item.from_account_id ?? ''),
                to_account_id: String(item.to_account_id ?? ''),
                total_amount: String(item.total_amount ?? ''),
                narration: item.narration ?? '',
                debit_description: '',
                credit_description: '',
            });
        } else {
            form.setData({
                type: TYPE_CONTRA,
                voucher_no: defaults?.voucher_no ?? '',
                date: defaults?.date ?? '',
                transaction_reference: defaults?.transaction_reference ?? '',
                from_account_id: '',
                to_account_id: '',
                total_amount: '',
                narration: '',
                debit_description: '',
                credit_description: '',
            });
        }
        form.clearErrors();
    }, [open, item]);

    function submit(e) {
        e.preventDefault();
        const payload = {
            ...form.data,
            from_account_id: Number(form.data.from_account_id),
            to_account_id: Number(form.data.to_account_id),
            total_amount: parseFloat(form.data.total_amount),
            debit_description: form.data.debit_description || form.data.narration,
            credit_description: form.data.credit_description || form.data.narration,
        };
        if (isEditing) {
            form.transform(() => payload).put(route('accounts.vouchers.update', item.id), { onSuccess: () => onOpenChange(false) });
        } else {
            form.transform(() => payload).post(route('accounts.vouchers.store'), { onSuccess: () => onOpenChange(false) });
        }
    }

    return (
        <VoucherModalShell
            open={open}
            onOpenChange={onOpenChange}
            title={isEditing ? 'Edit Contra Voucher' : 'Create Contra Voucher'}
            subtitle="Create a new bank/cash transfer record."
            footer={<VoucherFormFooter onCancel={() => onOpenChange(false)} processing={form.processing} submitLabel={isEditing ? 'Update Voucher' : 'Create Voucher'} />}
        >
            <form id="voucher-form" onSubmit={submit} className="space-y-4 px-5 py-4">
                {form.errors.general && <p className="text-sm text-destructive">{form.errors.general}</p>}
                <div className="grid gap-4 sm:grid-cols-2">
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
                        <Label>
                            From Account
                            <RequiredMark />
                        </Label>
                        <div className="mt-1">
                            <FlatAccountSelect accounts={assetAccounts} value={form.data.from_account_id} onChange={(v) => form.setData('from_account_id', v)} placeholder="Select Account..." />
                        </div>
                    </div>
                    <div>
                        <Label>
                            To Account
                            <RequiredMark />
                        </Label>
                        <div className="mt-1">
                            <FlatAccountSelect accounts={assetAccounts} value={form.data.to_account_id} onChange={(v) => form.setData('to_account_id', v)} placeholder="Select Account..." />
                        </div>
                    </div>
                    <div>
                        <Label>
                            Transfer Amount
                            <RequiredMark />
                        </Label>
                        <Input type="number" min="0" step="0.01" className="mt-1" placeholder="0.00" value={form.data.total_amount} onChange={(e) => form.setData('total_amount', e.target.value)} />
                    </div>
                    <div>
                        <Label>Transaction Reference</Label>
                        <Input className="mt-1" value={form.data.transaction_reference} onChange={(e) => form.setData('transaction_reference', e.target.value)} />
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label>Debit Description</Label>
                        <Input className="mt-1" value={form.data.debit_description} onChange={(e) => form.setData('debit_description', e.target.value)} placeholder="To account narration" />
                    </div>
                    <div>
                        <Label>Credit Description</Label>
                        <Input className="mt-1" value={form.data.credit_description} onChange={(e) => form.setData('credit_description', e.target.value)} placeholder="From account narration" />
                    </div>
                </div>
                <div>
                    <Label>Narration</Label>
                    <Textarea className="mt-1" placeholder="Add any narration or notes..." value={form.data.narration} onChange={(e) => form.setData('narration', e.target.value)} />
                </div>
            </form>
        </VoucherModalShell>
    );
}
