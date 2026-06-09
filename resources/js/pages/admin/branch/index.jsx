import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { resourceRoutes } from '@/lib/route';
import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import BranchFormDialog from './form-dialog';

const routes = resourceRoutes('branch');

function statusLabel(status) {
    const value = typeof status === 'object' ? status?.value : status;
    return Number(value) === 1 ? 'Active' : 'InActive';
}

function isActive(status) {
    const value = typeof status === 'object' ? status?.value : status;
    return Number(value) === 1;
}

export default function BranchIndex({ branches, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [formOpen, setFormOpen] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(routes.index({ search: search || undefined }), { preserveState: true, replace: true });
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(row) {
        setEditing(row);
        setFormOpen(true);
    }

    function handleDelete() {
        if (!deleting) {
            return;
        }

        router.delete(routes.destroy(deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (branches.from ?? 0) + i,
        },
        { header: 'Name', accessorKey: 'name' },
        { header: 'Phone', accessorKey: 'phone' },
        { header: 'Address', accessorKey: 'address' },
        {
            id: 'status',
            header: 'Status',
            render: (row) => (
                <Badge
                    className={
                        isActive(row.status)
                            ? 'bg-green-600 text-white hover:bg-green-700'
                            : 'bg-red-600 text-white hover:bg-red-700'
                    }
                >
                    {statusLabel(row.status)}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <AdminInlineActions
                    prefix="branch"
                    onEdit={() => openEdit(row)}
                    onDelete={() => setDeleting(row)}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Branches" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Building2 className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Branches</h1>
                            <p className="text-xs text-white/60">Manage your store branches.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="branch.create"
                        onClick={openCreate}
                        label="Add New"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name..."
                        className="max-w-xs"
                    />
                </div>

                <DataTable columns={columns} rows={branches.data} rowKey="id" emptyMessage="No branches found." />

                {branches.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {branches.links.map((link, i) => (
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

            <Can permission={['branch.create', 'branch.update']}>
                <BranchFormDialog open={formOpen} onOpenChange={setFormOpen} item={editing} routes={routes} />
            </Can>

            <Can permission="branch.delete">
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Delete branch?</DialogTitle>
                        </DialogHeader>
                        <p className="text-sm text-muted-foreground">
                            Delete <span className="font-medium text-foreground">{deleting?.name}</span>? This cannot be undone.
                        </p>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline" size="sm">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" size="sm" onClick={handleDelete}>
                                Delete
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </Can>
        </>
    );
}
