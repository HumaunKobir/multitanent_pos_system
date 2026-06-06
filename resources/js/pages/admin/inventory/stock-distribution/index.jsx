import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRightLeft, Eye, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

export default function StockDistributionIndex({
    distributions = { data: [] },
    filters = {},
    canManage = false,
    isReceiverView = false,
}) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () =>
            router.get(
                route('inventory.stock-distribution.index'),
                { search: search || undefined },
                { preserveState: true, replace: true },
            ),
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting?.id) {
            return;
        }

        router.delete(route('inventory.stock-distribution.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (distributions.from ?? 0) + i },
        {
            id: 'invoice',
            header: 'Invoice',
            render: (row) => (
                <span className="font-mono text-xs font-semibold text-primary">
                    {row.invoice_number ?? `INVT${String(row.id).padStart(8, '0')}`}
                </span>
            ),
        },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        {
            id: 'branch',
            header: isReceiverView ? 'From' : 'To Branch',
            render: (row) => (
                <span className="font-medium">
                    {isReceiverView ? 'Main Branch' : (row.to_branch?.name ?? '—')}
                </span>
            ),
        },
        { id: 'comment', header: 'Note', render: (row) => row.comment ?? '—' },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) =>
                canManage ? (
                    <AdminRowActions
                        prefix="inventory.stock-distribution"
                        id={row.id}
                        showRoute="inventory.stock-distribution.show"
                        editRoute="inventory.stock-distribution.edit"
                        onDelete={() => setDeleting(row)}
                    />
                ) : (
                    <Button size="sm" variant="ghost" asChild className="h-7">
                        <Link href={route('inventory.stock-distribution.show', row.id)}>
                            <Eye className="size-3.5" />
                            View
                        </Link>
                    </Button>
                ),
        },
    ];

    return (
        <>
            <Head title={isReceiverView ? 'Received Stock' : 'Distribute Stock'} />
            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <ArrowRightLeft className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">
                                {isReceiverView ? 'Received Stock' : 'Distribute Stock'}
                            </h1>
                            <p className="text-xs text-white/60">
                                {isReceiverView
                                    ? 'Stock received from main branch.'
                                    : 'Transfer stock from main branch to operating branches.'}
                            </p>
                        </div>
                    </div>
                    {canManage && (
                        <AdminCreateLink
                            permission="inventory.stock-distribution.create"
                            href={route('inventory.stock-distribution.create')}
                            label="New Distribution"
                            icon={Plus}
                            className="border border-white/30 bg-white/10 text-white hover:bg-white/20"
                        />
                    )}
                </div>
                <div className="relative mb-4 max-w-xs">
                    <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by branch or note…"
                        className="pl-8"
                    />
                </div>
                <DataTable
                    columns={columns}
                    rows={distributions.data}
                    rowKey="id"
                    emptyMessage={isReceiverView ? 'No stock received yet.' : 'No distributions yet.'}
                />

                {canManage && can('inventory.stock-distribution.delete') && (
                    <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete distribution?</DialogTitle>
                                <DialogDescription>
                                    Stock will be restored to main branch and accounting will be reversed.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline" size="sm">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>
                                    Delete
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </>
    );
}
