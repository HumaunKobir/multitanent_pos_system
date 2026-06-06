import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Mail, Trash2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

const routes = {
    index: (query) => route('subscriber-list.index', query ? { query } : undefined),
    destroy: (id) => route('subscriber-list.destroy', { subscriber: id }),
};

function formatDate(value) {
    if (!value) {
        return <span className="text-xs text-muted-foreground">—</span>;
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StatusBadge({ active }) {
    return (
        <span
            className={[
                'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                active ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground',
            ].join(' ')}
        >
            {active ? 'Active' : 'Inactive'}
        </span>
    );
}

export default function SubscriberListIndex({ subscribers, filters }) {
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
            router.get(routes.index({ search: search || undefined }), { preserveState: true, replace: true });
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting) return;
        router.delete(routes.destroy(deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (subscribers.from ?? 0) + i,
        },
        { header: 'Email', accessorKey: 'email' },
        {
            id: 'status',
            header: 'Status',
            render: (row) => <StatusBadge active={row.status} />,
        },
        {
            id: 'created_at',
            header: 'Subscribed',
            render: (row) => formatDate(row.created_at),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-1">
                    <button
                        type="button"
                        onClick={() => setDeleting(row)}
                        aria-label={`Remove subscriber ${row.email}`}
                        className="inline-flex size-6 items-center justify-center rounded-none border border-red-200 bg-red-50 text-red-600 transition-all duration-200 hover:-translate-y-1 hover:border-red-500 hover:bg-red-600 hover:text-white hover:shadow-sm hover:shadow-red-500/35"
                    >
                        <Trash2 className="size-2.5" strokeWidth={2.5} />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Newsletter Subscribers" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Users className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Newsletter Subscribers</h1>
                            <p className="text-xs text-white/60">Emails collected from the footer newsletter form.</p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by email..."
                        className="max-w-sm"
                    />
                </div>

                <DataTable columns={columns} rows={subscribers.data} rowKey="id" emptyMessage="No subscribers found." />

                {subscribers.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {subscribers.links.map((link, i) => (
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

            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="p-0">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Mail className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Remove Subscriber</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to remove <strong>{deleting?.email}</strong> from the newsletter list?
                            This action cannot be undone.
                        </p>
                        <DialogFooter className="mt-4">
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" onClick={handleDelete}>
                                Remove
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
