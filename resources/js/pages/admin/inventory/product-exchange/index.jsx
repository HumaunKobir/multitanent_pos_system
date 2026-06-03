import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftRight, Edit, Eye, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';

export default function ProductExchangeIndex({ exchanges = { data: [] }, filters = {} }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => router.get(route('inventory.product-exchange.index'), { search: search || undefined }, { preserveState: true, replace: true }),
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting?.id) {
            return;
        }

        router.delete(route('inventory.product-exchange.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (exchanges.from ?? 0) + i },
        { id: 'invoice', header: 'Invoice', render: (row) => <span className="font-mono text-xs font-semibold text-primary">{row.invoice_number ?? `INVX${String(row.id).padStart(8, '0')}`}</span> },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        { id: 'customer', header: 'Customer', render: (row) => row.customer?.name ?? '—' },
        { id: 'diff', header: 'Price Diff', render: (row) => `৳${parseFloat(row.price_difference ?? 0).toFixed(2)}` },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" asChild><Link href={route('inventory.product-exchange.show', row.id)}><Eye className="size-3.5" /></Link></Button>
                    <Button size="sm" variant="outline" asChild><Link href={route('inventory.product-exchange.edit', row.id)}><Edit className="size-3.5" /></Link></Button>
                    <Button size="sm" variant="destructive" onClick={() => setDeleting(row)}><Trash2 className="size-3.5" /></Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Product Exchange" />
            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15"><ArrowLeftRight className="size-4 text-white" /></div>
                        <div><h1 className="text-base font-semibold text-white">Product Exchange</h1></div>
                    </div>
                    <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white hover:bg-white/20">
                        <Link href={route('inventory.product-exchange.create')}><Plus className="size-3.5" />New Exchange</Link>
                    </Button>
                </div>
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="mb-4 max-w-xs" />
                <DataTable columns={columns} rows={exchanges.data} rowKey="id" emptyMessage="No exchanges." />
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader><DialogTitle>Delete exchange?</DialogTitle><DialogDescription>This will reverse all stock movements.</DialogDescription></DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild><Button type="button" variant="outline" size="sm">Cancel</Button></DialogClose>
                            <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>Delete</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
