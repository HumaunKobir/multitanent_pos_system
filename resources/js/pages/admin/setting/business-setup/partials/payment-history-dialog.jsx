import { useEffect, useState } from 'react';
import { CreditCard, History, Loader2 } from 'lucide-react';
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

export default function PaymentHistoryDialog({ open, onOpenChange, branch }) {
    const [loading, setLoading] = useState(false);
    const [payments, setPayments] = useState([]);

    useEffect(() => {
        if (open && branch) {
            setLoading(true);
            fetch(route('setting.business-setup.branch.payments', branch.id), {
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
    }, [open, branch]);

    if (!branch) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col p-6 border-slate-300 dark:border-slate-700 shadow-2xl">
                <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-foreground">
                        <div className="flex size-8 items-center justify-center rounded-md bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400">
                            <History className="size-4" />
                        </div>
                        Subscription Payment History — {branch.name}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Audit log of all past renewals and subscription payments recorded for this client branch.
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
                                        <th className="px-3.5 py-2.5">Reference</th>
                                        <th className="px-3.5 py-2.5">Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800 bg-card">
                                    {payments.map((p) => (
                                        <tr key={p.id} className="hover:bg-muted/30 transition-colors">
                                            <td className="px-3.5 py-2.5 font-medium text-foreground">{p.paid_at}</td>
                                            <td className="px-3.5 py-2.5 font-bold text-emerald-600 dark:text-emerald-400">
                                                {Number(p.amount).toFixed(2)}
                                            </td>
                                            <td className="px-3.5 py-2.5 uppercase">
                                                <Badge variant="outline" className="text-[0.65rem] px-1.5 py-0.5 border-slate-300 dark:border-slate-700 font-semibold">
                                                    {p.payment_method}
                                                </Badge>
                                            </td>
                                            <td className="px-3.5 py-2.5 text-muted-foreground">
                                                {p.billing_period_starts_at} → {p.billing_period_ends_at}
                                            </td>
                                            <td className="px-3.5 py-2.5 text-muted-foreground font-mono text-[0.7rem]">
                                                {p.transaction_reference || '—'}
                                            </td>
                                            <td className="px-3.5 py-2.5 text-muted-foreground">{p.recorded_by}</td>
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
    );
}
