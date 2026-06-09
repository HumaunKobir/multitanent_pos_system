import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useAppToast } from '@/contexts/app-toast-context';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { settingRoutes } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { GripVertical, HelpCircle, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import FaqFormDialog from './form-dialog';

const routes = settingRoutes('faq');
const PERM = 'setting.faq';

export default function FaqIndex({ faqs, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const [rows, setRows] = useState(faqs.data ?? []);
    const [dragIndex, setDragIndex] = useState(null);
    const [overIndex, setOverIndex] = useState(null);
    const [savingOrder, setSavingOrder] = useState(false);

    const canReorder = can(`${PERM}.update`) && !search;

    useEffect(() => {
        setRows(faqs.data ?? []);
    }, [faqs.data]);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
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
        if (!deleting) {
            return;
        }

        router.delete(routes.destroy(deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(row) {
        setEditing(row);
        setFormOpen(true);
    }

    function saveOrder(orderedRows) {
        setSavingOrder(true);

        router.post(
            routes.updateOrder(),
            {
                orders: orderedRows.map((row, index) => ({
                    id: row.id,
                    sort_order: index + 1,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('FAQ order updated successfully.'),
                onFinish: () => setSavingOrder(false),
            },
        );
    }

    function handleDrop(targetIndex) {
        if (dragIndex === null || dragIndex === targetIndex) {
            setDragIndex(null);
            setOverIndex(null);

            return;
        }

        const next = [...rows];
        const [moved] = next.splice(dragIndex, 1);
        next.splice(targetIndex, 0, moved);

        setRows(next);
        setDragIndex(null);
        setOverIndex(null);
        saveOrder(next);
    }

    const columns = [
        {
            id: 'drag',
            header: '',
            cellClassName: 'w-10',
            render: (row, index) =>
                canReorder ? (
                    <button
                        type="button"
                        draggable
                        aria-label={`Reorder ${row.question}`}
                        className={cn(
                            'flex size-8 cursor-grab items-center justify-center rounded-md text-muted-foreground transition-colors',
                            'hover:bg-accent hover:text-foreground active:cursor-grabbing',
                            dragIndex === index && 'cursor-grabbing text-foreground',
                        )}
                        onDragStart={() => setDragIndex(index)}
                        onDragEnd={() => {
                            setDragIndex(null);
                            setOverIndex(null);
                        }}
                    >
                        <GripVertical className="size-4" />
                    </button>
                ) : (
                    <span className="inline-flex size-8 items-center justify-center text-muted-foreground/40">
                        <GripVertical className="size-4" />
                    </span>
                ),
        },
        {
            id: 'num',
            header: '#',
            render: (_, index) => index + 1,
        },
        {
            header: 'Question',
            accessorKey: 'question',
        },
        {
            id: 'status',
            header: 'Status',
            render: (row) => (
                <Badge className={row.status ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-600 text-white hover:bg-red-700'}>
                    {row.status ? 'Active' : 'InActive'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <AdminInlineActions prefix={PERM} onEdit={() => openEdit(row)} onDelete={() => setDeleting(row)} />
            ),
        },
    ];

    return (
        <>
            <Head title="FAQ" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <HelpCircle className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">FAQ</h1>
                            <p className="text-xs text-white/60">
                                Manage frequently asked questions shown on the storefront.
                                {canReorder ? ' Drag rows to reorder.' : ''}
                            </p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission={`${PERM}.create`}
                        onClick={openCreate}
                        label="Add New"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search by question..."
                        className="max-w-xs"
                    />
                    {search && can(`${PERM}.update`) && (
                        <p className="text-xs text-muted-foreground">Clear search to reorder FAQs.</p>
                    )}
                    {savingOrder && <p className="text-xs text-muted-foreground">Saving order...</p>}
                </div>

                <DataTable
                    columns={columns}
                    rows={rows}
                    rowKey="id"
                    emptyMessage="No FAQ items found."
                    getRowProps={(_, index) =>
                        canReorder
                            ? {
                                  onDragOver: (event) => {
                                      event.preventDefault();
                                      setOverIndex(index);
                                  },
                                  onDragLeave: () => {
                                      if (overIndex === index) {
                                          setOverIndex(null);
                                      }
                                  },
                                  onDrop: (event) => {
                                      event.preventDefault();
                                      handleDrop(index);
                                  },
                                  className: cn(
                                      dragIndex === index && 'opacity-50',
                                      overIndex === index && dragIndex !== null && dragIndex !== index && 'bg-blue-950/10',
                                  ),
                              }
                            : {}
                    }
                />

                {faqs.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {faqs.links.map((link, index) => (
                            <Link
                                key={index}
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

            {can(`${PERM}.delete`) && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="p-0">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Trash2 className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Delete FAQ</h2>
                        </div>
                        <div className="px-5 pb-5 pt-4">
                            <p className="text-sm text-muted-foreground">
                                Are you sure you want to delete <strong>{deleting?.question}</strong>? This action cannot be undone.
                            </p>
                            <DialogFooter className="mt-4">
                                <DialogClose asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    size="sm"
                                    className="bg-red-600 text-white shadow-sm shadow-red-500/30 transition-all duration-150 hover:bg-red-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-red-500/50"
                                    onClick={handleDelete}
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            <Can permission={[`${PERM}.create`, `${PERM}.update`]}>
                <FaqFormDialog open={formOpen} onOpenChange={setFormOpen} item={editing} routes={routes} />
            </Can>
        </>
    );
}
