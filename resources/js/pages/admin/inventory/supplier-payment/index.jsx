import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Banknote, Plus, Search, Trash2, Wallet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { AdminCreateButton } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

function FormField({ label, required, name, error, children }) {
    return (
        <div>
            <Label htmlFor={name}>
                {label}
                {required && <span className="ml-0.5 text-red-500">*</span>}
            </Label>
            {children}
            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
        </div>
    );
}

function PaymentForm({ form, suppliers, paymentAccounts = [], onSubmit, onCancel }) {
    const selected = useMemo(
        () => suppliers.find((s) => String(s.id) === String(form.data.supplier_id)),
        [suppliers, form.data.supplier_id],
    );

    return (
        <form onSubmit={onSubmit} className="space-y-1.5 px-3 py-2">
            <FormField label="Supplier" required name="supplier_id" error={form.errors.supplier_id}>
                <Select
                    value={form.data.supplier_id ? String(form.data.supplier_id) : undefined}
                    onValueChange={(value) => form.setData('supplier_id', value)}
                >
                    <SelectTrigger id="supplier_id" className="mt-1 w-full" aria-invalid={!!form.errors.supplier_id}>
                        <SelectValue placeholder="Select supplier" />
                    </SelectTrigger>
                    <SelectContent>
                        {suppliers.map((supplier) => (
                            <SelectItem key={supplier.id} value={String(supplier.id)}>
                                {supplier.name} — ৳{parseFloat(supplier.balance).toFixed(2)} due
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FormField>

            {selected && (
                <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Current due: <strong>৳{parseFloat(selected.balance).toFixed(2)}</strong>
                </p>
            )}

            <div className="grid grid-cols-2 gap-3">
                <FormField label="Date" required name="date" error={form.errors.date}>
                    <Input
                        id="date"
                        type="date"
                        value={form.data.date}
                        onChange={(e) => form.setData('date', e.target.value)}
                        className="mt-1"
                        aria-invalid={!!form.errors.date}
                    />
                </FormField>
                <FormField label="Amount" required name="amount" error={form.errors.amount}>
                    <Input
                        id="amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={form.data.amount}
                        onChange={(e) => form.setData('amount', e.target.value)}
                        placeholder="0.00"
                        className="mt-1"
                        aria-invalid={!!form.errors.amount}
                    />
                </FormField>
            </div>

            <FormField label="Note" name="comment" error={form.errors.comment}>
                <Input
                    id="comment"
                    value={form.data.comment}
                    onChange={(e) => form.setData('comment', e.target.value)}
                    placeholder="Optional note"
                    className="mt-1"
                />
            </FormField>

            <FormField label="Payment Account" required name="payment_account_id" error={form.errors.payment_account_id}>
                <Select
                    value={form.data.payment_account_id ? String(form.data.payment_account_id) : undefined}
                    onValueChange={(value) => form.setData('payment_account_id', value)}
                >
                    <SelectTrigger id="payment_account_id" className="mt-1 w-full" aria-invalid={!!form.errors.payment_account_id}>
                        <SelectValue placeholder="Select cash / bank account" />
                    </SelectTrigger>
                    <SelectContent>
                        {paymentAccounts.map((account) => (
                            <SelectItem key={account.id} value={String(account.id)}>
                                {account.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FormField>

            <div className="flex justify-end gap-3 border-t pt-4">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing}
                    className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50"
                >
                    {form.processing ? 'Saving…' : 'Record Payment'}
                </Button>
            </div>
        </form>
    );
}

export default function SupplierPaymentIndex({ payments, suppliers, filters, today, paymentAccounts = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState(null);

    const createForm = useForm({
        supplier_id: '',
        date: today,
        amount: '',
        payment_account_id: '',
        comment: '',
    });

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                route('party.supplier-payment.index'),
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleCreate(e) {
        e.preventDefault();
        createForm.post(route('party.supplier-payment.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
                createForm.setData('date', today);
            },
        });
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('party.supplier-payment.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (payments.from ?? 0) + i },
        {
            id: 'invoice',
            header: 'Voucher',
            render: (row) => (
                <span className="font-mono text-xs font-semibold text-primary">{row.invoice_number}</span>
            ),
        },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        {
            id: 'supplier',
            header: 'Supplier',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.supplier?.name ?? '—'}</p>
                    <p className="text-xs text-muted-foreground">{row.supplier?.phone ?? ''}</p>
                </div>
            ),
        },
        {
            id: 'amount',
            header: 'Amount',
            render: (row) => <span className="font-medium text-emerald-700">৳{parseFloat(row.amount).toFixed(2)}</span>,
        },
        { id: 'comment', header: 'Note', render: (row) => row.comment ?? '—' },
        {
            id: 'created_by',
            header: 'Recorded By',
            render: (row) => row.created_by?.name ?? '—',
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) =>
                can('party.supplier-payment.delete') ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-red-600 hover:bg-red-50 hover:text-red-700"
                        onClick={() => setDeleting(row)}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Supplier Payments" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Wallet className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Supplier Payments</h1>
                            <p className="text-xs text-white/60">Pay supplier due balances.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="party.supplier-payment.create"
                        onClick={() => setCreating(true)}
                        label="New Payment"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search voucher, supplier, note…"
                            className="pl-9"
                        />
                    </div>
                </div>

                <DataTable columns={columns} rows={payments.data} rowKey="id" emptyMessage="No supplier payments found." />

                {payments.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {payments.links.map((link, i) => (
                            <a
                                key={i}
                                href={link.url ?? '#'}
                                className={[
                                    'border px-3 py-1 text-sm transition-colors',
                                    link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Can permission="party.supplier-payment.create">
                <Dialog
                    open={creating}
                    onOpenChange={(open) => {
                        if (!open) {
                            setCreating(false);
                            createForm.reset();
                            createForm.setData('date', today);
                        }
                    }}
                >
                    <DialogContent className="p-0 sm:max-w-lg">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Banknote className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Record Supplier Payment</h2>
                        </div>
                        <PaymentForm
                            form={createForm}
                            suppliers={suppliers}
                            paymentAccounts={paymentAccounts}
                            onSubmit={handleCreate}
                            onCancel={() => setCreating(false)}
                        />
                    </DialogContent>
                </Dialog>
            </Can>

            {can('party.supplier-payment.delete') && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="p-0">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Trash2 className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Delete Payment</h2>
                        </div>
                        <div className="px-5 pb-5 pt-4">
                            <p className="text-sm text-muted-foreground">
                                Delete payment <strong>{deleting?.invoice_number}</strong> for{' '}
                                <strong>{deleting?.supplier?.name}</strong>? Supplier due balance will be restored.
                            </p>
                            <div className="mt-4 flex justify-end gap-3 border-t pt-4">
                                <DialogClose asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    size="sm"
                                    className="bg-red-600 text-white shadow-sm shadow-red-500/30 transition-all duration-150 hover:bg-red-700 hover:-translate-y-0.5 hover:shadow-md hover:shadow-red-500/50"
                                    onClick={handleDelete}
                                >
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
