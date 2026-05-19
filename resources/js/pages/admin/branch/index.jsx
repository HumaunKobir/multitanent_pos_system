import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { resourceRoutes } from '@/lib/route';
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
    const [formOpen, setFormOpen] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    function handleSearch(e) {
        e.preventDefault();
        router.get(routes.index({ search }), { preserveState: true, replace: true });
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
                <div className="flex justify-end">
                    <Button size="sm" variant="outline" asChild>
                        <button type="button" onClick={() => openEdit(row)}>
                            <Pencil className="size-3.5" />
                        </button>
                    </Button>
                </div>
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
                    <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md">
                        <button type="button" onClick={openCreate}>
                            <Plus className="size-3.5" />
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

            <BranchFormDialog open={formOpen} onOpenChange={setFormOpen} item={editing} routes={routes} />
        </>
    );
}
