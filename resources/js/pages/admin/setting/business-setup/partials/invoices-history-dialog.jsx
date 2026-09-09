import { useState, useEffect } from 'react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    FileText,
    Loader2,
    Receipt,
    X,
} from 'lucide-react';
import { route } from '@/lib/route';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export default function InvoicesHistoryDialog({ open, onClose, onOpenChange, branch, fetchUrl = null }) {
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const handleOpenChange = (val) => {
        if (onOpenChange) onOpenChange(val);
        if (!val && onClose) onClose();
    };

    useEffect(() => {
        if (!open || !branch?.id) {
            setInvoices([]);
            setError(null);
            return;
        }

        setLoading(true);
        setError(null);

        const targetUrl = fetchUrl || route('branch-clients.invoices', branch.id);

        fetch(targetUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.json())
            .then((data) => {
                setInvoices(data.invoices ?? []);
                setLoading(false);
            })
            .catch((err) => {
                setError('Failed to load invoices.');
                console.error(err);
                setLoading(false);
            });
    }, [open, branch?.id, fetchUrl]);

    const getStatusBadge = (status) => {
        switch (status) {
            case 'paid':
                return <Badge className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30">Paid</Badge>;
            case 'partial':
                return <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30">Partial</Badge>;
            case 'overdue':
                return <Badge className="bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30">Overdue</Badge>;
            default:
                return <Badge className="bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30">Unpaid</Badge>;
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-3xl max-h-[85vh] flex flex-col p-0 overflow-hidden">
                <DialogHeader className="p-6 pb-4 border-b bg-muted/30">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-primary/10 text-primary">
                            <FileText className="w-5 h-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-xl">Subscription Invoices</DialogTitle>
                            <p className="text-sm text-muted-foreground mt-0.5">
                                Billing invoices & payment allocations for <span className="font-semibold text-foreground">{branch?.name}</span>
                            </p>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto p-6 space-y-4">
                    {loading ? (
                        <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                            <Loader2 className="w-8 h-8 animate-spin mb-2" />
                            <p className="text-sm">Loading billing invoices...</p>
                        </div>
                    ) : error ? (
                        <div className="text-center py-12 text-rose-500">
                            <p className="text-sm">{error}</p>
                        </div>
                    ) : invoices.length === 0 ? (
                        <div className="text-center py-12 text-muted-foreground">
                            <Receipt className="w-10 h-10 mx-auto mb-2 opacity-40" />
                            <p className="text-sm">No subscription invoices generated yet.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {invoices.map((inv) => (
                                <div
                                    key={inv.id}
                                    className="p-4 rounded-xl border bg-card/60 hover:bg-card/90 transition-colors space-y-2.5"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-bold text-foreground font-mono">{inv.invoice_number}</span>
                                            {getStatusBadge(inv.status)}
                                        </div>
                                        <div className="text-right">
                                            <span className="text-base font-bold text-foreground">৳{Number(inv.total_amount).toLocaleString()}</span>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-1.5">
                                            <Calendar className="w-3.5 h-3.5" />
                                            <span>Period: {inv.billing_period_starts_at} → {inv.billing_period_ends_at}</span>
                                        </div>
                                        <div>
                                            <span>Paid: </span>
                                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">৳{Number(inv.paid_amount).toLocaleString()}</span>
                                        </div>
                                        <div>
                                            <span>Due: </span>
                                            <span className={`font-semibold ${Number(inv.due_amount) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'}`}>
                                                ৳{Number(inv.due_amount).toLocaleString()}
                                            </span>
                                        </div>
                                    </div>

                                    {inv.allocations && inv.allocations.length > 0 && (
                                        <div className="pt-2 border-t border-dashed text-xs space-y-1">
                                            <span className="text-muted-foreground font-medium">Applied Payments:</span>
                                            {inv.allocations.map((alloc) => (
                                                <div key={alloc.id} className="flex justify-between items-center text-muted-foreground bg-muted/40 px-2 py-1 rounded">
                                                    <span>Payment #{alloc.payment_id} ({alloc.payment_method || 'Channel'}) on {alloc.paid_at}</span>
                                                    <span className="font-semibold text-foreground">৳{Number(alloc.amount).toLocaleString()}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="p-4 border-t bg-muted/20 flex justify-end">
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
