import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router, usePage } from '@inertiajs/react';
import { BookOpen, Eye, Pencil, Plus, Trash2, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AdminCreateButton } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import ContraVoucherModal from './contra-modal';
import ExpenseVoucherModal from './expense-modal';
import IncomeVoucherModal from './income-modal';
import JournalVoucherModal from './journal-modal';

const TYPE_LABELS = {
    journal: 'Journal Voucher',
    contra: 'Contra Voucher',
    income: 'Income Voucher',
    expense: 'Expense Voucher',
};

export default function VouchersIndex({
    vouchers = { data: [] },
    activeType = 'journal',
    accountsPicker = [],
    assetAccounts = [],
    parties = [],
    defaults = {},
    filters = {},
}) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    useDebouncedEffect(
        () =>
            router.get(
                route('accounts.vouchers.index', { query: { type: activeType, search: search || undefined } }),
                {},
                { preserveState: true, replace: true },
            ),
        [search],
        350,
        { skipFirstRun: true },
    );

    function switchType(slug) {
        router.get(route('accounts.vouchers.index', { query: { type: slug, search: search || undefined } }));
    }

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    async function openEdit(row) {
        try {
            const res = await fetch(route('accounts.vouchers.show', row.id), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            setEditing(data.voucher);
            setFormOpen(true);
        } catch {
            toast.error('Could not load voucher');
        }
    }

    async function openView(row) {
        try {
            const res = await fetch(route('accounts.vouchers.show', row.id), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            setViewing(data.voucher);
        } catch {
            toast.error('Could not load voucher');
        }
    }

    function handleDelete() {
        if (!deleting?.id) return;
        router.delete(route('accounts.vouchers.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
            preserveScroll: true,
        });
    }

    const columns = [
        { id: 'num', header: '#', render: (_, i) => (vouchers.from ?? 0) + i + 1 },
        { id: 'voucher_no', header: 'Voucher No', render: (row) => <span className="font-mono text-xs font-semibold">{row.voucher_no}</span> },
        { id: 'date', header: 'Date', render: (row) => formatBdDate(row.date) },
        {
            id: 'party',
            header: 'Party',
            render: (row) => row.party?.name ?? '—',
        },
        {
            id: 'total',
            header: 'Total',
            render: (row) => `৳${parseFloat(row.total_amount ?? 0).toLocaleString('en-BD', { minimumFractionDigits: 2 })}`,
        },
        { id: 'ref', header: 'Reference', render: (row) => <span className="font-mono text-xs">{row.transaction_reference ?? '—'}</span> },
        {
            id: 'narration',
            header: 'Narration',
            render: (row) => (
                <span className="line-clamp-1 max-w-[200px] text-muted-foreground">{row.narration ?? '—'}</span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-1">
                    {can('accounts.view') && (
                        <Button size="sm" variant="outline" className="h-7 w-7 p-0" onClick={() => openView(row)}>
                            <Eye className="size-3.5" />
                        </Button>
                    )}
                    {can('accounts.update') && (
                        <Button size="sm" variant="outline" className="h-7 w-7 p-0" onClick={() => openEdit(row)}>
                            <Pencil className="size-3.5" />
                        </Button>
                    )}
                    {can('accounts.delete') && (
                        <Button size="sm" variant="destructive" className="h-7 w-7 p-0" onClick={() => setDeleting(row)}>
                            <Trash2 className="size-3.5" />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    const modalProps = {
        open: formOpen,
        onOpenChange: setFormOpen,
        item: editing,
        accountsPicker,
        assetAccounts,
        parties,
        defaults,
    };

    return (
        <>
            <Head title={TYPE_LABELS[activeType] ?? 'Vouchers'} />

            <div className="px-2 py-1">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Wallet className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">{TYPE_LABELS[activeType] ?? 'Vouchers'}</h1>
                            <p className="text-xs text-white/70">Financial voucher records</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="accounts.create"
                        onClick={openCreate}
                        label="New Voucher"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white hover:bg-white/20"
                    />
                </div>

                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search voucher no, reference, narration…" className="mb-4 max-w-xs" />

                <DataTable columns={columns} rows={vouchers.data} rowKey="id" emptyMessage="No vouchers found." />
            </div>

            <Can permission={['accounts.create', 'accounts.update']}>
                {activeType === 'journal' && <JournalVoucherModal {...modalProps} />}
                {activeType === 'contra' && <ContraVoucherModal {...modalProps} />}
                {activeType === 'expense' && <ExpenseVoucherModal {...modalProps} />}
                {activeType === 'income' && <IncomeVoucherModal {...modalProps} />}
            </Can>

            <Dialog open={!!viewing} onOpenChange={(open) => !open && setViewing(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <BookOpen className="size-4" />
                            {viewing?.voucher_no}
                        </DialogTitle>
                        <DialogDescription>
                            {viewing?.date && formatBdDate(viewing.date)} · ৳{parseFloat(viewing?.total_amount ?? 0).toFixed(2)}
                        </DialogDescription>
                    </DialogHeader>
                    {viewing?.lines?.length > 0 && (
                        <ul className="max-h-48 space-y-1 overflow-auto text-sm">
                            {viewing.lines.map((l) => (
                                <li key={l.id} className="flex justify-between gap-2 border-b border-border/50 py-1">
                                    <span>
                                        {l.side}: {l.account_label}
                                    </span>
                                    <span className="font-mono">{parseFloat(l.amount).toFixed(2)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {viewing?.narration && <p className="text-sm text-muted-foreground">{viewing.narration}</p>}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" size="sm">
                                Close
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {can('accounts.delete') && (
            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete voucher?</DialogTitle>
                        <DialogDescription>This will reverse ledger entries for {deleting?.voucher_no}.</DialogDescription>
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
        </>
    );
}
