import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRightLeft, Check, Eye, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';

function distributionStatusBadgeClassName(status, statusLabel) {
    if (status === 2 || statusLabel === 'Received') {
        return 'border-transparent bg-emerald-600 text-white hover:bg-emerald-600';
    }

    if (status === 3 || statusLabel === 'Partially Received') {
        return 'border-transparent bg-amber-500 text-white hover:bg-amber-500';
    }

    if (status === 4 || statusLabel === 'Return Pending') {
        return 'border-transparent bg-orange-600 text-white hover:bg-orange-600';
    }

    if (status === 5 || statusLabel === 'Returned') {
        return 'border-transparent bg-slate-600 text-white hover:bg-slate-600';
    }

    return 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary';
}

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
                isReceiverView
                    ? route('inventory.stock-distribution.received')
                    : route('inventory.stock-distribution.index'),
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
            id: 'status',
            header: 'Status',
            render: (row) => {
                const statusLabel = row.status_label ?? 'Pending';
                const isReceived = row.status === 2 || statusLabel === 'Received';
                const isReturnPending = row.status === 4 || statusLabel === 'Return Pending';
                const isReturned = row.status === 5 || statusLabel === 'Returned';

                return (
                    <div className="space-y-0.5">
                        <Badge className={cn(distributionStatusBadgeClassName(row.status, statusLabel))}>
                            {statusLabel}
                        </Badge>
                        {isReceived && row.received_by?.name && (
                            <p className="text-[10px] text-muted-foreground">by {row.received_by.name}</p>
                        )}
                        {isReturnPending && row.return_sent_by?.name && (
                            <p className="text-[10px] text-muted-foreground">sent by {row.return_sent_by.name}</p>
                        )}
                        {isReturned && row.return_received_by?.name && (
                            <p className="text-[10px] text-muted-foreground">by {row.return_received_by.name}</p>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => {
                const isPending = row.status === 1 || row.status_label === 'Pending';
                const isPartial = row.status === 3 || row.status_label === 'Partially Received';
                const canReceiveRow = isReceiverView && (isPending || isPartial);

                if (isReceiverView) {
                    return (
                        <div className="flex justify-end gap-1">
                            <Button size="sm" variant="ghost" asChild className="h-7">
                                <Link href={route('inventory.stock-distribution.show', row.id)}>
                                    <Eye className="size-3.5" />
                                    View
                                </Link>
                            </Button>
                            {canReceiveRow && can('inventory.stock-distribution.receive') && (
                                <Button
                                    size="sm"
                                    className="h-7 bg-emerald-600 text-white hover:bg-emerald-600"
                                    asChild
                                >
                                    <Link href={route('inventory.stock-distribution.show', row.id)}>
                                        <Check className="size-3.5" />
                                        Receive
                                    </Link>
                                </Button>
                            )}
                        </div>
                    );
                }

                return canManage ? (
                    <AdminRowActions
                        prefix="inventory.stock-distribution"
                        id={row.id}
                        showRoute="inventory.stock-distribution.show"
                        editRoute={isPending ? 'inventory.stock-distribution.edit' : undefined}
                        onDelete={isPending ? () => setDeleting(row) : undefined}
                    />
                ) : (
                    <Button size="sm" variant="ghost" asChild className="h-7">
                        <Link href={route('inventory.stock-distribution.show', row.id)}>
                            <Eye className="size-3.5" />
                            View
                        </Link>
                    </Button>
                );
            },
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
                                    ? 'Pending and received stock from main branch.'
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
