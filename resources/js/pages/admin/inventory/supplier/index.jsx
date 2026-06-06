import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

function SupplierForm({ form, onSubmit, onCancel, isEditing }) {
    return (
        <form onSubmit={onSubmit} className="space-y-1.5 px-3 py-2">
            <div className="grid grid-cols-2 gap-3">
                <FormField label="Name" required name="name" error={form.errors.name}>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Supplier name"
                        className="mt-1"
                        aria-invalid={!!form.errors.name}
                    />
                </FormField>
                <FormField label="Phone" required name="phone" error={form.errors.phone}>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        placeholder="01XXXXXXXXX"
                        className="mt-1"
                        aria-invalid={!!form.errors.phone}
                    />
                </FormField>
            </div>
            <FormField label="Company Name" name="company_name" error={form.errors.company_name}>
                <Input
                    id="company_name"
                    value={form.data.company_name}
                    onChange={(e) => form.setData('company_name', e.target.value)}
                    placeholder="Company name"
                    className="mt-1"
                />
            </FormField>
            <FormField label="Address" name="address" error={form.errors.address}>
                <Input
                    id="address"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                    placeholder="Address"
                    className="mt-1"
                />
            </FormField>
            {!isEditing && (
                <FormField label="Opening Balance" name="opening_balance" error={form.errors.opening_balance}>
                    <Input
                        id="opening_balance"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.opening_balance}
                        onChange={(e) => form.setData('opening_balance', e.target.value)}
                        placeholder="0.00"
                        className="mt-1"
                    />
                </FormField>
            )}
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

export default function SupplierIndex({ suppliers, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const createForm = useForm({ name: '', phone: '', company_name: '', address: '', opening_balance: '' });
    const editForm = useForm({ name: '', phone: '', company_name: '', address: '' });

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(route('party.supplier.index'), { search: search || undefined }, { preserveState: true, replace: true });
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function openEdit(supplier) {
        editForm.setData({ name: supplier.name, phone: supplier.phone, company_name: supplier.company_name ?? '', address: supplier.address ?? '' });
        setEditing(supplier);
    }

    function handleCreate(e) {
        e.preventDefault();
        createForm.post(route('party.supplier.store'), {
            onSuccess: () => { setCreating(false); createForm.reset(); },
        });
    }

    function handleUpdate(e) {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('party.supplier.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('party.supplier.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (suppliers.from ?? 0) + i },
        { id: 'name', header: 'Supplier', render: (row) => (
            <div>
                <p className="font-medium">{row.name}</p>
                <p className="text-xs text-muted-foreground">{row.phone}</p>
            </div>
        )},
        { id: 'company', header: 'Company', render: (row) => row.company_name ?? '—' },
        { id: 'address', header: 'Address', render: (row) => row.address ?? '—' },
        { id: 'balance', header: 'Balance', render: (row) => `৳${parseFloat(row.balance).toFixed(2)}` },
        { id: 'actions', header: 'Actions', align: 'right', render: (row) => (
            <AdminInlineActions
                prefix="party.supplier"
                onEdit={() => openEdit(row)}
                onDelete={() => setDeleting(row)}
            />
        )},
    ];

    return (
        <>
            <Head title="Suppliers" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Users className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Suppliers</h1>
                            <p className="text-xs text-white/60">Manage your suppliers.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="party.supplier.create"
                        onClick={() => setCreating(true)}
                        label="Add Supplier"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name or phone…"
                        className="max-w-xs"
                    />
                </div>

                <DataTable columns={columns} rows={suppliers.data} rowKey="id" emptyMessage="No suppliers found." />

                {suppliers.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {suppliers.links.map((link, i) => (
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

            <Can permission="party.supplier.create">
            {/* Create Dialog */}
            <Dialog open={creating} onOpenChange={(open) => { if (!open) { setCreating(false); createForm.reset(); } }}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Plus className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Add Supplier</h2>
                    </div>
                    <SupplierForm form={createForm} onSubmit={handleCreate} onCancel={() => setCreating(false)} isEditing={false} />
                </DialogContent>
            </Dialog>
            </Can>

            <Can permission="party.supplier.update">
            {/* Edit Dialog */}
            <Dialog open={!!editing} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Pencil className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Edit Supplier</h2>
                    </div>
                    <SupplierForm form={editForm} onSubmit={handleUpdate} onCancel={() => setEditing(null)} isEditing={true} />
                </DialogContent>
            </Dialog>
            </Can>

            {can('party.supplier.delete') && (
            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="p-0">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Trash2 className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Delete Supplier</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <strong>{deleting?.name}</strong>?
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
