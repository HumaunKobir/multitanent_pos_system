import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Edit, Eye, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ListDateExportBar } from '@/components/admin/list-date-export-bar';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';

export default function DamageIndex({ damages = { data: [] }, filters = {} }) {
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
                route('inventory.damage.index'),
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

        router.delete(route('inventory.damage.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (damages.from ?? 0) + i },
        {
            id: 'invoice',
            header: 'Invoice',
            render: (row) => (
                <span className="font-mono text-xs font-semibold text-primary">
                    {row.invoice_number ?? `INVD${String(row.id).padStart(8, '0')}`}
                </span>
            ),
        },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        { id: 'comment', header: 'Note', render: (row) => row.comment ?? '—' },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <AdminRowActions
                    prefix="inventory.damage"
                    id={row.id}
                    showRoute="inventory.damage.show"
                    editRoute="inventory.damage.edit"
                    onDelete={() => setDeleting(row)}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Damage" />
            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <AlertTriangle className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Damage</h1>
                            <p className="text-xs text-white/60">Record damaged stock.</p>
                        </div>
                    </div>
                    <AdminCreateLink
                        permission="inventory.damage.create"
                        href={route('inventory.damage.create')}
                        label="New Damage"
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
                        exportExcelRoute="inventory.damage.export-excel"
                        exportPdfRoute="inventory.damage.export-pdf"
                        exportPrintRoute="inventory.damage.export-print"
                        query={{ search }}
                    />
                </div>
                <DataTable columns={columns} rows={damages.data} rowKey="id" emptyMessage="No damage records." />
                {can('inventory.damage.delete') && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Delete damage record?</DialogTitle>
                            <DialogDescription>Stock will be restored if possible.</DialogDescription>
                        </DialogHeader>
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
