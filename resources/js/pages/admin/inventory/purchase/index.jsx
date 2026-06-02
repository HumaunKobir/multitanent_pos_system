import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Eye, HandCoins, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';

function formatBdDate(date) {
    if (!date || typeof date !== 'string') return '—';

    // We may receive `YYYY-MM-DD` or ISO (`YYYY-MM-DDTHH:mm:ss...Z`) from Laravel.
    // Parse manually to avoid timezone shifts.
    const normalized = date.includes('T') ? date.slice(0, 10) : date;
    const parts = normalized.split('-');
    if (parts.length !== 3) return date;

    const [y, m, d] = parts.map((p) => Number(p));
    if (!y || !m || !d) return date;

    const utcMidnight = new Date(Date.UTC(y, m - 1, d));

    const dtf = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Dhaka',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

    const dateParts = dtf.formatToParts(utcMidnight);
    const day = dateParts.find((p) => p.type === 'day')?.value;
    const month = dateParts.find((p) => p.type === 'month')?.value;
    const year = dateParts.find((p) => p.type === 'year')?.value;

    if (!day || !month || !year) {
        return dtf.format(utcMidnight);
    }

    return `${day} ${month}, ${year}`;
}

export default function PurchaseIndex({ purchases, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(route('inventory.purchase.index'), { search: search || undefined }, { preserveState: true, replace: true });
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('inventory.purchase.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (purchases.from ?? 0) + i },
        {
            id: 'invoice',
            header: 'Invoice',
            render: (row) => (
                <span className="font-mono text-xs font-semibold text-primary">
                    {row.invoice_number ?? `INVP${String(row.id).padStart(8, '0')}`}
                </span>
            ),
        },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        {
            id: 'supplier',
            header: 'Supplier',
            render: (row) => row.supplier?.name ?? '—',
        },
        {
            id: 'total',
            header: 'Total Amount',
            render: (row) => {
                const gross = parseFloat(row.gross_amount ?? 0);
                const vat = parseFloat(row.vat ?? 0);
                const discount = parseFloat(row.discount ?? 0);
                const total = gross + vat - discount;

                return <span className="font-medium">৳{total.toFixed(2)}</span>;
            },
        },
        {
            id: 'paid',
            header: 'Paid',
            render: (row) => (
                <span className="text-green-700 dark:text-green-400">৳{parseFloat(row.paid_amount).toFixed(2)}</span>
            ),
        },
        {
            id: 'due',
            header: 'Due',
            render: (row) => (
                <span className={parseFloat(row.due_amount) > 0 ? 'font-semibold text-destructive' : 'font-semibold text-green-700 dark:text-green-400'}>
                    ৳{parseFloat(row.due_amount).toFixed(2)}
                </span>
            ),
        },
        {
            id: 'note',
            header: 'Note',
            render: (row) => (
                <span className="block max-w-64 truncate text-muted-foreground" title={row.comment ?? ''}>
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
                    <Button size="sm" variant="outline" asChild>
                        <Link href={route('inventory.purchase.show', row.id)}>
                            <Eye className="size-3.5" />
                        </Link>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <Link href={route('inventory.purchase.edit', row.id)}>
                            <Edit className="size-3.5" />
                        </Link>
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
            <Head title="Purchases" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <HandCoins className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Purchases</h1>
                            <p className="text-xs text-white/60">Manage purchase orders.</p>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        asChild
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    >
                        <Link href={route('inventory.purchase.create')}>
                            <Plus className="size-3.5" />
                            New Purchase
                        </Link>
                    </Button>
                </div>

                <div className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by invoice or supplier…"
                        className="max-w-xs"
                    />
                </div>

                <DataTable columns={columns} rows={purchases.data} rowKey="id" emptyMessage="No purchases found." />

                <Dialog open={!!deleting} onOpenChange={(open) => (!open ? setDeleting(null) : null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Delete purchase?</DialogTitle>
                            <DialogDescription>
                                This will permanently delete the purchase and roll back stock changes if possible.
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

                {purchases.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {purchases.links.map((link, i) => (
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
