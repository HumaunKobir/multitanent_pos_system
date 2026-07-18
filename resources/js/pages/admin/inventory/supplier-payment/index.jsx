import { formatBdDate } from '@/lib/format-bd-date';
import { AllocationSummaryTable } from '@/components/inventory/document-payment-breakdown';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Banknote, Edit, Eye, Plus, Search, Trash2, Wallet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { ListDateExportBar } from '@/components/admin/list-date-export-bar';
import { AdminCreateButton } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import { useFlashToast } from '@/hooks/use-flash-toast';
import {
    PaymentAllocationTable,
    allocationAmountsFromApiDocuments,
    allocationTotal,
    buildAllocations,
} from '@/components/party/payment-allocation-table';

function supplierLabel(supplier) {
    if (!supplier) {
        return '—';
    }

    const company = supplier.company_name?.trim();
    const person = supplier.name?.trim();

    if (company && person) {
        return `${company} (${person})`;
    }

    return company || person || '—';
}

function supplierDueAmount(supplier) {
    if (!supplier) {
        return 0;
    }

    return parseFloat(supplier.total_due ?? supplier.balance ?? 0) || 0;
}

function PaymentSummary({ payment }) {
    if (!payment) {
        return null;
    }

    return (
        <div className="space-y-4 px-5 pb-5 pt-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p className="text-muted-foreground">Voucher</p>
                    <p className="font-mono font-medium">{payment.invoice_number}</p>
                </div>
                <div>
                    <p className="text-muted-foreground">Date</p>
                    <p>{formatBdDate(payment.date)}</p>
                </div>
                <div>
                    <p className="text-muted-foreground">Supplier</p>
                    <p className="font-medium">{supplierLabel(payment.supplier)}</p>
                </div>
                <div>
                    <p className="text-muted-foreground">Payment Account</p>
                    <p>{payment.payment_account_label ?? '—'}</p>
                </div>
                <div>
                    <p className="text-muted-foreground">Total Amount</p>
                    <p className="font-medium text-emerald-700">৳{parseFloat(payment.amount ?? 0).toFixed(2)}</p>
                </div>
                <div>
                    <p className="text-muted-foreground">Recorded By</p>
                    <p>{payment.created_by?.name ?? '—'}</p>
                </div>
            </div>

            <AllocationSummaryTable
                title="Allocated Purchases"
                documentLabel="Purchase"
                rows={payment.allocations ?? []}
            />

            <div>
                <p className="text-sm font-medium">Note</p>
                <p className="mt-1 text-sm text-muted-foreground">{payment.comment || '—'}</p>
            </div>
        </div>
    );
}

