import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Contact, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';

function PartyForm({ form, onSubmit, onCancel, isEditing }) {
    return (
        <form onSubmit={onSubmit} className="space-y-1.5 px-3 py-2">
            <FormField label="Name" required name="name" error={form.errors.name}>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Party name"
                    className="mt-1"
                    aria-invalid={!!form.errors.name}
                />
            </FormField>
            <div className="grid grid-cols-2 gap-3">
                <FormField label="Phone" name="phone" error={form.errors.phone}>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        placeholder="01XXXXXXXXX"
                        className="mt-1"
                        aria-invalid={!!form.errors.phone}
                    />
                </FormField>
                <FormField label="Email" name="email" error={form.errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        placeholder="email@example.com"
                        className="mt-1"
                        aria-invalid={!!form.errors.email}
                    />
                </FormField>
            </div>
            <FormField label="Address" name="address" error={form.errors.address}>
                <Input
                    id="address"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                    placeholder="Address"
                    className="mt-1"
                />
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
                    className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/50"
                >
                    {form.processing ? 'Saving…' : isEditing ? 'Update' : 'Create'}
                </Button>
            </div>
        </form>
    );
}

export default function PartiesIndex({ parties, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const createForm = useForm({ name: '', phone: '', email: '', address: '' });
    const editForm = useForm({ name: '', phone: '', email: '', address: '' });

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                route('party.parties.index'),
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function openEdit(party) {
        editForm.setData({
            name: party.name ?? '',
            phone: party.phone ?? '',
            email: party.email ?? '',
            address: party.address ?? '',
        });
        editForm.clearErrors();
        setEditing(party);
    }

    function submitCreate(e) {
        e.preventDefault();
        createForm.post(route('party.parties.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    }

    function submitEdit(e) {
        e.preventDefault();
        if (!editing) return;

        editForm.put(route('party.parties.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function confirmDelete() {
        if (!deleting) return;

        router.delete(route('party.parties.destroy', deleting.id), {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (parties.from ?? 0) + i },
        { id: 'name', header: 'Name', render: (row) => <span className="font-medium">{row.name}</span> },
        { id: 'phone', header: 'Phone', render: (row) => row.phone || '—' },
        { id: 'email', header: 'Email', render: (row) => row.email || '—' },
        {
            id: 'address',
            header: 'Address',
            render: (row) => (
                <span className="block max-w-64 truncate text-muted-foreground" title={row.address ?? ''}>
                    {row.address || '—'}
                </span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <AdminInlineActions
                    prefix="party.parties"
                    onEdit={() => openEdit(row)}
                    onDelete={() => setDeleting(row)}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Parties" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Contact className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Parties</h1>
                            <p className="text-xs text-white/60">Third parties for income and expense vouchers.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="party.parties.create"
                        label="New Party"
                        icon={Plus}
                        onClick={() => {
                            createForm.reset();
                            createForm.clearErrors();
                            setCreating(true);
                        }}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name, phone, email…"
                            className="pl-8"
                        />
                    </div>
                </div>

                <DataTable columns={columns} rows={parties.data} rowKey="id" emptyMessage="No parties found." />
            </div>

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent className="max-w-md p-0">
                    <div className="border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">New Party</h2>
                    </div>
                    <PartyForm form={createForm} onSubmit={submitCreate} onCancel={() => setCreating(false)} isEditing={false} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editing} onOpenChange={(open) => (!open ? setEditing(null) : null)}>
                <DialogContent className="max-w-md p-0">
                    <div className="border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">Edit Party</h2>
                    </div>
                    <PartyForm form={editForm} onSubmit={submitEdit} onCancel={() => setEditing(null)} isEditing />
                </DialogContent>
            </Dialog>

            <Can permission="party.parties.delete">
                <Dialog open={!!deleting} onOpenChange={(open) => (!open ? setDeleting(null) : null)}>
                    <DialogContent className="max-w-sm">
                        <h2 className="text-sm font-semibold">Delete party?</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            This will permanently remove {deleting?.name}.
                        </p>
                        <div className="mt-4 flex justify-end gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="outline" size="sm">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="button" variant="destructive" size="sm" onClick={confirmDelete}>
                                Delete
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>
            </Can>
        </>
    );
}
