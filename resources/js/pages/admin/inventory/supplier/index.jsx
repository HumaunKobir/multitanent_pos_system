import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

function SupplierForm({ form, onSubmit, onCancel }) {
    return (
        <form onSubmit={onSubmit} className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                    <Label htmlFor="name">Name *</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Supplier name"
                    />
                    {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="phone">Phone *</Label>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        placeholder="01XXXXXXXXX"
                    />
                    {form.errors.phone && <p className="text-xs text-destructive">{form.errors.phone}</p>}
                </div>
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="company_name">Company Name</Label>
                <Input
                    id="company_name"
                    value={form.data.company_name}
                    onChange={(e) => form.setData('company_name', e.target.value)}
                    placeholder="Company name (optional)"
                />
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="address">Address</Label>
                <Input
                    id="address"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                    placeholder="Address (optional)"
                />
            </div>
            <DialogFooter className="pt-2">
                <DialogClose asChild>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function SupplierIndex({ suppliers, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const createForm = useForm({ name: '', phone: '', company_name: '', address: '' });
    const editForm = useForm({ name: '', phone: '', company_name: '', address: '' });

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('party.supplier.index'), { search: search || undefined }, { preserveState: true, replace: true });
    }

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
        { id: 'balance', header: 'Due Balance', render: (row) => (
            <Badge className={parseFloat(row.balance) > 0 ? 'bg-red-600 text-white' : 'bg-green-600 text-white'}>
                ৳{parseFloat(row.balance).toFixed(2)}
            </Badge>
        )},
        { id: 'actions', header: 'Actions', align: 'right', render: (row) => (
            <div className="flex justify-end gap-2">
                <Button size="sm" variant="outline" onClick={() => openEdit(row)}>
                    <Pencil className="size-3.5" />
                </Button>
                <Button size="sm" variant="destructive" onClick={() => setDeleting(row)}>
                    <Trash2 className="size-3.5" />
                </Button>
            </div>
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
                    <Button
                        size="sm"
                        onClick={() => setCreating(true)}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    >
                        <Plus className="size-3.5" />
                        Add Supplier
                    </Button>
                </div>

                <form onSubmit={handleSearch} className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name or phone…"
                        className="max-w-xs"
                    />
                    <Button type="submit" variant="outline" size="sm">
                        <Search className="size-4" />
                    </Button>
                </form>

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

            {/* Create Dialog */}
            <Dialog open={creating} onOpenChange={(open) => { if (!open) { setCreating(false); createForm.reset(); } }}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Plus className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Add Supplier</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <SupplierForm form={createForm} onSubmit={handleCreate} onCancel={() => setCreating(false)} />
                    </div>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editing} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Pencil className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Edit Supplier</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <SupplierForm form={editForm} onSubmit={handleUpdate} onCancel={() => setEditing(null)} />
                    </div>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
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
                        <DialogFooter className="mt-4">
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" onClick={handleDelete}>
                                Delete
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