function PaymentForm({ form, suppliers, paymentAccounts = [], payment = null, onSuccess, onCancel, submitLabel }) {
    const [duePurchases, setDuePurchases] = useState([]);
    const [loadingPurchases, setLoadingPurchases] = useState(false);
    const [amountsById, setAmountsById] = useState({});
    const [liveTotalDue, setLiveTotalDue] = useState(null);
    const isEditing = payment?.id != null;

    const selected = useMemo(
        () => suppliers.find((s) => String(s.id) === String(form.data.supplier_id)),
        [suppliers, form.data.supplier_id],
    );

    const allocationSum = useMemo(() => allocationTotal(amountsById), [amountsById]);
    const hasDueDocuments = duePurchases.length > 0;
    const usesManualAmount = !hasDueDocuments;

    const selectedDue = liveTotalDue ?? supplierDueAmount(selected);

    useEffect(() => {
        if (!isEditing || !payment?.allocations?.length) {
            return;
        }

        setAmountsById(
            Object.fromEntries(
                payment.allocations.map((allocation) => [allocation.purchase_id, String(allocation.amount)]),
            ),
        );
    }, [payment?.id, isEditing, payment?.allocations]);

    useEffect(() => {
        if (!form.data.supplier_id) {
            setDuePurchases([]);
            setLiveTotalDue(null);
            if (!isEditing) {
                setAmountsById({});
                form.setData('amount', '');
            }
            return;
        }

        let cancelled = false;
        setLoadingPurchases(true);

        const paymentId = payment?.id ? `?current_payment_id=${payment.id}` : '';

        fetch(`${route('api.suppliers.due-purchases', form.data.supplier_id)}${paymentId}`, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : Promise.reject()))
            .then((data) => {
                if (!cancelled) {
                    const purchases = data.purchases ?? [];
                    setDuePurchases(purchases);
                    setLiveTotalDue(data.total_due != null ? parseFloat(data.total_due) : null);

                    if (purchases.length > 0) {
                        if (isEditing) {
                            setAmountsById(allocationAmountsFromApiDocuments(purchases));
                        }
                    } else if (!isEditing) {
                        setAmountsById({});
                    }
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setDuePurchases([]);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoadingPurchases(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [form.data.supplier_id, payment?.id, isEditing]);

    function handleAmountChange(purchaseId, value) {
        setAmountsById((prev) => ({ ...prev, [purchaseId]: value }));
    }

    function handlePayFull(purchaseId, dueAmount) {
        setAmountsById((prev) => ({ ...prev, [purchaseId]: String(dueAmount) }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        const allocations = buildAllocations('purchase_id', duePurchases, amountsById);

        form.transform((data) => ({
            ...data,
            allocations,
            amount: allocations.length > 0 ? null : data.amount,
        }));
        const options = {
            preserveState: true,
            onSuccess: (page) => {
                if (page.props.flash?.success) {
                    onSuccess?.();
                }
            },
        };

        if (isEditing) {
            form.put(route('party.supplier-payment.update', payment.id), options);
            return;
        }

        form.post(route('party.supplier-payment.store'), options);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-1.5 px-3 py-2">
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
                                {supplierLabel(supplier)} — ৳{supplierDueAmount(supplier).toFixed(2)} due
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FormField>

            {selected && (
                <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Current due: <strong>৳{selectedDue.toFixed(2)}</strong>
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
                <FormField label="Total Amount" required={usesManualAmount} name="amount" error={form.errors.amount}>
                    <Input
                        id="amount"
                        type="number"
                        min="0"
                        step="0.01"
                        readOnly={!usesManualAmount}
                        value={
                            usesManualAmount
                                ? form.data.amount
                                : allocationSum > 0
                                  ? allocationSum.toFixed(2)
                                  : ''
                        }
                        onChange={(e) => form.setData('amount', e.target.value)}
                        placeholder="0"
                        className={`mt-1 ${usesManualAmount ? '' : 'bg-muted'}`}
                        aria-invalid={!!form.errors.amount}
                    />
                </FormField>
            </div>

            {hasDueDocuments ? (
                <FormField label="Allocate to Dues" error={form.errors.allocations}>
                    <PaymentAllocationTable
                        documents={duePurchases}
                        idField="purchase_id"
                        amountsById={amountsById}
                        onAmountChange={handleAmountChange}
                        onPayFull={handlePayFull}
                        loading={loadingPurchases}
                        emptyMessage="No due items found for this supplier."
                    />
                </FormField>
            ) : (
                <p className="rounded-md border border-dashed px-3 py-3 text-xs text-muted-foreground">
                    {loadingPurchases
                        ? 'Loading supplier dues…'
                        : form.data.supplier_id
                          ? 'No itemized dues found. Enter the payment amount above to reduce the supplier balance directly.'
                          : 'Select a supplier first.'}
                </p>
            )}

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
                    {form.processing ? 'Saving…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}

export default function SupplierPaymentIndex({ payments, suppliers, filters, today, paymentAccounts = [] }) {
    useFlashToast();
    const { flash } = usePage().props;
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [creating, setCreating] = useState(false);
    const [viewing, setViewing] = useState(null);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash?.warning) {
            setCreating(true);
        }
    }, [flash?.warning]);

    const createForm = useForm({
        supplier_id: '',
        date: today,
        payment_account_id: '',
        comment: '',
        amount: '',
        allocations: [],
    });
    const editForm = useForm({
        supplier_id: '',
        date: today,
        payment_account_id: '',
        comment: '',
        amount: '',
        allocations: [],
    });

    useEffect(() => {
        if (!editing) {
            return;
        }

        editForm.setData({
            supplier_id: String(editing.supplier_id ?? ''),
            date: editing.date ?? today,
            payment_account_id: editing.payment_account_id ? String(editing.payment_account_id) : '',
            comment: editing.comment ?? '',
            amount: editing.allocations?.length ? '' : String(editing.amount ?? ''),
            allocations: [],
        });
        editForm.clearErrors();
    }, [editing, today]);

    useDebouncedEffect(
        () => {
            router.get(
                route('party.supplier-payment.index'),
                {
                    search: search || undefined,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                },
                { preserveState: true, replace: true },
            );
        },
        [search, dateFrom, dateTo],
        350,
        { skipFirstRun: true },
    );

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
                    <p className="font-medium">{supplierLabel(row.supplier)}</p>
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
            render: (row) => (
                <div className="flex justify-end gap-1">
                    {can('party.supplier-payment.view') && (
                        <Button type="button" variant="ghost" size="sm" onClick={() => setViewing(row)}>
                            <Eye className="size-4" />
                        </Button>
                    )}
                    {can('party.supplier-payment.update') && (
                        <Button type="button" variant="ghost" size="sm" onClick={() => setEditing(row)}>
                            <Edit className="size-4" />
                        </Button>
                    )}
                    {can('party.supplier-payment.delete') && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-red-600 hover:bg-red-50 hover:text-red-700"
                            onClick={() => setDeleting(row)}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    )}
                </div>
            ),
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

                <div className="mb-4 flex flex-wrap items-end gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search voucher, supplier, note…"
                            className="pl-9"
                        />
                    </div>
                    <ListDateExportBar
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onDateFromChange={setDateFrom}
                        onDateToChange={setDateTo}
                        exportExcelRoute="party.supplier-payment.export-excel"
                        exportPdfRoute="party.supplier-payment.export-pdf"
                        exportPrintRoute="party.supplier-payment.export-print"
                        query={{ search }}
                    />
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
                    <DialogContent className="p-0 sm:max-w-2xl">
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
                            payment={null}
                            onSuccess={() => {
                                setCreating(false);
                                createForm.reset();
                                createForm.setData('date', today);
                            }}
                            onCancel={() => setCreating(false)}
                            submitLabel="Record Payment"
                        />
                    </DialogContent>
                </Dialog>
            </Can>

            {can('party.supplier-payment.view') && (
                <Dialog open={!!viewing} onOpenChange={(open) => !open && setViewing(null)}>
                    <DialogContent className="p-0 sm:max-w-3xl">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Eye className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Payment Details</h2>
                        </div>
                        <PaymentSummary payment={viewing} />
                    </DialogContent>
                </Dialog>
            )}

            {can('party.supplier-payment.update') && (
                <Dialog
                    open={!!editing}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                            editForm.reset();
                            editForm.setData('date', today);
                        }
                    }}
                >
                    <DialogContent className="p-0 sm:max-w-2xl">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Edit className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Edit Supplier Payment</h2>
                        </div>
                        <PaymentForm
                            form={editForm}
                            suppliers={suppliers}
                            paymentAccounts={paymentAccounts}
                            payment={editing}
                            onSuccess={() => {
                                setEditing(null);
                                editForm.reset();
                                editForm.setData('date', today);
                            }}
                            onCancel={() => setEditing(null)}
                            submitLabel="Update Payment"
                        />
                    </DialogContent>
                </Dialog>
            )}

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
                                <strong>{supplierLabel(deleting?.supplier)}</strong>? Supplier due balance will be restored.
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
