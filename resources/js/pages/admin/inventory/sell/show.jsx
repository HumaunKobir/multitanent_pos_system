import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { buildSellPosPrintPayload, posPrint } from '@/lib/pos-print';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, Printer, Receipt, ShoppingCart, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';

export default function SellShow({ sell }) {
    const { flash, logo, siteName } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [deleting, setDeleting] = useState(false);
    const autoPrintedRef = useRef(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = sell.invoice_number ?? 'INVS' + String(sell.id).padStart(8, '0');
    const gross = parseFloat(sell.gross_amount ?? 0);
    const vat = parseFloat(sell.vat ?? 0);
    const discount = parseFloat(sell.discount ?? 0);
    const net = gross + vat - discount;
    const paid = parseFloat(sell.paid_amount ?? 0);
    const due = Math.max(0, net - paid);

    const handlePosPrint = useCallback(() => {
        posPrint(
            buildSellPosPrintPayload(sell, {
                companyName: siteName || 'Coolness Point',
                logoUrl: logo,
                branchName: sell.branch?.name,
            }),
        );
    }, [sell, siteName, logo]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const shouldAutoPrint = params.get('pos_print') === '1' || params.get('pos_print') === 'true';

        if (!shouldAutoPrint || autoPrintedRef.current) {
            return;
        }

        autoPrintedRef.current = true;
        const timer = setTimeout(() => handlePosPrint(), 500);

        return () => clearTimeout(timer);
    }, [handlePosPrint]);

    function handleDelete() {
        router.delete(route('inventory.sell.destroy', sell.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`Sale — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3 print:hidden">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <ShoppingCart className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Sale Invoice</h1>
                            <p className="font-mono text-xs text-white/60">{invoiceNumber}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            onClick={() => window.print()}
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        >
                            <Printer className="size-3.5" />
                            Normal Print
                        </Button>
                        <Button
                            size="sm"
                            onClick={handlePosPrint}
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        >
                            <Receipt className="size-3.5" />
                            POS Print
                        </Button>
                        <Can permission="inventory.sell.update">
                            <Button
                                size="sm"
                                asChild
                                className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                            >
                                <Link href={route('inventory.sell.edit', sell.id)}>
                                    <Edit className="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                        </Can>
                        <Can permission="inventory.sell.delete">
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
                        <Button
                            size="sm"
                            asChild
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        >
                            <Link href={route('inventory.sell.index')}>
                                <ArrowLeft className="size-3.5" />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="border border-border p-6 print:border-0 print:p-0">
                    <div className="mb-6 flex justify-between">
                        <div>
                            {logo ? (
                                <img src={logo} alt={siteName || 'Coolness Point'} className="mb-2 h-12 object-contain" />
                            ) : null}
                            <h2 className="text-xl font-bold">{siteName || 'Coolness Point'}</h2>
                            {sell.branch?.name && <p className="text-sm text-muted-foreground">{sell.branch.name}</p>}
                            <p className="text-sm text-muted-foreground">Sale Invoice</p>
                        </div>
                        <div className="text-right text-sm">
                            <p className="font-mono font-bold">{invoiceNumber}</p>
                            <p className="text-muted-foreground">Date: {sell.date}</p>
                        </div>
                    </div>

                    {sell.customer ? (
                        <div className="mb-6 border border-border p-3">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Customer</p>
                            <p className="font-semibold">{sell.customer.name}</p>
                            {sell.customer.phone && <p className="text-sm text-muted-foreground">{sell.customer.phone}</p>}
                            {sell.customer.address && <p className="text-sm text-muted-foreground">{sell.customer.address}</p>}
                        </div>
                    ) : (
                        <div className="mb-6 border border-border p-3">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Customer</p>
                            <p className="text-sm text-muted-foreground">Walk-in Customer</p>
                        </div>
                    )}

                    <div className="mb-6 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/30 text-xs uppercase tracking-wide">
                                    <th className="px-3 py-2 text-left">#</th>
                                    <th className="px-3 py-2 text-left">Product</th>
                                    <th className="px-3 py-2 text-right">Unit Price</th>
                                    <th className="px-3 py-2 text-right">Qty</th>
                                    <th className="px-3 py-2 text-right">Sub Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {sell.products?.map((item, i) => (
                                    <tr key={item.id}>
                                        <td className="px-3 py-2 text-muted-foreground">{i + 1}</td>
                                        <td className="px-3 py-2">
                                            <p className="font-medium">{item.product?.name}</p>
                                            {item.variation?.variation_data?.label && (
                                                <p className="text-xs text-muted-foreground">
                                                    {item.variation.variation_data.label}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right">৳{parseFloat(item.unit_price).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-right">{parseFloat(item.quantity)}</td>
                                        <td className="px-3 py-2 text-right font-semibold">
                                            ৳{(parseFloat(item.unit_price) * parseFloat(item.quantity)).toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex justify-end">
                        <div className="w-64 space-y-1.5 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Gross Amount</span>
                                <span>৳{gross.toFixed(2)}</span>
                            </div>
                            {discount > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Discount</span>
                                    <span className="text-green-600">-৳{discount.toFixed(2)}</span>
                                </div>
                            )}
                            {vat > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">VAT</span>
                                    <span>৳{vat.toFixed(2)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-border pt-1.5 font-bold">
                                <span>Net Amount</span>
                                <span>৳{net.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between text-green-700 dark:text-green-400">
                                <span>Paid</span>
                                <span>৳{paid.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between font-semibold text-destructive">
                                <span>Due</span>
                                <span>৳{due.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 flex items-center gap-2">
                        <Badge className={due > 0 ? 'bg-red-600 text-white' : 'bg-green-600 text-white'}>
                            {due > 0 ? 'Partially Paid' : 'Paid'}
                        </Badge>
                    </div>

                    {sell.comment && (
                        <div className="mt-4 border-t border-border pt-3 text-sm text-muted-foreground">
                            <strong>Note:</strong> {sell.comment}
                        </div>
                    )}
                </div>

                {can('inventory.sell.delete') && (
                <Dialog open={deleting} onOpenChange={setDeleting}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Delete sale?</DialogTitle>
                            <DialogDescription>
                                This will permanently delete the sale and restore stock.
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
