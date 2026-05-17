import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { settingRoutes } from '@/lib/route';
import SettingFormDialog from '../form-dialog';

const routes = settingRoutes('color');

export default function ColorIndex({ colors, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    function handleSearch(e) {
        e.preventDefault();
        router.get(routes.index({ search }), { preserveState: true, replace: true });
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(routes.destroy(deleting?.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(row) {
        setEditing(row);
        setFormOpen(true);
    }

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (colors.from ?? 0) + i,
        },
        { header: 'Name', accessorKey: 'name' },
        {
            id: 'code',
            header: 'Code',
            render: (row) =>
                row.code ? (
                    <div className="flex items-center gap-2">
                        <span
                            className="inline-block h-5 w-5 rounded-full border border-border"
                            style={{ backgroundColor: row.code }}
                        />
                        <span className="text-xs font-mono">{row.code}</span>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
        {
            id: 'status',
            header: 'Status',
            render: (row) => (
                <Badge className={row.status ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-600 text-white hover:bg-red-700'}>
                    {row.status ? 'Active' : 'InActive'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" asChild>
                        <button type="button" onClick={() => openEdit(row)}>
                            <Pencil className="size-3.5" />
                        </button>
                    </Button>
                    <Button size="sm" variant="destructive" onClick={() => setDeleting(row)}>
                        <Trash2 className="size-3.5" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Colors" />

            <div className="p-6">
                <div className="mb-5 flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Colors</h1>
                    <Button asChild>
                        <button type="button" onClick={openCreate}>
                            <Plus className="size-4" />
                            Add New
                        </button>
                    </Button>
                </div>

                <form onSubmit={handleSearch} className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name..."
                        className="max-w-xs"
                    />
                    <Button type="submit" variant="outline" size="sm">
                        <Search className="size-4" />
                    </Button>
                </form>

                <DataTable columns={columns} rows={colors.data} rowKey="id" emptyMessage="No colors found." />

                {colors.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {colors.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                className={[
                                    'border px-3 py-1 text-sm transition-colors',
                                    link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                preserveScroll
                            />
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Color</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Are you sure you want to delete <strong>{deleting?.name}</strong>? This action cannot be undone.
                    </p>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <SettingFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title="Color"
                item={editing}
                routes={routes}
                fields={[
                    { name: 'name', label: 'Name', placeholder: 'Color name' },
                    { name: 'code', label: 'Color Code (optional)', type: 'color', placeholder: '#000000' },
                    { name: 'status', label: 'Status', type: 'select', defaultValue: '1' },
                ]}
            />
        </>
    );
}
