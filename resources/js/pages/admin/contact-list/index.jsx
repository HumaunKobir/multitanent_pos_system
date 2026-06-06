import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, MessageSquare, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

const routes = {
    index: (query) => route('contact-list.index', query ? { query } : undefined),
    destroy: (id) => route('contact-list.destroy', { contact: id }),
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

function truncate(text, length = 60) {
    if (!text) {
        return '—';
    }

    return text.length > length ? `${text.slice(0, length)}…` : text;
}

export default function ContactListIndex({ contacts, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [viewing, setViewing] = useState(null);

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
            render: (_, i) => (contacts.from ?? 0) + i,
        },
        { header: 'Name', accessorKey: 'name' },
        {
            id: 'email',
            header: 'Email',
            render: (row) => row.email || <span className="text-xs text-muted-foreground">—</span>,
        },
        {
            id: 'phone',
            header: 'Phone',
            render: (row) => row.phone || <span className="text-xs text-muted-foreground">—</span>,
        },
        {
            id: 'message',
            header: 'Message',
            render: (row) => (
                <span className="line-clamp-2 max-w-xs text-sm text-muted-foreground" title={row.message}>
                    {truncate(row.message, 80)}
                </span>
            ),
        },
        {
            id: 'created_at',
            header: 'Received',
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
                        onClick={() => setViewing(row)}
                        aria-label={`View message from ${row.name}`}
                        className="inline-flex size-6 items-center justify-center rounded-none border border-blue-200 bg-blue-50 text-blue-600 transition-all duration-200 hover:-translate-y-1 hover:border-blue-500 hover:bg-blue-600 hover:text-white hover:shadow-sm hover:shadow-blue-500/35"
                    >
                        <Eye className="size-2.5" strokeWidth={2.5} />
                    </button>
                    <button
                        type="button"
                        onClick={() => setDeleting(row)}
                        aria-label={`Delete message from ${row.name}`}
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
            <Head title="Contact Messages" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <MessageSquare className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Contact Messages</h1>
                            <p className="text-xs text-white/60">Messages submitted from the store contact form.</p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, email, phone, or message..."
                        className="max-w-sm"
                    />
                </div>

                <DataTable columns={columns} rows={contacts.data} rowKey="id" emptyMessage="No messages found." />

                {contacts.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {contacts.links.map((link, i) => (
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

            <Dialog open={!!viewing} onOpenChange={(open) => !open && setViewing(null)}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <MessageSquare className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Message Details</h2>
                    </div>
                    <div className="space-y-3 px-5 pb-5 pt-4 text-sm">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Name</p>
                            <p className="mt-0.5 font-medium">{viewing?.name}</p>
                        </div>
                        {viewing?.email && (
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</p>
                                <p className="mt-0.5">{viewing.email}</p>
                            </div>
                        )}
                        {viewing?.phone && (
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Phone</p>
                                <p className="mt-0.5">{viewing.phone}</p>
                            </div>
                        )}
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Received</p>
                            <p className="mt-0.5">{viewing?.created_at ? formatDate(viewing.created_at) : '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Message</p>
                            <p className="mt-1 whitespace-pre-wrap rounded-md border bg-muted/30 p-3 leading-relaxed">
                                {viewing?.message}
                            </p>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="p-0">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Trash2 className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Delete Message</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete the message from <strong>{deleting?.name}</strong>? This
                            action cannot be undone.
                        </p>
                        <DialogFooter className="mt-4">
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" onClick={handleDelete}>
                                Delete
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
