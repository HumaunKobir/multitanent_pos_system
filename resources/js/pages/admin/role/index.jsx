import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Shield, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { AdminCreateLink } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

export default function RoleIndex({ roles }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('role.destroy', { role: deleting.id }), {
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => i + 1,
        },
        { header: 'Role Name', accessorKey: 'name' },
        {
            id: 'users',
            header: 'Users',
            render: (row) => (
                <Badge variant="secondary" className="text-xs">
                    {row.users_count} {row.users_count === 1 ? 'user' : 'users'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    {can('role.update') && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('role.permissions', { role: row.id })}>
                                <KeyRound className="size-3.5" />
                                Permissions
                            </Link>
                        </Button>
                    )}
                    {can('role.update') && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('role.edit', { role: row.id })}>
                                <Pencil className="size-3.5" />
                            </Link>
                        </Button>
                    )}
                    {can('role.delete') && (
                        <Button size="sm" variant="destructive" onClick={() => setDeleting(row)}>
                            <Trash2 className="size-3.5" />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Roles" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Shield className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Roles</h1>
                            <p className="text-xs text-white/60">Manage roles and their permissions.</p>
                        </div>
                    </div>
                    <AdminCreateLink
                        permission="role.create"
                        href={route('role.create')}
                        label="Add Role"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <DataTable columns={columns} rows={roles} rowKey="id" emptyMessage="No roles found." />
            </div>

            {can('role.delete') && (
            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Role</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Are you sure you want to delete <strong>{deleting?.name}</strong>? This action cannot be undone.
                    </p>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" size="sm" className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button size="sm" className="bg-red-600 text-white shadow-sm shadow-red-500/30 transition-all duration-150 hover:bg-red-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-red-500/50" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            )}
        </>
    );
}
