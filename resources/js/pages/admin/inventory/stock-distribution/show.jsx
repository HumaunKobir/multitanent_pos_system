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
import { useEffect, useMemo, useState } from 'react';

import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';

function statusHeaderBadgeClassName(status, statusLabel) {
    if (status === 2 || statusLabel === 'Received') {
        return 'border-transparent bg-emerald-600 text-white';
    }

    if (status === 3 || statusLabel === 'Partially Received') {
        return 'border-transparent bg-amber-500 text-white';
    }

    return 'border-white/30 bg-white/10 text-white';
}

export default function StockDistributionShow({ distribution, canManage = false, canReceive = false }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);
    const [selectedLineIds, setSelectedLineIds] = useState([]);

    const pendingLines = useMemo(
        () => (distribution.products ?? []).filter((line) => !line.is_received),
        [distribution.products],
    );

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = distribution.invoice_number ?? `INVT${String(distribution.id).padStart(8, '0')}`;
    const actionClass = headerActionClassName();
    const isFullyPending = distribution.status === 1 || distribution.status_label === 'Pending';
    const canEditOrDelete = canManage && isFullyPending && (distribution.received_count ?? 0) === 0;

    function toggleLine(lineId) {
        setSelectedLineIds((prev) =>
            prev.includes(lineId) ? prev.filter((id) => id !== lineId) : [...prev, lineId],
        );
    }

    function toggleAllPending(checked) {
        if (checked) {
            setSelectedLineIds(pendingLines.map((line) => line.id));
            return;
        }

        setSelectedLineIds([]);
    }

    function handleReceive(lineIds = selectedLineIds) {
        if (lineIds.length === 0) {
            toast.error('Select at least one pending product to receive.');
            return;
        }

        router.post(route('inventory.stock-distribution.receive', distribution.id), {
            line_ids: lineIds,
        });
    }

    function handleDelete() {
        router.delete(route('inventory.stock-distribution.destroy', distribution.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    const selectionColumn = canReceive
        ? {
              id: 'select',
              header: (
                  <Checkbox
                      checked={pendingLines.length > 0 && selectedLineIds.length === pendingLines.length}
                      onCheckedChange={(checked) => toggleAllPending(Boolean(checked))}
                      aria-label="Select all pending products"
                  />
              ),
              render: (row) =>
                  !row.is_received ? (
                      <Checkbox
                          checked={selectedLineIds.includes(row.id)}
                          onCheckedChange={() => toggleLine(row.id)}
                          aria-label={`Select ${row.product?.name ?? 'product'}`}
                      />
                  ) : null,
          }
        : null;

    return (
        <>
            <Head title={`Distribution — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <InvoiceShowHeader icon={ArrowRightLeft} title="Stock Distribution" invoiceNumber={invoiceNumber}>
                    <Badge className={cn('mr-2', statusHeaderBadgeClassName(distribution.status, distribution.status_label))}>
                        {distribution.status_label ?? 'Pending'}
                    </Badge>
                    {canReceive && (
                        <>
                            <Button
                                size="sm"
                                onClick={() => handleReceive()}
                                disabled={selectedLineIds.length === 0}
                                className="border border-emerald-400/50 bg-emerald-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md disabled:opacity-50"
                            >
                                <Check className="size-3.5" />
                                Receive Selected
                            </Button>
                            <Button
                                size="sm"
                                onClick={() => handleReceive(pendingLines.map((line) => line.id))}
                                disabled={pendingLines.length === 0}
                                className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-white/20 hover:shadow-md disabled:opacity-50"
                            >
                                <Check className="size-3.5" />
                                Receive All
                            </Button>
                        </>
                    )}
                    {canEditOrDelete && (
                        <Can permission="inventory.stock-distribution.update">
                            <Button size="sm" asChild className={actionClass}>
                                <Link href={route('inventory.stock-distribution.edit', distribution.id)}>
                                    <Edit className="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                        </Can>
                    )}
                    {canEditOrDelete && (
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
                    receivedCount={distribution.received_count ?? 0}
                    pendingCount={distribution.pending_count ?? 0}
                    selectionColumn={selectionColumn}
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
                            {(distribution.received_count ?? 0) > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    {distribution.received_count} of {distribution.total_count} products received
                                    {distribution.status_label === 'Received' && distribution.received_by
                                        ? ` — last received by ${distribution.received_by.name}`
                                        : ''}
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

                {canEditOrDelete && can('inventory.stock-distribution.delete') && (
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
