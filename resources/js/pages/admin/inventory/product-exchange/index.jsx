import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftRight, Plus, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { paymentModeToAccountId, paymentModeToType } from '@/components/inventory/inventory-form';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

const PAYMENT_STATUS_LABELS = {
    unpaid: 'Unpaid',
    partially_paid: 'Partially Paid',
    fully_paid: 'Fully Paid',
    settled: 'Settled',
};

export default function ProductExchangeIndex({ exchanges = { data: [] }, filters = {}, paymentAccounts = [] }) {
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
        () => router.get(route('inventory.product-exchange.index'), { search: search || undefined }, { preserveState: true, replace: true }),
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting?.id) {
            return;
        }

        router.delete(route('inventory.product-exchange.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    function hasOutstandingSettlement(row) {
        return parseFloat(row.settlement_amount ?? 0) > 0.009 && parseFloat(row.due_amount ?? 0) > 0.009;
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (exchanges.from ?? 0) + i },
        { id: 'invoice', header: 'Invoice', render: (row) => <span className="font-mono text-xs font-semibold text-primary">{row.invoice_number ?? `INVX${String(row.id).padStart(8, '0')}`}</span> },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        { id: 'customer', header: 'Customer', render: (row) => row.customer?.name ?? '—' },
        {
            id: 'settlement',
            header: 'Settlement',
            render: (row) => `৳${parseFloat(row.settlement_amount ?? 0).toFixed(2)}`,
        },
        {
            id: 'paid',
            header: 'Paid',
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
                    {can('inventory.product-exchange.update') && hasOutstandingSettlement(row) && (
                        <Button
                            size="sm"
                            variant="outline"
                            title={row.is_refund ? 'Record refund' : 'Record payment'}
                            onClick={() => setSettling(row)}
                        >
                            <Wallet className="size-3.5" />
                        </Button>
                    )}
                    <AdminRowActions
                        prefix="inventory.product-exchange"
                        id={row.id}
                        showRoute="inventory.product-exchange.show"
                        editRoute="inventory.product-exchange.edit"
                        canEdit={row.can_access_edit !== false}
                        onDelete={() => setDeleting(row)}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Product Exchange" />
            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15"><ArrowLeftRight className="size-4 text-white" /></div>
                        <div><h1 className="text-base font-semibold text-white">Product Exchange</h1></div>
                    </div>
                    <AdminCreateLink
                        permission="inventory.product-exchange.create"
                        href={route('inventory.product-exchange.create')}
                        label="New Exchange"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white hover:bg-white/20"
                    />
                </div>
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="mb-4 max-w-xs" />
                <DataTable columns={columns} rows={exchanges.data} rowKey="id" emptyMessage="No exchanges." />
                {can('inventory.product-exchange.delete') && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader><DialogTitle>Delete exchange?</DialogTitle><DialogDescription>This will reverse all stock movements.</DialogDescription></DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild><Button type="button" variant="outline" size="sm">Cancel</Button></DialogClose>
                            <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>Delete</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                )}
                {can('inventory.product-exchange.update') && (
                    <SettlePaymentDialog
                        exchange={settling}
                        paymentAccounts={paymentAccounts}
                        onClose={() => setSettling(null)}
                    />
                )}
            </div>
        </>
    );
}

