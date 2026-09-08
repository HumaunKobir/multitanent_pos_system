import { useEffect, useState } from 'react';
import { CreditCard, ExternalLink, Eye, FileText, History, Image as ImageIcon, Loader2, X } from 'lucide-react';
import { route } from '@/lib/route';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export default function PaymentHistoryDialog({ open, onOpenChange, branch, fetchUrl = null }) {
    const [loading, setLoading] = useState(false);
    const [payments, setPayments] = useState([]);
    const [previewAttachment, setPreviewAttachment] = useState(null);

    useEffect(() => {
        if (open && branch) {
            setLoading(true);
            const targetUrl = fetchUrl || route('setting.business-setup.branch.payments', branch.id);
            fetch(targetUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((res) => res.json())
                .then((data) => {
                    setPayments(data.payments || []);
                    setLoading(false);
                })
                .catch(() => {
                    setPayments([]);
                    setLoading(false);
                });
        }
    }, [open, branch, fetchUrl]);

    if (!branch) return null;

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-w-3xl max-h-[85vh] flex flex-col p-6 border-slate-300 dark:border-slate-700 shadow-2xl">
                    <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                        <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-foreground">
                            <div className="flex size-8 items-center justify-center rounded-md bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400">
                                <History className="size-4" />
                            </div>
                            Subscription Payment History — {branch.name}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Audit log of all past renewals, receipts, and subscription payments recorded for this branch.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-y-auto py-2">
                        {loading ? (
                            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                                <Loader2 className="size-6 animate-spin text-primary mb-2" />
                                <p className="text-xs">Loading payment logs...</p>
                            </div>
                        ) : payments.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center text-xs text-muted-foreground bg-slate-50/50 dark:bg-slate-900/30">
                                No subscription payment records found for this branch yet.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800 shadow-xs">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-slate-200 dark:border-slate-800 bg-slate-100/70 dark:bg-slate-900/60 font-semibold text-muted-foreground">
                                        <tr>
                                            <th className="px-3.5 py-2.5">Date Paid</th>
                                            <th className="px-3.5 py-2.5">Amount</th>
                                            <th className="px-3.5 py-2.5">Method</th>
                                            <th className="px-3.5 py-2.5">Billing Period</th>
                                            <th className="px-3.5 py-2.5">Reference / TrxID</th>
                                            <th className="px-3.5 py-2.5">Receipt</th>
                                            <th className="px-3.5 py-2.5">Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-800 bg-card">
                                        {payments.map((p) => (
                                            <tr key={p.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-3.5 py-2.5 font-medium text-foreground whitespace-nowrap">{p.paid_at}</td>
                                                <td className="px-3.5 py-2.5 font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                                    ৳{Number(p.amount).toFixed(2)}
                                                </td>
                                                <td className="px-3.5 py-2.5 uppercase whitespace-nowrap">
                                                    <Badge variant="outline" className="text-[0.65rem] px-1.5 py-0.5 border-slate-300 dark:border-slate-700 font-semibold">
                                                        {p.payment_method}
                                                    </Badge>
                                                </td>
                                                <td className="px-3.5 py-2.5 text-muted-foreground whitespace-nowrap">
                                                    {p.billing_period_starts_at} → {p.billing_period_ends_at}
                                                </td>
                                                <td className="px-3.5 py-2.5 text-muted-foreground font-mono text-[0.7rem] max-w-[120px] truncate" title={p.transaction_reference}>
                                                    {p.transaction_reference || '—'}
                                                </td>
                                                <td className="px-3.5 py-2.5">
                                                    {p.attachment_url ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewAttachment(p)}
                                                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200 dark:border-blue-900 text-[11px] font-medium hover:bg-blue-100 transition-colors"
                                                        >
                                                            <Eye className="size-3" />
                                                            <span>View</span>
                                                        </button>
                                                    ) : (
                                                        <span className="text-muted-foreground text-[11px]">None</span>
                                                    )}
                                                </td>
                                                <td className="px-3.5 py-2.5 text-muted-foreground whitespace-nowrap">{p.recorded_by}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <DialogFooter className="pt-3 border-t border-slate-200 dark:border-slate-800">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="border-slate-300 dark:border-slate-700 font-medium"
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Receipt Modal Preview */}
            <Dialog open={Boolean(previewAttachment)} onOpenChange={(val) => !val && setPreviewAttachment(null)}>
                <DialogContent className="max-w-xl p-6 border-slate-300 dark:border-slate-700 shadow-2xl">
                    <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                        <DialogTitle className="flex items-center gap-2 text-base font-bold text-foreground">
                            <ImageIcon className="size-4 text-primary" />
                            Payment Receipt Preview
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            TrxID: <strong>{previewAttachment?.transaction_reference || 'N/A'}</strong> • Amount: <strong>৳{Number(previewAttachment?.amount || 0).toFixed(2)}</strong> ({previewAttachment?.payment_method})
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-3 flex flex-col items-center justify-center">
                        {previewAttachment?.attachment_url?.toLowerCase().endsWith('.pdf') ? (
                            <div className="p-8 text-center space-y-3">
                                <FileText className="size-16 text-rose-500 mx-auto" />
                                <p className="text-xs font-semibold">PDF Receipt Document</p>
                                <Button asChild size="sm">
                                    <a href={previewAttachment.attachment_url} target="_blank" rel="noreferrer" className="flex items-center gap-1.5">
                                        <ExternalLink className="size-3.5" /> Open PDF in New Tab
                                    </a>
                                </Button>
                            </div>
                        ) : (
                            <div className="max-h-[60vh] overflow-auto rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-900/10 p-1">
                                <img
                                    src={previewAttachment?.attachment_url}
                                    alt="Payment Receipt"
                                    className="max-h-[55vh] w-auto rounded-md object-contain mx-auto"
                                />
                            </div>
                        )}
                    </div>

                    <DialogFooter className="pt-2 flex justify-between sm:justify-between border-t border-slate-200 dark:border-slate-800">
                        {previewAttachment?.attachment_url && (
                            <Button variant="outline" size="sm" asChild className="text-xs">
                                <a href={previewAttachment.attachment_url} target="_blank" rel="noreferrer" download>
                                    <ExternalLink className="size-3 mr-1" /> Open Original
                                </a>
                            </Button>
                        )}
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setPreviewAttachment(null)}
                        >
                            Close Preview
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
