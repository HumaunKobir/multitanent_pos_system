import DocShow from '../_shared/doc-show';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBdDate } from '@/lib/format-bd-date';

export default function PurchaseReturnShow({ purchaseReturn, paymentAccounts = [], today }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [payDialog, setPayDialog] = useState(false);

    const gross = parseFloat(purchaseReturn.gross_amount || 0);
    const discount = parseFloat(purchaseReturn.discount || 0);
    const vat = parseFloat(purchaseReturn.vat || 0);
    const vatPercent = parseFloat(purchaseReturn.vat_percent || 0);
    const net = parseFloat(purchaseReturn.net_amount || 0);
    const paid = parseFloat(purchaseReturn.paid_amount || 0);
    const due = parseFloat(purchaseReturn.due_amount || 0);
    const receivedAmount = parseFloat(purchaseReturn.received_amount || 0);

    const payForm = useForm({
        date: today,
        amount: due > 0 ? String(due) : '',
        payment_account_id: paymentAccounts[0]?.id ?? '',
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    function handleReceivePayment(e) {
        e.preventDefault();
        payForm.post(route('inventory.purchase-return.receive-payment', purchaseReturn.id), {
            preserveScroll: true,
            onSuccess: () => {
                setPayDialog(false);
                payForm.reset();
            },
        });
    }

    return (
        <>
            <DocShow
                title="Purchase Return"
                invoice={purchaseReturn.invoice_number}
                backRoute="inventory.purchase-return.index"
                editRoute="inventory.purchase-return.edit"
                destroyRoute="inventory.purchase-return.destroy"
                updatePermission="inventory.purchase-return.update"
                deletePermission="inventory.purchase-return.delete"
                id={purchaseReturn.id}
                date={purchaseReturn.date}
                comment={purchaseReturn.comment}
                extra={
                    <div className="mt-1 space-y-2 text-xs">
                        <p>Supplier: {purchaseReturn.supplier?.company_name && purchaseReturn.supplier?.name ? `${purchaseReturn.supplier.company_name} (${purchaseReturn.supplier.name})` : purchaseReturn.supplier?.company_name || purchaseReturn.supplier?.name} · Purchase #{purchaseReturn.purchase_id}</p>
                        <div className="flex flex-wrap gap-x-3 text-muted-foreground">
                            <span>Gross ৳{gross.toFixed(2)}</span>
                            {discount > 0.009 && <span>Discount -৳{discount.toFixed(2)}</span>}
                            {vat > 0.009 && (
                                <span>VAT {vatPercent > 0.009 ? `(${vatPercent.toFixed(2)}%)` : ''} +৳{vat.toFixed(2)}</span>
                            )}
                            <span>Net ৳{net.toFixed(2)}</span>
                            <span className="text-green-700 dark:text-green-400">Paid ৳{paid.toFixed(2)}</span>
                            {receivedAmount > 0.009 && (
                                <span className="text-green-700 dark:text-green-400">Received ৳{receivedAmount.toFixed(2)}</span>
                            )}
                            <span className={due > 0 ? 'font-semibold text-destructive' : 'font-semibold text-green-700 dark:text-green-400'}>
                                Due ৳{due.toFixed(2)}
                            </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className={due <= 0 ? 'bg-green-600 text-white' : 'bg-orange-500 text-white'}>
                                {due <= 0 ? 'Settled' : 'Pending Refund'}
                            </Badge>
                            {due > 0 && paymentAccounts.length > 0 && (
                                <Button size="sm" variant="outline" onClick={() => setPayDialog(true)}>
                                    Receive Payment
                                </Button>
                            )}
                        </div>

                        {purchaseReturn.payments?.length > 0 && (
                            <div className="mt-2 rounded-md border border-border bg-muted/30 p-2">
                                <p className="mb-1 font-medium text-foreground">Payments Received</p>
                                {purchaseReturn.payments.map((p) => (
                                    <div key={p.id} className="flex justify-between text-muted-foreground">
                                        <span>{formatBdDate(p.date)} · {p.serial}</span>
                                        <span className="text-green-700 dark:text-green-400">৳{parseFloat(p.amount).toFixed(2)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                }
                lines={purchaseReturn.products?.map((p) => ({
                    name: p.product?.name,
                    code: p.product?.code,
                    qty: p.quantity,
                    price: p.unit_price,
                }))}
            />

            <Dialog open={payDialog} onOpenChange={setPayDialog}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Receive Supplier Payment</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleReceivePayment} className="space-y-3 pt-1">
                        <div>
                            <Label className="text-xs">Date</Label>
                            <Input type="date" value={payForm.data.date} onChange={(e) => payForm.setData('date', e.target.value)} className="mt-1" />
                            {payForm.errors.date && <p className="mt-1 text-xs text-destructive">{payForm.errors.date}</p>}
                        </div>
                        <div>
                            <Label className="text-xs">Amount (Due: ৳{due.toFixed(2)})</Label>
                            <Input type="number" min="0.01" step="0.01" max={due} value={payForm.data.amount} onChange={(e) => payForm.setData('amount', e.target.value)} className="mt-1" />
                            {payForm.errors.amount && <p className="mt-1 text-xs text-destructive">{payForm.errors.amount}</p>}
                        </div>
                        <div>
                            <Label className="text-xs">Payment Account</Label>
                            <select
                                value={payForm.data.payment_account_id}
                                onChange={(e) => payForm.setData('payment_account_id', e.target.value)}
                                className="mt-1 h-8 w-full rounded-md border border-input bg-background px-2 text-xs outline-none focus:border-primary"
                            >
                                {paymentAccounts.map((acc) => (
                                    <option key={acc.id} value={acc.id}>{acc.label}</option>
                                ))}
                            </select>
                            {payForm.errors.payment_account_id && <p className="mt-1 text-xs text-destructive">{payForm.errors.payment_account_id}</p>}
                        </div>
                        <DialogFooter className="gap-2 pt-2">
                            <DialogClose asChild>
                                <Button type="button" variant="outline" size="sm">Cancel</Button>
                            </DialogClose>
                            <Button type="submit" size="sm" disabled={payForm.processing}>
                                Record Receipt
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
