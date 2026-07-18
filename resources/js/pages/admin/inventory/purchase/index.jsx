import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Eye, HandCoins, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ListDateExportBar } from '@/components/admin/list-date-export-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

export default function PurchaseIndex({ purchases, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                route('inventory.purchase.index'),
                {
                    search: search || undefined,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                },
                { preserveState: true, replace: true },
            );
        },
        [search, dateFrom, dateTo],
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
                <div className="flex items-center gap-2">
                    <span className="font-mono text-xs font-semibold text-primary">
                        {row.invoice_number ?? `INVP${String(row.id).padStart(8, '0')}`}
                    </span>
                    {row.has_return && (
                        <Badge variant="outline" className="gap-1 border-sky-300 bg-sky-50 text-[10px] text-sky-800">
                            <RotateCcw className="size-3" />
                            Returned
                            {row.return_invoice_number ? ` · ${row.return_invoice_number}` : ''}
                        </Badge>
                    )}
                </div>
            ),
        },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        {
            id: 'supplier',
            header: 'Supplier',
            render: (row) => {
                const supplier = row.supplier;

                if (!supplier) {
                    return '—';
                }

                if (supplier.company_name) {
                    return (
                        <div>
                            <p className="font-medium">{supplier.company_name}</p>
                            <p className="text-xs text-muted-foreground">{supplier.name}</p>
                        </div>
                    );
                }

                return supplier.name;
            },
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
                <AdminRowActions
                    prefix="inventory.purchase"
                    id={row.id}
                    showRoute="inventory.purchase.show"
                    editRoute={row.can_edit ? 'inventory.purchase.edit' : undefined}
                    onDelete={() => setDeleting(row)}
                />
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
                    <AdminCreateLink
                        permission="inventory.purchase.create"
                        href={route('inventory.purchase.create')}
                        label="New Purchase"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by invoice, supplier, phone…"
                        className="max-w-xs"
                    />
                    <ListDateExportBar
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onDateFromChange={setDateFrom}
                        onDateToChange={setDateTo}
                        exportExcelRoute="inventory.purchase.export-excel"
                        exportPdfRoute="inventory.purchase.export-pdf"
                        exportPrintRoute="inventory.purchase.export-print"
                        query={{ search }}
                    />
                </div>

                <DataTable columns={columns} rows={purchases.data} rowKey="id" emptyMessage="No purchases found." />

                {can('inventory.purchase.delete') && (
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
                )}

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
