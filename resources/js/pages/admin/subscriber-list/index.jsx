import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Mail, Send, Trash2, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

const routes = {
    index: (query) => route('subscriber-list.index', query ? { query } : undefined),
    destroy: (id) => route('subscriber-list.destroy', { subscriber: id }),
    sendMail: (id) => route('subscriber-list.send-mail', { subscriber: id }),
    sendBulkMail: () => route('subscriber-list.send-bulk-mail'),
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

export default function SubscriberListIndex({ subscribers, filters, mailConfigured, activeSubscriberCount }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [selectedIds, setSelectedIds] = useState([]);
    const [mailDialog, setMailDialog] = useState(null);

    const mailForm = useForm({
        subject: '',
        body: '',
        all_active: false,
    });

    const pageIds = useMemo(() => subscribers.data.map((row) => row.id), [subscribers.data]);
    const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));

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

    function toggleSelectAllOnPage(checked) {
        if (checked) {
            setSelectedIds((current) => [...new Set([...current, ...pageIds])]);
            return;
        }

        setSelectedIds((current) => current.filter((id) => !pageIds.includes(id)));
    }

    function toggleRowSelection(id, checked) {
        setSelectedIds((current) => (checked ? [...new Set([...current, id])] : current.filter((value) => value !== id)));
    }

    function openSingleMailDialog(subscriber) {
        mailForm.reset();
        mailForm.clearErrors();
        setMailDialog({ mode: 'single', subscriber });
    }

    function openBulkMailDialog() {
        mailForm.reset();
        mailForm.clearErrors();
        setMailDialog({ mode: 'bulk' });
    }

    function closeMailDialog() {
        setMailDialog(null);
        mailForm.reset();
        mailForm.clearErrors();
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(routes.destroy(deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    function handleSendMail(event) {
        event.preventDefault();

        if (!mailDialog) return;

        if (mailDialog.mode === 'single') {
            mailForm.transform((data) => ({
                subject: data.subject,
                body: data.body,
            }));

            mailForm.post(routes.sendMail(mailDialog.subscriber.id), {
                preserveScroll: true,
                onSuccess: () => closeMailDialog(),
            });

            return;
        }

        const allActive = mailForm.data.all_active;

        mailForm.transform((data) => ({
            subject: data.subject,
            body: data.body,
            all_active: allActive,
            subscriber_ids: allActive ? undefined : selectedIds,
        }));

        mailForm.post(routes.sendBulkMail(), {
            preserveScroll: true,
            onSuccess: () => {
                closeMailDialog();
                setSelectedIds([]);
            },
        });
    }

    const bulkRecipientCount = mailForm.data.all_active ? activeSubscriberCount : selectedIds.length;

    const columns = [
        {
            id: 'select',
            header: (
                <Checkbox
                    checked={allPageSelected}
                    onCheckedChange={(checked) => toggleSelectAllOnPage(checked === true)}
                    aria-label="Select all subscribers on this page"
                />
            ),
            render: (row) => (
                <Checkbox
                    checked={selectedIds.includes(row.id)}
                    onCheckedChange={(checked) => toggleRowSelection(row.id, checked === true)}
                    aria-label={`Select ${row.email}`}
                />
            ),
            headerClassName: 'w-10',
            cellClassName: 'w-10',
        },
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
                        onClick={() => openSingleMailDialog(row)}
                        disabled={!row.status || !mailConfigured}
                        aria-label={`Send email to ${row.email}`}
                        title={!mailConfigured ? 'Configure SMTP in Website Settings first' : 'Send email'}
                        className="inline-flex size-6 items-center justify-center rounded-none border border-blue-200 bg-blue-50 text-blue-600 transition-all duration-200 hover:-translate-y-1 hover:border-blue-500 hover:bg-blue-600 hover:text-white hover:shadow-sm hover:shadow-blue-500/35 disabled:pointer-events-none disabled:opacity-40"
                    >
                        <Mail className="size-2.5" strokeWidth={2.5} />
                    </button>
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
                    <Button
                        type="button"
                        size="sm"
                        onClick={openBulkMailDialog}
                        disabled={!mailConfigured || activeSubscriberCount === 0}
                        className="gap-1.5 bg-white/15 text-white hover:bg-white/25"
                    >
                        <Send className="size-3.5" />
                        Bulk Mail
                        {selectedIds.length > 0 ? ` (${selectedIds.length})` : ''}
                    </Button>
                </div>

                {!mailConfigured && (
                    <div className="mb-4 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                        <p>
                            SMTP mail is not configured. Set up mail credentials in{' '}
                            <Link href={route('setting.website.edit')} className="font-medium underline underline-offset-2">
                                Website Settings
                            </Link>{' '}
                            before sending emails.
                        </p>
                    </div>
                )}

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

            <Dialog open={!!mailDialog} onOpenChange={(open) => !open && closeMailDialog()}>
                <DialogContent className="gap-0 p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Mail className="size-3.5 text-white" />
                        </div>
                        <div>
                            <h2 className="text-sm font-semibold text-white">
                                {mailDialog?.mode === 'single' ? 'Send Email' : 'Bulk Email'}
                            </h2>
                            <p className="text-xs text-white/60">
                                {mailDialog?.mode === 'single'
                                    ? mailDialog.subscriber.email
                                    : `${bulkRecipientCount} active recipient(s)`}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleSendMail} className="space-y-4 px-5 pb-5 pt-4">
                        {mailDialog?.mode === 'bulk' && (
                            <label className="flex cursor-pointer items-center gap-2 rounded-md border bg-muted/30 px-3 py-2.5 text-sm">
                                <Checkbox
                                    checked={mailForm.data.all_active}
                                    onCheckedChange={(checked) => mailForm.setData('all_active', checked === true)}
                                />
                                <span>
                                    Send to all active subscribers
                                    <span className="ml-1 text-muted-foreground">({activeSubscriberCount})</span>
                                </span>
                            </label>
                        )}

                        <div className="space-y-1.5">
                            <Label htmlFor="mail-subject">Subject</Label>
                            <Input
                                id="mail-subject"
                                value={mailForm.data.subject}
                                onChange={(e) => mailForm.setData('subject', e.target.value)}
                                placeholder="Email subject..."
                                aria-invalid={!!mailForm.errors.subject}
                            />
                            {mailForm.errors.subject && (
                                <p className="text-xs text-destructive">{mailForm.errors.subject}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="mail-body">Message</Label>
                            <Textarea
                                id="mail-body"
                                value={mailForm.data.body}
                                onChange={(e) => mailForm.setData('body', e.target.value)}
                                placeholder="Write your newsletter message..."
                                rows={8}
                                className="min-h-[160px] resize-y"
                                aria-invalid={!!mailForm.errors.body}
                            />
                            {mailForm.errors.body && <p className="text-xs text-destructive">{mailForm.errors.body}</p>}
                            {mailForm.errors.subscriber_ids && (
                                <p className="text-xs text-destructive">{mailForm.errors.subscriber_ids}</p>
                            )}
                        </div>

                        <DialogFooter className="pt-1">
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                disabled={
                                    mailForm.processing ||
                                    (mailDialog?.mode === 'bulk' &&
                                        !mailForm.data.all_active &&
                                        selectedIds.length === 0)
                                }
                                className="gap-1.5"
                            >
                                {mailForm.processing ? 'Sending...' : 'Send Email'}
                                {!mailForm.processing && <Send className="size-3.5" />}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

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
