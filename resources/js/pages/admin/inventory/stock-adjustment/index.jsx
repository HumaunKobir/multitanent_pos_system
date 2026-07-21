import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Scale } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ListDateExportBar } from '@/components/admin/list-date-export-bar';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

export default function StockAdjustmentIndex({ adjustments = { data: [] }, filters = {} }) {
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
        () =>
            router.get(
                route('inventory.stock-adjustment.index'),
                {
                    search: search || undefined,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                },
                { preserveState: true, replace: true },
            ),
        [search, dateFrom, dateTo],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting?.id) {
            return;
        }

        router.delete(route('inventory.stock-adjustment.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (adjustments.from ?? 0) + i },
        {
            id: 'invoice',
            header: 'Invoice',
            render: (row) => (
                <span className="font-mono text-xs font-semibold text-primary">
                    {row.invoice_number ?? `INVA${String(row.id).padStart(8, '0')}`}
                </span>
            ),
        },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        {
            id: 'type',
            header: 'Type',
            render: (row) => {
                const type = typeof row.type === 'object' ? row.type?.value ?? row.type : row.type;
                const isIncrease = type === 'increase';

                return (
                    <span
                        className={
                            isIncrease
                                ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                : 'font-medium text-rose-700 dark:text-rose-400'
                        }
                    >
                        {isIncrease ? 'Increase' : 'Decrease'}
                    </span>
                );
            },
        },
        { id: 'comment', header: 'Note', render: (row) => row.comment ?? '—' },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <AdminRowActions
                    prefix="inventory.stock-adjustment"
                    id={row.id}
                    showRoute="inventory.stock-adjustment.show"
                    onDelete={() => setDeleting(row)}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Stock Adjustment" />
            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Scale className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Stock Adjustment</h1>
                            <p className="text-xs text-white/60">Increase or decrease inventory stock.</p>
                        </div>
                    </div>
                    <AdminCreateLink
                        permission="inventory.stock-adjustment.create"
                        href={route('inventory.stock-adjustment.create')}
                        label="New Adjustment"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white hover:bg-white/20"
                    />
                </div>
                <div className="mb-4 flex flex-wrap items-end gap-2">
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="max-w-xs" />
                    <ListDateExportBar
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onDateFromChange={setDateFrom}
                        onDateToChange={setDateTo}
                        exportExcelRoute="inventory.stock-adjustment.export-excel"
                        exportPdfRoute="inventory.stock-adjustment.export-pdf"
                        exportPrintRoute="inventory.stock-adjustment.export-print"
                        query={{ search }}
                    />
                </div>
                <DataTable columns={columns} rows={adjustments.data} rowKey="id" emptyMessage="No stock adjustments." />
                {can('inventory.stock-adjustment.delete') && (
                    <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete stock adjustment?</DialogTitle>
                                <DialogDescription>Stock will be reversed if possible.</DialogDescription>
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
