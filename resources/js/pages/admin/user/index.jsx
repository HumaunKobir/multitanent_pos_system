import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { resourceRoutes } from '@/lib/route';
import UserFormDialog from './form-dialog';

const routes = resourceRoutes('user');

export default function UserIndex({ users, branches }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    function handleDelete() {
        if (!deleting) return;
        router.delete(routes.destroy(deleting.id), {
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
            render: (_, i) => i + 1,
        },
        { header: 'Name', accessorKey: 'name' },
        { header: 'Email', accessorKey: 'email' },
        { header: 'Phone', accessorKey: 'phone' },
        {
            id: 'branch',
            header: 'Branch',
            render: (row) => row.branch_name ?? 'All Branches',
        },
        {
            id: 'status',
            header: 'Status',
            render: (row) => (
                <Badge
                    className={
                        Number(row.status) === 1
                            ? 'bg-green-600 text-white hover:bg-green-700'
                            : 'bg-red-600 text-white hover:bg-red-700'
                    }
                >
                    {Number(row.status) === 1 ? 'Active' : 'InActive'}
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
            <Head title="Users" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Users className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Users</h1>
                            <p className="text-xs text-white/60">Manage your admin users.</p>
                        </div>
                    </div>
                    <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md">
                        <button type="button" onClick={openCreate}>
                            <Plus className="size-3.5" />
                            Add New
                        </button>
                    </Button>
                </div>

                <DataTable columns={columns} rows={users} rowKey="id" emptyMessage="No users found." />

                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete User</DialogTitle>
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

                <UserFormDialog
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    item={editing}
                    routes={routes}
                    branches={branches}
                />
            </div>
        </>
    );
}
