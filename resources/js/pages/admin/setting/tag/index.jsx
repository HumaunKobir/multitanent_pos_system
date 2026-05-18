import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { settingRoutes } from '@/lib/route';
import TagFormDialog from './form-dialog';

const routes = settingRoutes('tag');

const statusPillBase =
    'inline-flex items-center border px-2.5 py-1 text-[0.8125rem] leading-tight tracking-tight';

const statusStyles = {
    0: cn(
        statusPillBase,
        'border-amber-500/90 bg-amber-500 font-semibold text-white shadow-[0_6px_22px_-6px_rgba(245,158,11,0.55),0_2px_8px_-2px_rgba(217,119,6,0.35)] dark:border-amber-400/70 dark:bg-amber-600',
    ),
    1: cn(
        statusPillBase,
        'border-emerald-500/90 bg-emerald-600 font-semibold text-white shadow-[0_6px_22px_-6px_rgba(16,185,129,0.55),0_2px_8px_-2px_rgba(5,150,105,0.35)] dark:border-emerald-400/70 dark:bg-emerald-700',
    ),
    2: cn(
        statusPillBase,
        'border-red-500/90 bg-red-600 font-semibold text-white shadow-[0_6px_22px_-6px_rgba(239,68,68,0.55),0_2px_8px_-2px_rgba(220,38,38,0.35)] dark:border-red-400/70 dark:bg-red-700',
    ),
};

function collectDescendantIds(tagId, tagHierarchy) {
    const ids = new Set([tagId]);
    const queue = [tagId];

    while (queue.length > 0) {
        const current = queue.shift();
        tagHierarchy
            .filter((tag) => tag.parent_id === current)
            .forEach((tag) => {
                if (!ids.has(tag.id)) {
                    ids.add(tag.id);
                    queue.push(tag.id);
                }
            });
    }

    return ids;
}

function resolveStatusValue(status) {
    if (typeof status === 'object' && status !== null && 'value' in status) {
        return status.value;
    }

    return status;
}

function statusLabel(row, statusOptions) {
    if (row.status_label) {
        return row.status_label;
    }

    return statusOptions.find((option) => option.value === String(resolveStatusValue(row.status)))?.label ?? 'Pending';
}

export default function TagIndex({ tags, filters, parentOptions, statusOptions, tagHierarchy }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);

    const formParentOptions = useMemo(() => {
        if (!editing) {
            return parentOptions;
        }

        const invalidIds = collectDescendantIds(editing.id, tagHierarchy);

        return parentOptions.filter((option) => option.value === '__none__' || !invalidIds.has(Number(option.value)));
    }, [editing, parentOptions, tagHierarchy]);

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
            render: (_, i) => (tags.from ?? 0) + i,
        },
        {
            id: 'image',
            header: 'Image',
            render: (row) =>
                row.image ? (
                    <img src={`/storage/${row.image}`} alt={row.name} className="h-8 w-8 rounded-none object-cover" />
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
        { header: 'Name', accessorKey: 'name' },
        {
            id: 'parent',
            header: 'Parent',
            render: (row) => row.parent?.name ?? <span className="text-xs text-muted-foreground">—</span>,
        },
        {
            id: 'status',
            header: 'Status',
            render: (row) => (
                <span className={statusStyles[resolveStatusValue(row.status)] ?? statusStyles[0]}>
                    {statusLabel(row, statusOptions)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-1">
                    <button
                        type="button"
                        onClick={() => openEdit(row)}
                        aria-label={`Edit ${row.name}`}
                        className="inline-flex size-6 items-center justify-center rounded-none border border-indigo-200 bg-indigo-50 text-indigo-600 transition-all duration-200 hover:-translate-y-1 hover:border-indigo-500 hover:bg-indigo-600 hover:text-white hover:shadow-sm hover:shadow-indigo-500/35"
                    >
                        <Pencil className="size-2.5" strokeWidth={2.5} />
                    </button>
                    <button
                        type="button"
                        onClick={() => setDeleting(row)}
                        aria-label={`Delete ${row.name}`}
                        className="inline-flex size-6 items-center justify-center rounded-none border border-red-200 bg-red-50 text-red-600 transition-all duration-200 hover:-translate-y-1 hover:border-red-500 hover:bg-red-600 hover:text-white hover:shadow-sm hover:shadow-red-500/35"
                    >
                        <Trash2 className="size-2.5" strokeWidth={2.5} />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Tags" />

            <div className="p-6">
                <div className="mb-5 flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Tags</h1>
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

                <DataTable columns={columns} rows={tags.data} rowKey="id" emptyMessage="No tags found." />

                {tags.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {tags.links.map((link, i) => (
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
                        <DialogTitle>Delete Tag</DialogTitle>
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

            <TagFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                item={editing}
                routes={routes}
                parentOptions={formParentOptions}
                statusOptions={statusOptions}
            />
        </>
    );
}
