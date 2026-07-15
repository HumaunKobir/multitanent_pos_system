import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Bell, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';

function AlertForm({ form, customers, dueSales, statuses, onSubmit, onCancel, isEditing }) {
    const selected = useMemo(
        () => customers.find((c) => String(c.id) === String(form.data.customer_id)),
        [customers, form.data.customer_id],
    );

    const customerSales = useMemo(
        () => dueSales.filter((sale) => String(sale.customer_id) === String(form.data.customer_id)),
        [dueSales, form.data.customer_id],
    );

    return (
        <form onSubmit={onSubmit} className="space-y-1.5 px-3 py-2">
            <FormField label="Customer" required name="customer_id" error={form.errors.customer_id}>
                <Select
                    value={form.data.customer_id ? String(form.data.customer_id) : undefined}
                    onValueChange={(value) => {
                        form.setData((data) => ({
                            ...data,
                            customer_id: value,
                            sell_id: '',
                        }));
                    }}
                >
                    <SelectTrigger id="customer_id" className="mt-1 w-full" aria-invalid={!!form.errors.customer_id}>
                        <SelectValue placeholder="Select customer" />
                    </SelectTrigger>
                    <SelectContent>
                        {customers.map((customer) => (
                            <SelectItem key={customer.id} value={String(customer.id)}>
                                {customer.name} — ৳{parseFloat(customer.balance).toFixed(2)} due
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

            <FormField label="Sale Invoice" name="sell_id" error={form.errors.sell_id}>
                <Select
                    value={form.data.sell_id ? String(form.data.sell_id) : undefined}
                    onValueChange={(value) => form.setData('sell_id', value === 'none' ? '' : value)}
                    disabled={!form.data.customer_id}
                >
                    <SelectTrigger id="sell_id" className="mt-1 w-full" aria-invalid={!!form.errors.sell_id}>
                        <SelectValue placeholder={form.data.customer_id ? 'Select sale invoice (optional)' : 'Select a customer first'} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No invoice</SelectItem>
                        {customerSales.map((sale) => (
                            <SelectItem key={sale.id} value={String(sale.id)}>
                                {sale.invoice_number} — ৳{parseFloat(sale.due_amount).toFixed(2)} due
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FormField>

            <FormField label="Due Given Date" required name="due_given_date" error={form.errors.due_given_date}>
                <Input
                    id="due_given_date"
                    type="date"
                    value={form.data.due_given_date}
                    onChange={(e) => form.setData('due_given_date', e.target.value)}
                    className="mt-1"
                    aria-invalid={!!form.errors.due_given_date}
                />
            </FormField>

            <FormField label="Status" required name="status" error={form.errors.status}>
                <Select
                    value={String(form.data.status)}
                    onValueChange={(v) => form.setData('status', parseInt(v))}
                >
                    <SelectTrigger id="status" className="mt-1 w-full" aria-invalid={!!form.errors.status}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {statuses.filter((s) => s.value !== 0).map((s) => (
                            <SelectItem key={s.value} value={String(s.value)}>
                                {s.name}
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
                    {form.processing ? 'Saving…' : isEditing ? 'Update' : 'Create'}
                </Button>
            </div>
        </form>
    );
}

export default function CustomerDueAlertIndex({ alerts, customers, dueSales = [], filters, today, statuses }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const blank = { customer_id: '', sell_id: '', due_given_date: today, status: 1 };
    const createForm = useForm(blank);
    const editForm = useForm(blank);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                route('party.customer-due-alert.index'),
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function openEdit(row) {
        editForm.setData({
            customer_id: String(row.customer_id),
            sell_id: row.sell_id ? String(row.sell_id) : '',
            due_given_date: row.due_given_date?.slice(0, 10) ?? today,
            status: row.status,
        });
        setEditing(row);
    }

    function handleCreate(e) {
        e.preventDefault();
        createForm.post(route('party.customer-due-alert.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
                createForm.setData({ due_given_date: today, sell_id: '' });
            },
        });
    }

    function handleUpdate(e) {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('party.customer-due-alert.update', editing.id), {
            onSuccess: () => {
                setEditing(null);
                editForm.reset();
                editForm.setData({ due_given_date: today, sell_id: '' });
            },
        });
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('party.customer-due-alert.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    const statusBadge = (status) => {
        if (status === 2) {
            return <Badge className="bg-green-600 text-white hover:bg-green-700">Paid</Badge>;
        }
        if (status === 3) {
            return <Badge className="bg-blue-600 text-white hover:bg-blue-700">Date Changed</Badge>;
        }
        return <Badge className="bg-yellow-500 text-white hover:bg-yellow-600">Unpaid</Badge>;
    };

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (alerts.from ?? 0) + i },
        {
            id: 'invoice',
            header: 'Invoice',
            render: (row) =>
                row.invoice_number ? (
                    <span className="font-mono text-xs font-semibold text-primary">{row.invoice_number}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            id: 'customer',
            header: 'Customer',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.customer?.name ?? '—'}</p>
                    <p className="text-xs text-muted-foreground">{row.customer?.phone ?? ''}</p>
                </div>
            ),
        },
        { id: 'due_given_date', header: 'Due Given Date', render: (row) => formatBdDate(row.due_given_date) },
        { id: 'status', header: 'Status', render: (row) => statusBadge(row.status) },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <AdminInlineActions
                    prefix="party.customer-due-alert"
                    onEdit={() => openEdit(row)}
                    onDelete={() => setDeleting(row)}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Customer Due Alerts" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Bell className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Customer Due Alerts</h1>
                            <p className="text-xs text-white/60">Track customer due payment schedules.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="party.customer-due-alert.create"
                        onClick={() => setCreating(true)}
                        label="New Alert"
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
                            placeholder="Search by invoice, name, or phone…"
                            className="pl-9"
                        />
                    </div>
                </div>

                <DataTable columns={columns} rows={alerts.data} rowKey="id" emptyMessage="No due alerts found." />

                {alerts.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {alerts.links.map((link, i) => (
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

            <Can permission="party.customer-due-alert.create">
                <Dialog
                    open={creating}
                    onOpenChange={(open) => {
                        if (!open) {
                            setCreating(false);
                            createForm.reset();
                            createForm.setData({ due_given_date: today, sell_id: '' });
                        }
                    }}
                >
                    <DialogContent className="p-0 sm:max-w-lg">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Plus className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">New Due Alert</h2>
                        </div>
                        <AlertForm
                            form={createForm}
                            customers={customers}
                            dueSales={dueSales}
                            statuses={statuses}
                            onSubmit={handleCreate}
                            onCancel={() => setCreating(false)}
                            isEditing={false}
                        />
                    </DialogContent>
                </Dialog>
            </Can>

            <Can permission="party.customer-due-alert.update">
                <Dialog open={!!editing} onOpenChange={(open) => !open && setEditing(null)}>
                    <DialogContent className="p-0 sm:max-w-lg">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Pencil className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Edit Due Alert</h2>
                        </div>
                        <AlertForm
                            form={editForm}
                            customers={customers}
                            dueSales={dueSales}
                            statuses={statuses}
                            onSubmit={handleUpdate}
                            onCancel={() => setEditing(null)}
                            isEditing={true}
                        />
                    </DialogContent>
                </Dialog>
            </Can>

            {can('party.customer-due-alert.delete') && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="p-0">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Trash2 className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Delete Due Alert</h2>
                        </div>
                        <div className="px-5 pb-5 pt-4">
                            <p className="text-sm text-muted-foreground">
                                Are you sure you want to delete the due alert for{' '}
                                <strong>{deleting?.customer?.name}</strong>
                                {deleting?.invoice_number ? (
                                    <>
                                        {' '}
                                        (<span className="font-mono">{deleting.invoice_number}</span>)
                                    </>
                                ) : null}
                                ?
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
