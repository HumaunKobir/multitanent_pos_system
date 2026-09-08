import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { useCan } from '@/hooks/use-can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Filter, Pencil, Plus, RotateCcw, Search, Trash2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { resourceRoutes } from '@/lib/route';
import UserFormDialog from './form-dialog';

const routes = resourceRoutes('user');

export default function UserIndex({ users, branches = {}, roles = {}, filters = {} }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();

    const [search, setSearch] = useState(filters.search ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id || 'all');
    const [roleId, setRoleId] = useState(filters.role_id || 'all');
    const [status, setStatus] = useState(filters.status || 'all');

    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);

    const hasActiveFilters = Boolean(
        search ||
        (branchId && branchId !== 'all') ||
        (roleId && roleId !== 'all') ||
        (status && status !== 'all')
    );

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                routes.index(),
                {
                    search: search || undefined,
                    branch_id: branchId && branchId !== 'all' ? branchId : undefined,
                    role_id: roleId && roleId !== 'all' ? roleId : undefined,
                    status: status && status !== 'all' ? status : undefined,
                },
                { preserveState: true, replace: true }
            );
        },
        [search, branchId, roleId, status],
        350,
        { skipFirstRun: true }
    );

    function handleReset() {
        setSearch('');
        setBranchId('all');
        setRoleId('all');
        setStatus('all');
    }

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
            header: 'No.',
            render: (_, i) => (users.from ?? 1) + i,
        },
        { header: 'Name', accessorKey: 'name' },
        { header: 'Email', accessorKey: 'email' },
        { header: 'Phone', accessorKey: 'phone' },
        {
            id: 'branch',
            header: 'Branch',
            render: (row) => row.branch_name ?? '—',
        },
        {
            id: 'role',
            header: 'Role',
            render: (row) =>
                row.role_name ? (
                    <Badge variant="outline" className="text-xs border-slate-300 dark:border-slate-700">
                        {row.role_name}
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
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
                <AdminInlineActions prefix="user" onEdit={() => openEdit(row)} onDelete={() => setDeleting(row)} />
            ),
        },
    ];

    return (
        <>
            <Head title="Users" />

            <div className="px-2 py-1 space-y-3">
                {/* Header */}
                <div className="flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3 shadow-md">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15 shadow-xs">
                            <Users className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Users</h1>
                            <p className="text-xs text-white/60">Manage system users, branch assignments, and roles.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="user.create"
                        onClick={openCreate}
                        label="Add New"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                {/* Filter Toolbar */}
                <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-card p-3.5 shadow-xs">
                    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">
                        {/* Search Input */}
                        <div className="relative lg:col-span-2">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input
                                placeholder="Search by name, email, or phone..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-9 pl-9 text-xs border-slate-300 dark:border-slate-700 bg-background"
                            />
                        </div>

                        {/* Branch Filter */}
                        <div>
                            <Select value={branchId} onValueChange={setBranchId}>
                                <SelectTrigger className="h-9 text-xs border-slate-300 dark:border-slate-700 bg-background">
                                    <SelectValue placeholder="All Branches" />
                                </SelectTrigger>
                                <SelectContent className="border-slate-300 dark:border-slate-700">
                                    <SelectItem value="all">All Branches</SelectItem>
                                    {Object.entries(branches).map(([id, name]) => (
                                        <SelectItem key={id} value={String(id)}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Role Filter */}
                        <div>
                            <Select value={roleId} onValueChange={setRoleId}>
                                <SelectTrigger className="h-9 text-xs border-slate-300 dark:border-slate-700 bg-background">
                                    <SelectValue placeholder="All Roles" />
                                </SelectTrigger>
                                <SelectContent className="border-slate-300 dark:border-slate-700">
                                    <SelectItem value="all">All Roles</SelectItem>
                                    {Object.entries(roles).map(([id, name]) => (
                                        <SelectItem key={id} value={String(id)}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Status Filter & Reset */}
                        <div className="flex items-center gap-2">
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger className="h-9 text-xs border-slate-300 dark:border-slate-700 bg-background flex-1">
                                    <SelectValue placeholder="All Status" />
                                </SelectTrigger>
                                <SelectContent className="border-slate-300 dark:border-slate-700">
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">InActive</SelectItem>
                                </SelectContent>
                            </Select>

                            {hasActiveFilters && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleReset}
                                    className="h-9 px-2.5 text-xs text-muted-foreground hover:text-foreground border-slate-300 dark:border-slate-700"
                                    title="Reset filters"
                                >
                                    <RotateCcw className="size-3.5 mr-1" />
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Table */}
                <DataTable columns={columns} rows={users.data} rowKey="id" emptyMessage="No users found matching your filters." />

                {/* Pagination */}
                {users.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {users.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                className={[
                                    'border rounded-md px-3 py-1 text-xs transition-colors border-slate-300 dark:border-slate-700',
                                    link.active ? 'border-primary bg-primary text-primary-foreground font-semibold' : 'bg-card text-foreground hover:bg-muted',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                preserveScroll
                            />
                        ))}
                    </div>
                )}

                {/* Delete Confirmation Dialog */}
                {can('user.delete') && (
                    <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                        <DialogContent className="border-slate-300 dark:border-slate-700">
                            <DialogHeader>
                                <DialogTitle>Delete User</DialogTitle>
                            </DialogHeader>
                            <p className="text-sm text-muted-foreground">
                                Are you sure you want to delete <strong>{deleting?.name}</strong>? This action cannot be undone.
                            </p>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-slate-300 dark:border-slate-700"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    size="sm"
                                    className="bg-red-600 text-white hover:bg-red-700 shadow-xs"
                                    onClick={handleDelete}
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}

                {/* Create / Edit Form Modal */}
                <Can permission={['user.create', 'user.update']}>
                    <UserFormDialog
                        open={formOpen}
                        onOpenChange={setFormOpen}
                        item={editing}
                        routes={routes}
                        branches={branches}
                        roles={roles}
                    />
                </Can>
            </div>
        </>
    );
}