function SettlePaymentDialog({ exchange, paymentAccounts, onClose }) {
    const toast = useAppToast();
    const isRefund = Boolean(exchange?.is_refund);
    const settlement = parseFloat(exchange?.settlement_amount ?? 0);
    const alreadyPaid = parseFloat(exchange?.paid_amount ?? 0);
    const remainingDue = parseFloat(exchange?.due_amount ?? 0);

    const form = useForm({
        payment_type: '0',
        payment_account_id: null,
        paid_amount: '0',
    });

    const [paymentMode, setPaymentMode] = useState('party');

    useEffect(() => {
        if (!exchange) {
            return;
        }

        const mode = paymentAccounts.length > 0 ? `cash-${paymentAccounts[0].id}` : 'party';
        setPaymentMode(mode);
        form.clearErrors();
        form.setData({
            payment_type: paymentModeToType(mode),
            payment_account_id: paymentModeToAccountId(mode),
            paid_amount: mode === 'party' ? '0' : remainingDue > 0 ? remainingDue.toFixed(2) : '0',
        });
    }, [exchange?.id]);

    function handleModeChange(mode) {
        setPaymentMode(mode);
        form.setData({
            ...form.data,
            payment_type: paymentModeToType(mode),
            payment_account_id: paymentModeToAccountId(mode),
            paid_amount: mode === 'party' ? '0' : remainingDue > 0 ? remainingDue.toFixed(2) : '0',
        });
    }

    function handleSubmit(e) {
        e.preventDefault();

        if (!exchange) {
            return;
        }

        form.transform((data) => ({
            payment_type: paymentModeToType(paymentMode),
            payment_account_id: paymentModeToAccountId(paymentMode),
            paid_amount: paymentMode === 'party' ? '0' : data.paid_amount,
        }));

        form.put(route('inventory.product-exchange.payment', exchange.id), {
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

    const isParty = paymentMode === 'party';
    const settlementLabel = isRefund ? 'Refund to Customer' : 'Customer Pays';
    const amountLabel = isRefund ? 'Refund Amount' : 'Amount Received';

    return (
        <Dialog open={!!exchange} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{isRefund ? 'Record Refund' : 'Record Payment'}</DialogTitle>
                    <DialogDescription>
                        {exchange?.invoice_number ?? (exchange ? `INVX${String(exchange.id).padStart(8, '0')}` : '')}
                        {exchange?.customer?.name ? ` · ${exchange.customer.name}` : ''}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3">
                    <div className="rounded-md border border-border bg-muted/30 p-3 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">{settlementLabel}</span>
                            <span className={isRefund ? 'font-semibold text-destructive' : 'font-semibold text-primary'}>
                                ৳{settlement.toFixed(2)}
                            </span>
                        </div>
                        {alreadyPaid > 0.009 && (
                            <div className="mt-1 flex items-center justify-between text-xs">
                                <span className="text-muted-foreground">Already recorded</span>
                                <span className="text-green-700 dark:text-green-400">৳{alreadyPaid.toFixed(2)}</span>
                            </div>
                        )}
                        {remainingDue > 0.009 && (
                            <div className="mt-1 flex items-center justify-between text-xs">
                                <span className="text-muted-foreground">Remaining due</span>
                                <span className="font-semibold text-destructive">৳{remainingDue.toFixed(2)}</span>
                            </div>
                        )}
                    </div>

                    <div>
                        <Label className="mb-1 block text-xs text-muted-foreground">Payment Option</Label>
                        <select
                            className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs shadow-xs outline-none focus:border-primary focus:ring-[3px] focus:ring-ring/50"
                            value={paymentMode}
                            onChange={(e) => handleModeChange(e.target.value)}
                        >
                            <option value="party">Customer Account</option>
                            {paymentAccounts.map((acc) => (
                                <option key={acc.id} value={`cash-${acc.id}`}>
                                    {acc.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-[10px] text-muted-foreground">
                            {isParty
                                ? 'Settles on the customer account. No cash or bank entry is posted.'
                                : isRefund
                                  ? 'Cash / bank account the refund is paid from.'
                                  : 'Cash / bank account the payment is received into.'}
                        </p>
                    </div>

                    {!isParty && (
                        <div>
                            <Label className="mb-1 block text-xs text-muted-foreground">{amountLabel}</Label>
                            <Input
                                type="number"
                                min="0"
                                max={remainingDue > 0 ? remainingDue : settlement}
                                step="0.01"
                                value={form.data.paid_amount}
                                onChange={(e) => form.setData('paid_amount', e.target.value)}
                            />
                            {form.errors.paid_amount && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.paid_amount}</p>
                            )}
                            {form.errors.payment_account_id && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.payment_account_id}</p>
                            )}
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline" size="sm">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" size="sm" disabled={form.processing}>
                            {isRefund ? 'Record Refund' : 'Record Payment'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
