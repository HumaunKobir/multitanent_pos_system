import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { buildSellRowSummary, resolveSellEditAccess } from '@/lib/sell-summary';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Eye, Plus, Search, ShoppingCart, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

export default function SellIndex({ sells, filters }) {
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
        () => {
            router.get(route('inventory.sell.index'), { search: search || undefined }, { preserveState: true, replace: true });
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('inventory.sell.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (sells.from ?? 0) + i },
        {
            id: 'invoice',
            header: 'Invoice',
            render: (row) => (
                <span className="font-mono text-xs font-semibold text-primary">
                    {'INVS' + String(row.id).padStart(8, '0')}
                </span>
            ),
        },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        {
            id: 'customer',
            header: 'Customer',
            render: (row) => row.customer?.name ?? <span className="text-muted-foreground">Walk-in</span>,
        },
        {
            id: 'discount',
            header: 'Discount',
            render: (row) => {
                const { nonCoinDiscount, coinDiscountAmount } = buildSellRowSummary(row);

                if (nonCoinDiscount <= 0 && coinDiscountAmount <= 0) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <div className="text-xs leading-tight">
                        {nonCoinDiscount > 0 && (
                            <span className="font-medium text-green-700 dark:text-green-400">
                                -৳{nonCoinDiscount.toFixed(2)}
                            </span>
                        )}
                        {coinDiscountAmount > 0 && (
                            <span className="block text-violet-700 dark:text-violet-300">
                                Coin -৳{coinDiscountAmount.toFixed(2)}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'total',
            header: 'Net Payable',
            render: (row) => {
                const { netAmount } = buildSellRowSummary(row);

                return <span className="font-medium">৳{netAmount.toFixed(2)}</span>;
            },
        },
        {
            id: 'paid',
            header: 'Paid',
            render: (row) => {
                const { paidAmount } = buildSellRowSummary(row);

                return (
                    <span className="text-green-700 dark:text-green-400">৳{paidAmount.toFixed(2)}</span>
                );
            },
        },
        {
            id: 'due',
            header: 'Due',
            render: (row) => {
                const { dueAmount } = buildSellRowSummary(row);

                return (
                    <span className={dueAmount > 0 ? 'font-semibold text-destructive' : 'font-semibold text-green-700 dark:text-green-400'}>
                        ৳{dueAmount.toFixed(2)}
                    </span>
                );
            },
        },
        {
            id: 'note',
            header: 'Note',
            render: (row) => (
                <span className="block max-w-48 truncate text-muted-foreground" title={row.comment ?? ''}>
                    {row.comment ?? '—'}
                </span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    {can('inventory.sell.view') && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('inventory.sell.show', row.id)}>
                                <Eye className="size-3.5" />
                            </Link>
                        </Button>
                    )}
                    {can('inventory.sell.update') && resolveSellEditAccess(buildSellRowSummary(row)).canEdit && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('inventory.sell.edit', row.id)}>
                                <Edit className="size-3.5" />
                            </Link>
                        </Button>
                    )}
                    {can('inventory.sell.delete') && (
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
            <Head title="Sales" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <ShoppingCart className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Sales</h1>
                            <p className="text-xs text-white/60">Manage sale invoices.</p>
                        </div>
                    </div>
                    <Can permission="inventory.sell.create">
                        <Button
                            size="sm"
                            asChild
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        >
                            <Link href={route('inventory.sell.create')}>
                                <Plus className="size-3.5" />
                                New Sale
                            </Link>
                        </Button>
                    </Can>
                </div>

                <div className="mb-4 flex gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by invoice, customer, phone…"
                            className="pl-8"
                        />
                    </div>
                </div>

                <DataTable columns={columns} rows={sells.data} rowKey="id" emptyMessage="No sales found." />

                {can('inventory.sell.delete') && (
                <Dialog open={!!deleting} onOpenChange={(open) => (!open ? setDeleting(null) : null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Delete sale?</DialogTitle>
                            <DialogDescription>
                                This will permanently delete the sale and restore stock.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="mt-4 gap-2">
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

                {sells.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {sells.links.map((link, i) => (
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
        </>
    );
}
