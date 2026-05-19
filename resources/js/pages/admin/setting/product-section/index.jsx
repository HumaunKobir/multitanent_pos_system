import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, LayoutDashboard, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { settingRoutes } from '@/lib/route';
import ProductSectionFormDialog from './form-dialog';

const routes = settingRoutes('productsection');

export default function ProductSectionIndex({
    sections,
    filters,
    layoutTypeOptions,
    blockTypeOptions,
    productOptions,
}) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const [rows, setRows] = useState(sections.data ?? []);

    useEffect(() => {
        setRows(sections.data ?? []);
    }, [sections.data]);

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
        router.delete(routes.destroy(deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    function moveRow(index, direction) {
        const next = [...rows];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;

        [next[index], next[target]] = [next[target], next[index]];
        setRows(next);
    }

    function saveOrder() {
        router.post(
            routes.updateOrder(),
            {
                orders: rows.map((row, index) => ({
                    id: row.id,
                    serial: index + 1,
                })),
            },
            { preserveScroll: true },
        );
    }

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (sections.from ?? 0) + i,
        },
        { header: 'Name', accessorKey: 'name' },
        { header: 'Serial', accessorKey: 'serial' },
        { header: 'Layout', accessorKey: 'layout_type_label' },
        { header: 'Block', accessorKey: 'block_type_label' },
        { header: 'Per Line', accessorKey: 'block_per_line' },
        {
            id: 'status',
            header: 'Status',
            render: (row) => (
                <Badge className={row.status ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-600 text-white hover:bg-red-700'}>
                    {row.status ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            id: 'order',
            header: 'Order',
            render: (row, index) => (
                <div className="flex gap-1">
                    <Button type="button" size="sm" variant="outline" onClick={() => moveRow(index, -1)}>
                        <ArrowUp className="size-3.5" />
                    </Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => moveRow(index, 1)}>
                        <ArrowDown className="size-3.5" />
                    </Button>
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" asChild>
                        <button type="button" onClick={() => { setEditing(row); setFormOpen(true); }}>
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
            <Head title="Product Sections" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <LayoutDashboard className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Product Sections</h1>
                            <p className="text-xs text-white/60">Manage your homepage product sections.</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline" className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md" onClick={saveOrder}>
                            Save Order
                        </Button>
                        <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md">
                            <button type="button" onClick={() => { setEditing(null); setFormOpen(true); }}>
                                <Plus className="size-3.5" />
                                Add New
                            </button>
                        </Button>
                    </div>
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

                <DataTable columns={columns} rows={rows} rowKey="id" emptyMessage="No product sections found." />

                {sections.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {sections.links.map((link, i) => (
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
                        <DialogTitle>Delete Product Section</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Are you sure you want to delete <strong>{deleting?.name || 'this section'}</strong>?
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

            <ProductSectionFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                item={editing}
                routes={routes}
                layoutTypeOptions={layoutTypeOptions}
                blockTypeOptions={blockTypeOptions}
                productOptions={productOptions}
            />
        </>
    );
}
