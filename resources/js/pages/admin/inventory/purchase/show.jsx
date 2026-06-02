import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, HandCoins, Printer, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export default function PurchaseShow({ purchase }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const invoiceNumber = purchase.invoice_number ?? `INVP${String(purchase.id).padStart(8, '0')}`;
    const net = parseFloat(purchase.gross_amount) + parseFloat(purchase.vat) - parseFloat(purchase.discount);

    function handleDelete() {
        router.delete(route('inventory.purchase.destroy', purchase.id), {
            onSuccess: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`Purchase — ${invoiceNumber}`} />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <HandCoins className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Purchase Invoice</h1>
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
                            Print
                        </Button>
                        <Button
                            size="sm"
                            asChild
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        >
                            <Link href={route('inventory.purchase.edit', purchase.id)}>
                                <Edit className="size-3.5" />
                                Edit
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => setDeleting(true)}
                            className="border border-red-500/50 bg-red-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-md"
                        >
                            <Trash2 className="size-3.5" />
                            Delete
                        </Button>
                        <Button
                            size="sm"
                            asChild
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        >
                            <Link href={route('inventory.purchase.index')}>
                                <ArrowLeft className="size-3.5" />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="border border-border p-6 print:border-0 print:p-0">
                    {/* Invoice Header */}
                    <div className="mb-6 flex justify-between">
                        <div>
                            <h2 className="text-xl font-bold">Coolness Point</h2>
                            <p className="text-sm text-muted-foreground">Purchase Invoice</p>
                        </div>
                        <div className="text-right text-sm">
                            <p className="font-mono font-bold">{invoiceNumber}</p>
                            <p className="text-muted-foreground">Date: {purchase.date}</p>
                        </div>
                    </div>

                    {/* Supplier Info */}
                    {purchase.supplier && (
                        <div className="mb-6 border border-border p-3">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supplier</p>
                            <p className="font-semibold">{purchase.supplier.name}</p>
                            {purchase.supplier.phone && <p className="text-sm text-muted-foreground">{purchase.supplier.phone}</p>}
                            {purchase.supplier.company_name && <p className="text-sm text-muted-foreground">{purchase.supplier.company_name}</p>}
                        </div>
                    )}

                    {/* Line Items */}
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
                                {purchase.purchase_products?.map((item, i) => (
                                    <tr key={item.id}>
                                        <td className="px-3 py-2 text-muted-foreground">{i + 1}</td>
                                        <td className="px-3 py-2">
                                            <p className="font-medium">{item.product?.name}</p>
                                            {item.variation?.variation_data?.label && (
                                                <p className="text-xs text-muted-foreground">{item.variation.variation_data.label}</p>
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

                    {/* Totals */}
                    <div className="flex justify-end">
                        <div className="w-64 space-y-1.5 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Gross Amount</span>
                                <span>৳{parseFloat(purchase.gross_amount).toFixed(2)}</span>
                            </div>
                            {parseFloat(purchase.discount) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Discount</span>
                                    <span className="text-green-600">-৳{parseFloat(purchase.discount).toFixed(2)}</span>
                                </div>
                            )}
                            {parseFloat(purchase.vat) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">VAT</span>
                                    <span>৳{parseFloat(purchase.vat).toFixed(2)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-border pt-1.5 font-bold">
                                <span>Net Amount</span>
                                <span>৳{net.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between text-green-700 dark:text-green-400">
                                <span>Paid</span>
                                <span>৳{parseFloat(purchase.paid_amount).toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between font-semibold text-destructive">
                                <span>Due</span>
                                <span>৳{parseFloat(purchase.due_amount).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>

                    {/* Status */}
                    <div className="mt-4 flex items-center gap-2">
                        <Badge className={parseFloat(purchase.due_amount) > 0 ? 'bg-red-600 text-white' : 'bg-green-600 text-white'}>
                            {parseFloat(purchase.due_amount) > 0 ? 'Partially Paid' : 'Paid'}
                        </Badge>
                    </div>

                    {purchase.comment && (
                        <div className="mt-4 border-t border-border pt-3 text-sm text-muted-foreground">
                            <strong>Note:</strong> {purchase.comment}
                        </div>
                    )}
                </div>

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
            </div>
        </>
    );
}
