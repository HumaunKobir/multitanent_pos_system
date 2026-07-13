import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, RotateCcw, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

const PAYMENT_STATUS_LABELS = {
    unpaid: 'Unpaid',
    partially_refunded: 'Partially Refunded',
    fully_refunded: 'Fully Refunded',
    settled: 'Settled',
};

export default function SaleReturnIndex({ returns = { data: [] }, filters = {}, paymentAccounts = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [settling, setSettling] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => router.get(route('inventory.sale-return.index'), { search: search || undefined }, { preserveState: true, replace: true }),
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting?.id) {
            return;
        }

        router.delete(route('inventory.sale-return.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    function hasOutstandingRefund(row) {
        return parseFloat(row.refund_amount ?? row.net_amount ?? 0) > 0.009 && parseFloat(row.due_amount ?? 0) > 0.009;
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (returns.from ?? 0) + i },
        { id: 'invoice', header: 'Invoice', render: (row) => <span className="font-mono text-xs font-semibold text-primary">{row.invoice_number ?? `INVSR${String(row.id).padStart(8, '0')}`}</span> },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        { id: 'customer', header: 'Customer', render: (row) => row.customer?.name ?? '—' },
        {
            id: 'total',
            header: 'Refund',
            render: (row) => `৳${parseFloat(row.refund_amount ?? row.net_amount ?? row.gross_amount ?? 0).toFixed(2)}`,
        },
        {
            id: 'paid',
            header: 'Refunded',
            render: (row) => (
                <span className="text-green-700 dark:text-green-400">
                    ৳{parseFloat(row.paid_amount ?? 0).toFixed(2)}
                </span>
            ),
        },
        {
            id: 'due',
            header: 'Due',
            render: (row) => (
                <span
                    className={
                        parseFloat(row.due_amount ?? 0) > 0
                            ? 'font-semibold text-destructive'
                            : 'font-semibold text-green-700 dark:text-green-400'
                    }
                >
                    ৳{parseFloat(row.due_amount ?? 0).toFixed(2)}
                </span>
            ),
        },
        {
            id: 'payment',
            header: 'Status',
            render: (row) => PAYMENT_STATUS_LABELS[row.payment_status] ?? '—',
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    {can('inventory.sale-return.update') && hasOutstandingRefund(row) && (
                        <Button
                            size="sm"
                            variant="outline"
                            title="Record refund"
                            onClick={() => setSettling(row)}
                        >
                            <Wallet className="size-3.5" />
                        </Button>
                    )}
                    <AdminRowActions
                        prefix="inventory.sale-return"
                        id={row.id}
                        showRoute="inventory.sale-return.show"
                        editRoute="inventory.sale-return.edit"
                        onDelete={() => setDeleting(row)}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Sale Returns" />
            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15"><RotateCcw className="size-4 text-white" /></div>
                        <div><h1 className="text-base font-semibold text-white">Sale Returns</h1></div>
                    </div>
                    <AdminCreateLink
                        permission="inventory.sale-return.create"
                        href={route('inventory.sale-return.create')}
                        label="New Return"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white hover:bg-white/20"
                    />
                </div>
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="mb-4 max-w-xs" />
                <DataTable columns={columns} rows={returns.data} rowKey="id" emptyMessage="No sale returns." />
                {can('inventory.sale-return.delete') && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader><DialogTitle>Delete sale return?</DialogTitle><DialogDescription>This will reverse stock and customer balance.</DialogDescription></DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild><Button type="button" variant="outline" size="sm">Cancel</Button></DialogClose>
                            <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>Delete</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                )}
                {can('inventory.sale-return.update') && (
                    <SettleRefundDialog
                        saleReturn={settling}
                        paymentAccounts={paymentAccounts}
                        onClose={() => setSettling(null)}
                    />
                )}
            </div>
        </>
    );
}

function SettleRefundDialog({ saleReturn, paymentAccounts, onClose }) {
    const toast = useAppToast();
    const refundTotal = parseFloat(saleReturn?.refund_amount ?? saleReturn?.net_amount ?? 0);
    const alreadyRefunded = parseFloat(saleReturn?.paid_amount ?? 0);
    const remainingDue = parseFloat(saleReturn?.due_amount ?? 0);

    const form = useForm({
        date: new Date().toISOString().slice(0, 10),
        payment_account_id: paymentAccounts[0]?.id ?? null,
        amount: '0',
    });

    useEffect(() => {
        if (!saleReturn) {
            return;
        }

        form.clearErrors();
        form.setData({
            date: new Date().toISOString().slice(0, 10),
            payment_account_id: paymentAccounts[0]?.id ?? null,
            amount: remainingDue > 0 ? remainingDue.toFixed(2) : '0',
        });
    }, [saleReturn?.id]);

    function handleSubmit(e) {
        e.preventDefault();

        if (!saleReturn) {
            return;
        }

        form.put(route('inventory.sale-return.refund', saleReturn.id), {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errors) => {
                const first = Object.values(errors)[0];

                if (first) {
                    toast.error(Array.isArray(first) ? first[0] : first);
                }
            },
        });
    }

    return (
        <Dialog open={!!saleReturn} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Record Refund</DialogTitle>
                    <DialogDescription>
                        {saleReturn?.invoice_number ?? (saleReturn ? `INVSR${String(saleReturn.id).padStart(8, '0')}` : '')}
                        {saleReturn?.customer?.name ? ` · ${saleReturn.customer.name}` : ''}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3">
                    <div className="rounded-md border border-border bg-muted/30 p-3 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">Total Refund</span>
                            <span className="font-semibold text-destructive">৳{refundTotal.toFixed(2)}</span>
                        </div>
                        {alreadyRefunded > 0.009 && (
                            <div className="mt-1 flex items-center justify-between text-xs">
                                <span className="text-muted-foreground">Already refunded</span>
                                <span className="text-green-700 dark:text-green-400">৳{alreadyRefunded.toFixed(2)}</span>
                            </div>
                        )}
                        <div className="mt-1 flex items-center justify-between text-xs">
                            <span className="text-muted-foreground">Remaining due</span>
                            <span className="font-semibold text-destructive">৳{remainingDue.toFixed(2)}</span>
                        </div>
                    </div>

                    <div>
                        <Label className="mb-1 block text-xs text-muted-foreground">Refund Date</Label>
                        <Input
                            type="date"
                            value={form.data.date}
                            onChange={(e) => form.setData('date', e.target.value)}
                        />
                        {form.errors.date && <p className="mt-1 text-xs text-destructive">{form.errors.date}</p>}
                    </div>

                    <div>
                        <Label className="mb-1 block text-xs text-muted-foreground">Cash / Bank Account</Label>
                        <select
                            className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs shadow-xs outline-none focus:border-primary focus:ring-[3px] focus:ring-ring/50"
                            value={form.data.payment_account_id ?? ''}
                            onChange={(e) => form.setData('payment_account_id', e.target.value ? Number(e.target.value) : null)}
                        >
                            {paymentAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.label}
                                </option>
                            ))}
                        </select>
                        {form.errors.payment_account_id && (
                            <p className="mt-1 text-xs text-destructive">{form.errors.payment_account_id}</p>
                        )}
                    </div>

                    <div>
                        <Label className="mb-1 block text-xs text-muted-foreground">Refund Amount</Label>
                        <Input
                            type="number"
                            min="0.01"
                            max={remainingDue}
                            step="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                        />
                        {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline" size="sm">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" size="sm" disabled={form.processing}>
                            Record Refund
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
