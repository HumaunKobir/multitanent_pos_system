import { useAppToast } from '@/contexts/app-toast-context';
import {
    DistributionDocument,
} from '@/components/inventory/distribution-show-layout';
import {
    headerActionClassName,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, Building2, Check, Edit, Trash2, Warehouse } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

export default function StockDistributionShow({ distribution, canManage = false, canReceive = false }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = distribution.invoice_number ?? `INVT${String(distribution.id).padStart(8, '0')}`;
    const actionClass = headerActionClassName();
    const isPending = distribution.status === 1 || distribution.status_label === 'Pending';

    function handleReceive() {
        router.post(route('inventory.stock-distribution.receive', distribution.id));
    }

    function handleDelete() {
        router.delete(route('inventory.stock-distribution.destroy', distribution.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`Distribution — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <InvoiceShowHeader icon={ArrowRightLeft} title="Stock Distribution" invoiceNumber={invoiceNumber}>
                    <Badge variant={isPending ? 'secondary' : 'default'} className="mr-2">
                        {distribution.status_label ?? (isPending ? 'Pending' : 'Received')}
                    </Badge>
                    {canReceive && (
                        <Button
                            size="sm"
                            onClick={handleReceive}
                            className="border border-emerald-400/50 bg-emerald-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md"
                        >
                            <Check className="size-3.5" />
                            Receive Stock
                        </Button>
                    )}
                    {canManage && isPending && (
                        <Can permission="inventory.stock-distribution.update">
                            <Button size="sm" asChild className={actionClass}>
                                <Link href={route('inventory.stock-distribution.edit', distribution.id)}>
                                    <Edit className="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                        </Can>
                    )}
                    {canManage && isPending && (
                        <Can permission="inventory.stock-distribution.delete">
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => setDeleting(true)}
                                className="border border-red-500/50 bg-red-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-md"
                            >
                                <Trash2 className="size-3.5" />
                                Delete
                            </Button>
                        </Can>
                    )}
                    <Button size="sm" asChild className={actionClass}>
                        <Link href={canReceive ? route('inventory.stock-distribution.received') : route('inventory.stock-distribution.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </InvoiceShowHeader>

                <DistributionDocument
                    docTitle="Stock Distribution"
                    invoiceNumber={invoiceNumber}
                    date={distribution.date}
                    fromBranchName={distribution.from_branch?.name ?? 'Main Branch'}
                    toBranchName={distribution.to_branch?.name}
                    items={distribution.products ?? []}
                    comment={distribution.comment}
                    branchSection={
                        <div className="mb-6 space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <PartyInfoCard
                                    icon={Warehouse}
                                    label="From Branch"
                                    name={distribution.from_branch?.name ?? 'Main Branch'}
                                    emptyText="Main Branch"
                                />
                                <PartyInfoCard
                                    icon={Building2}
                                    label="To Branch"
                                    name={distribution.to_branch?.name}
                                    emptyText="—"
                                />
                            </div>
                            {!isPending && distribution.received_by && (
                                <p className="text-xs text-muted-foreground">
                                    Received by {distribution.received_by.name}
                                    {distribution.received_at ? ` on ${new Date(distribution.received_at).toLocaleString()}` : ''}
                                </p>
                            )}
                            {distribution.purchase && (
                                <p className="text-xs text-muted-foreground">
                                    Linked purchase:{' '}
                                    <Link
                                        href={route('inventory.purchase.show', distribution.purchase.id)}
                                        className="font-medium text-primary hover:underline"
                                    >
                                        {distribution.purchase.invoice_number}
                                    </Link>
                                </p>
                            )}
                        </div>
                    }
                />

                {canManage && can('inventory.stock-distribution.delete') && (
                    <Dialog open={deleting} onOpenChange={setDeleting}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete distribution?</DialogTitle>
                                <DialogDescription>
                                    This will permanently delete the distribution, reverse accounting, and restore main branch stock.
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
            </div>
        </>
    );
}
