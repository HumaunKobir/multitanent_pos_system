import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Eye, HandCoins, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

export default function PurchaseReturnIndex({ returns = { data: [] }, filters = {} }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);

    function handleDelete() {
        if (!deleting?.id) {
            return;
        }

        router.delete(route('inventory.purchase-return.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => router.get(route('inventory.purchase-return.index'), { search: search || undefined }, { preserveState: true, replace: true }),
        [search],
        350,
        { skipFirstRun: true },
    );

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (returns.from ?? 0) + i },
        { id: 'invoice', header: 'Invoice', render: (row) => <span className="font-mono text-xs font-semibold text-primary">{row.invoice_number ?? `INVPR${String(row.id).padStart(8, '0')}`}</span> },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        { id: 'supplier', header: 'Supplier', render: (row) => row.supplier?.name ?? '—' },
        { id: 'total', header: 'Total', render: (row) => `৳${parseFloat(row.net_amount ?? row.gross_amount ?? 0).toFixed(2)}` },
        { id: 'paid', header: 'Paid', render: (row) => <span className="text-green-700 dark:text-green-400">৳{parseFloat(row.paid_amount ?? 0).toFixed(2)}</span> },
        { id: 'due', header: 'Due', render: (row) => {
            const due = parseFloat(row.due_amount ?? 0);
            return <span className={due > 0 ? 'font-semibold text-destructive' : 'font-semibold text-green-700 dark:text-green-400'}>৳{due.toFixed(2)}</span>;
        }},
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <AdminRowActions
                    prefix="inventory.purchase-return"
                    id={row.id}
                    showRoute="inventory.purchase-return.show"
                    editRoute="inventory.purchase-return.edit"
                    onDelete={() => setDeleting(row)}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Purchase Returns" />
            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15"><HandCoins className="size-4 text-white" /></div>
                        <div><h1 className="text-base font-semibold text-white">Purchase Returns</h1></div>
                    </div>
                    <AdminCreateLink
                        permission="inventory.purchase-return.create"
                        href={route('inventory.purchase-return.create')}
                        label="New Return"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white hover:bg-white/20"
                    />
                </div>
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="mb-4 max-w-xs" />
                <DataTable columns={columns} rows={returns.data} rowKey="id" emptyMessage="No purchase returns." />
                {can('inventory.purchase-return.delete') && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader><DialogTitle>Delete purchase return?</DialogTitle><DialogDescription>This will reverse stock and supplier balance.</DialogDescription></DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild><Button type="button" variant="outline" size="sm">Cancel</Button></DialogClose>
                            <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>Delete</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                )}
            </div>
        </>
    );
}
