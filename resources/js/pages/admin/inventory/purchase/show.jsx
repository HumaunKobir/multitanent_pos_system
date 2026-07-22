import { useAppToast } from '@/contexts/app-toast-context';
import { DocumentPaymentBreakdown } from '@/components/inventory/document-payment-breakdown';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, Printer, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

function money(value) {
    return parseFloat(value ?? 0).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function DetailField({ label, children }) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
            <div className="mt-1 text-sm font-semibold text-foreground">{children}</div>
        </div>
    );
}

function lineLabel(line) {
    const name = line.product?.name ?? '—';
    const variant = line.variation?.variation_data?.label;
    const code = line.product?.code;

    return {
        name: variant ? `${name} (${variant})` : name,
        code: code || null,
    };
}

function lineSubtotal(line) {
    return parseFloat(line.unit_price ?? 0) * parseFloat(line.quantity ?? 0) - parseFloat(line.discount ?? 0);
}

export default function PurchaseShow({ purchase }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = purchase.invoice_number ?? `INVP${String(purchase.id).padStart(8, '0')}`;
    const gross = parseFloat(purchase.gross_amount ?? 0);
    const vat = parseFloat(purchase.vat ?? 0);
    const discount = parseFloat(purchase.discount ?? 0);
    const net = gross + vat - discount;
    const paid = parseFloat(purchase.paid_amount ?? 0);
    const due = parseFloat(purchase.due_amount ?? Math.max(0, net - paid));
    const lines = purchase.purchase_products ?? [];
    const status = purchase.is_fully_returned ? 'Returned' : due > 0 ? 'Due' : 'Paid';

    function handlePrint() {
        window.print();
    }

    function handleDelete() {
        router.delete(route('inventory.purchase.destroy', purchase.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`Purchase — ${invoiceNumber}`} />

            <style>{`
                @media print {
                    @page {
                        size: A4;
                        margin: 16mm;
                    }
                    html, body {
                        width: 100% !important;
                        height: auto !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        background: white !important;
                    }
                    body * { visibility: hidden !important; }
                    #purchase-print-sheet,
                    #purchase-print-sheet * { visibility: visible !important; }
                    #purchase-print-sheet {
                        position: fixed !important;
                        inset: 0 !important;
                        display: flex !important;
                        justify-content: center !important;
                        align-items: flex-start !important;
                        width: 100% !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                        background: white !important;
                        color: black !important;
                        z-index: 9999 !important;
                    }
                    #purchase-print-inner {
                        width: 100% !important;
                        max-width: 700px !important;
                        margin: 0 auto !important;
                    }
                }
            `}</style>

            <div className="px-2 py-1 print:hidden">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button variant="outline" size="sm" asChild className="h-8 gap-1.5">
                            <Link href={route('inventory.purchase.index')}>
                                <ArrowLeft className="size-3.5" />
                                Back
                            </Link>
                        </Button>
                        <h1 className="truncate text-lg font-semibold text-foreground">
                            Purchase ({invoiceNumber})
                        </h1>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {purchase.can_edit ? (
                            <Can permission="inventory.purchase.update">
                                <Button variant="outline" size="sm" asChild className="h-9 gap-1.5">
                                    <Link href={route('inventory.purchase.edit', purchase.id)}>
                                        <Edit className="size-3.5" />
                                        Edit
                                    </Link>
                                </Button>
                            </Can>
                        ) : null}
                        <Can permission="inventory.purchase.delete">
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={() => setDeleting(true)}
                                className="h-9 gap-1.5"
                            >
                                <Trash2 className="size-3.5" />
                                Delete
                            </Button>
                        </Can>
                        <Button
                            type="button"
                            size="sm"
                            onClick={handlePrint}
                            className="h-9 gap-1.5 bg-teal-600 text-white hover:bg-teal-700"
                        >
                            <Printer className="size-4" />
                            Print
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="relative border-b border-border bg-muted/40 px-6 py-8 text-center">
                        <p className="absolute left-6 top-4 font-mono text-xs text-muted-foreground">#{invoiceNumber}</p>
                        <span className="absolute right-6 top-4 rounded-md border border-border bg-card px-2.5 py-1 text-xs font-medium text-muted-foreground">
                            {status}
                        </span>
                        <p className="text-4xl font-bold tracking-tight text-foreground">{money(net)}</p>
                        <p className="mt-2 text-sm text-muted-foreground">{formatBdDate(purchase.date)}</p>
                    </div>

                    <div className="grid gap-8 px-6 py-6 sm:grid-cols-2">
                        <DetailField label="Supplier">
                            <p>{purchase.supplier?.name || '—'}</p>
                            {purchase.supplier?.company_name ? (
                                <p className="mt-0.5 text-xs font-normal text-muted-foreground">
                                    {purchase.supplier.company_name}
                                </p>
                            ) : null}
                            {purchase.supplier?.phone ? (
                                <p className="mt-0.5 text-xs font-normal text-muted-foreground">{purchase.supplier.phone}</p>
                            ) : null}
                        </DetailField>
                        <DetailField label="Branch">{purchase.branch?.name || '—'}</DetailField>
                        <DetailField label="Paid">{money(paid)}</DetailField>
                        <DetailField label="Due">{money(due)}</DetailField>
                    </div>

                    <div className="border-t border-border px-6 py-5">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Note</p>
                        <p className="mt-1 text-sm text-muted-foreground">{purchase.comment || '—'}</p>
                    </div>

                    {lines.length > 0 ? (
                        <div className="border-t border-border px-6 py-5">
                            <p className="mb-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Products
                            </p>
                            <div className="overflow-hidden rounded-lg border border-border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-semibold">Product</th>
                                            <th className="px-3 py-2 text-right font-semibold">Qty</th>
                                            <th className="px-3 py-2 text-right font-semibold">Unit price</th>
                                            <th className="px-3 py-2 text-right font-semibold">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lines.map((line) => {
                                            const label = lineLabel(line);

                                            return (
                                                <tr key={line.id} className="border-t border-border/60">
                                                    <td className="px-3 py-2">
                                                        <p>{label.name}</p>
                                                        {label.code ? (
                                                            <p className="font-mono text-[10px] text-muted-foreground">
                                                                {label.code}
                                                            </p>
                                                        ) : null}
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                        {parseFloat(line.quantity ?? 0)}
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                        {money(line.unit_price)}
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                        {money(lineSubtotal(line))}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : null}

                    <div className="grid gap-3 border-t border-border bg-muted/20 px-6 py-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div className="flex justify-between gap-3 sm:block">
                            <p className="text-xs text-muted-foreground">Gross</p>
                            <p className="font-semibold tabular-nums">{money(gross)}</p>
                        </div>
                        <div className="flex justify-between gap-3 sm:block">
                            <p className="text-xs text-muted-foreground">Discount</p>
                            <p className="font-semibold tabular-nums">{money(discount)}</p>
                        </div>
                        <div className="flex justify-between gap-3 sm:block">
                            <p className="text-xs text-muted-foreground">VAT</p>
                            <p className="font-semibold tabular-nums">{money(vat)}</p>
                        </div>
                        <div className="flex justify-between gap-3 sm:block">
                            <p className="text-xs text-muted-foreground">Net amount</p>
                            <p className="font-semibold tabular-nums">{money(net)}</p>
                        </div>
                        <div className="flex justify-between gap-3 sm:block">
                            <p className="text-xs text-muted-foreground">Paid</p>
                            <p className="font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{money(paid)}</p>
                        </div>
                        <div className="flex justify-between gap-3 sm:block">
                            <p className="text-xs text-muted-foreground">Due</p>
                            <p className="font-semibold tabular-nums">{money(due)}</p>
                        </div>
                    </div>
                </div>

                <div className="mt-4">
                    <DocumentPaymentBreakdown
                        directPayment={purchase.direct_payment}
                        directPaymentLabel="Purchase Payment"
                        partyPayments={purchase.supplier_payment_details ?? []}
                        partyPaymentLabel="Supplier Payment"
                    />
                </div>

                {can('inventory.purchase.delete') ? (
                    <Dialog open={deleting} onOpenChange={setDeleting}>
                        <DialogContent className="max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Delete purchase?</DialogTitle>
                                <DialogDescription>
                                    This will permanently delete the purchase and roll back stock changes if possible.
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
                ) : null}
            </div>

            <div id="purchase-print-sheet" className="hidden print:block">
                <div id="purchase-print-inner" className="mx-auto w-full max-w-[700px] text-black">
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-bold tracking-wide">PURCHASE INVOICE</h1>
                        <p className="mt-1 text-base font-semibold">{invoiceNumber}</p>
                    </div>

                    <div className="mb-6 space-y-1 text-sm">
                        <p>
                            <span className="font-semibold">Date:</span> {formatBdDate(purchase.date)}
                        </p>
                        <p>
                            <span className="font-semibold">Status:</span> {status}
                        </p>
                        <p>
                            <span className="font-semibold">Supplier:</span>{' '}
                            {purchase.supplier?.name || '—'}
                            {purchase.supplier?.company_name ? ` (${purchase.supplier.company_name})` : ''}
                            {purchase.supplier?.phone ? ` · ${purchase.supplier.phone}` : ''}
                        </p>
                        <p>
                            <span className="font-semibold">Branch:</span> {purchase.branch?.name || '—'}
                        </p>
                    </div>

                    <table className="mb-4 w-full border-collapse text-sm">
                        <thead>
                            <tr className="bg-neutral-100">
                                <th className="border-b border-neutral-300 px-3 py-2 text-left font-semibold">Product</th>
                                <th className="border-b border-neutral-300 px-3 py-2 text-right font-semibold">Qty</th>
                                <th className="border-b border-neutral-300 px-3 py-2 text-right font-semibold">Unit</th>
                                <th className="border-b border-neutral-300 px-3 py-2 text-right font-semibold">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line) => {
                                const label = lineLabel(line);

                                return (
                                    <tr key={`print-${line.id}`}>
                                        <td className="border-b border-neutral-200 px-3 py-2">{label.name}</td>
                                        <td className="border-b border-neutral-200 px-3 py-2 text-right font-mono">
                                            {parseFloat(line.quantity ?? 0)}
                                        </td>
                                        <td className="border-b border-neutral-200 px-3 py-2 text-right font-mono">
                                            {money(line.unit_price)}
                                        </td>
                                        <td className="border-b border-neutral-200 px-3 py-2 text-right font-mono">
                                            {money(lineSubtotal(line))}
                                        </td>
                                    </tr>
                                );
                            })}
                            <tr>
                                <td colSpan={3} className="border-t border-neutral-300 px-3 py-2 text-right">
                                    Gross
                                </td>
                                <td className="border-t border-neutral-300 px-3 py-2 text-right font-mono">{money(gross)}</td>
                            </tr>
                            <tr>
                                <td colSpan={3} className="px-3 py-1 text-right">
                                    Discount
                                </td>
                                <td className="px-3 py-1 text-right font-mono">{money(discount)}</td>
                            </tr>
                            <tr>
                                <td colSpan={3} className="px-3 py-1 text-right">
                                    VAT
                                </td>
                                <td className="px-3 py-1 text-right font-mono">{money(vat)}</td>
                            </tr>
                            <tr>
                                <td colSpan={3} className="border-t-2 border-double border-neutral-800 px-3 py-2 text-right font-semibold">
                                    Total Amount
                                </td>
                                <td className="border-t-2 border-double border-neutral-800 px-3 py-2 text-right font-mono font-semibold">
                                    {money(net)}
                                </td>
                            </tr>
                            <tr>
                                <td colSpan={3} className="px-3 py-1 text-right">
                                    Paid
                                </td>
                                <td className="px-3 py-1 text-right font-mono">{money(paid)}</td>
                            </tr>
                            <tr>
                                <td colSpan={3} className="px-3 py-1 text-right font-semibold">
                                    Due
                                </td>
                                <td className="px-3 py-1 text-right font-mono font-semibold">{money(due)}</td>
                            </tr>
                        </tbody>
                    </table>

                    <p className="mb-16 text-sm">
                        <span className="font-semibold">Note:</span> {purchase.comment || '—'}
                    </p>

                    <div className="mt-20 grid grid-cols-2 gap-16 text-center text-sm">
                        <div>
                            <div className="mb-2 border-t border-neutral-800 pt-2">Prepared By</div>
                            <p className="text-xs text-neutral-600">{purchase.created_by_name || ''}</p>
                        </div>
                        <div>
                            <div className="mb-2 border-t border-neutral-800 pt-2">Approved By</div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
