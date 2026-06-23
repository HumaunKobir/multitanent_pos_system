import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Megaphone, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import { route } from '@/lib/route';
import PromotionFormDialog from './form-dialog';

const routes = {
    index: (query) => route('setting.promotion.index', query ? { query } : undefined),
    store: route('setting.promotion.store'),
    update: (id) => route('setting.promotion.update', { promotion: id }),
    destroy: (id) => route('setting.promotion.destroy', { promotion: id }),
};
const PERM = 'setting.promotion';

export default function PromotionIndex({ promotions, filters, promotionScopes, promotionTypes, catalogOptions }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);

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
            render: (_, i) => (promotions.from ?? 0) + i,
        },
        { header: 'Name', accessorKey: 'name' },
        { header: 'Scope', accessorKey: 'scope_label' },
        { header: 'Type', accessorKey: 'type_label' },
        { header: 'Discount', accessorKey: 'discount_summary' },
        { header: 'Usage', accessorKey: 'usage_count' },
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
                <AdminInlineActions prefix={PERM} onEdit={() => { setEditing(row); setFormOpen(true); }} onDelete={() => setDeleting(row)} />
            ),
        },
    ];

    return (
        <>
            <Head title="Promotions" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Megaphone className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Promotions</h1>
                            <p className="text-xs text-white/60">Branch-wise promotions for category, brand, or product targets.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission={`${PERM}.create`}
                        onClick={() => { setEditing(null); setFormOpen(true); }}
                        label="Add New"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name..."
                        className="max-w-xs"
                    />
                </div>

                <DataTable columns={columns} rows={promotions.data} rowKey="id" emptyMessage="No promotions found." />

                {promotions.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {promotions.links.map((link, i) => (
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

            {can(`${PERM}.delete`) && (
                <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent className="p-0">
                        <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                                <Trash2 className="size-3.5 text-white" />
                            </div>
                            <h2 className="text-sm font-semibold text-white">Delete Promotion</h2>
                        </div>
                        <div className="px-5 pt-4 pb-5">
                            <p className="text-sm text-muted-foreground">
                                Are you sure you want to delete <strong>{deleting?.name}</strong>?
                            </p>
                            <DialogFooter className="mt-4">
                                <DialogClose asChild>
                                    <Button variant="outline" size="sm">Cancel</Button>
                                </DialogClose>
                                <Button size="sm" className="bg-red-600 text-white" onClick={handleDelete}>
                                    Delete
                                </Button>
                            </DialogFooter>
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            <Can permission={[`${PERM}.create`, `${PERM}.update`]}>
                <PromotionFormDialog
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    item={editing}
                    routes={routes}
                    promotionScopes={promotionScopes}
                    promotionTypes={promotionTypes}
                    catalogOptions={catalogOptions}
                />
            </Can>
        </>
    );
}
