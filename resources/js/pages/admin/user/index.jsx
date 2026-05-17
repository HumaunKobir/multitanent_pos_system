import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
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

            <div className="p-6">
                <div className="mb-5 flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Users</h1>
                    <Button asChild>
                        <button type="button" onClick={openCreate}>
                            <Plus className="size-4" />
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
