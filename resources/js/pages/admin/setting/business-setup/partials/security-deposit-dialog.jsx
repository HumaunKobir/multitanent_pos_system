import { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
    DollarSign,
    FileText,
    History,
    Loader2,
    Plus,
    Shield,
    Upload,
} from 'lucide-react';
import { route } from '@/lib/route';
import { useAppToast } from '@/contexts/app-toast-context';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export default function SecurityDepositDialog({
    open,
    onClose,
    onOpenChange,
    branch,
    paymentMethods = [],
    fetchUrl = null,
    submitUrl = null,
}) {
    const toast = useAppToast();
    const [deposits, setDeposits] = useState([]);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [tab, setTab] = useState('record'); // 'record' | 'history'

    const handleOpenChange = (val) => {
        if (onOpenChange) onOpenChange(val);
        if (!val && onClose) onClose();
    };

    const { data, setData, post, processing, reset, errors } = useForm({
        amount: '',
        payment_method: 'bKash',
        payment_account_id: '',
        transaction_reference: '',
        paid_at: new Date().toISOString().split('T')[0],
        notes: '',
        attachment: null,
    });

    const loadHistory = () => {
        if (!branch?.id) return;
        setLoadingHistory(true);
        const targetUrl = fetchUrl || route('branch-clients.security-deposits', branch.id);

        fetch(targetUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.json())
            .then((resData) => {
                setDeposits(resData.security_deposits ?? []);
                setLoadingHistory(false);
            })
            .catch((err) => {
                console.error(err);
                setLoadingHistory(false);
            });
    };

    useEffect(() => {
        if (open && branch?.id) {
            reset();
            setTab('record');
            loadHistory();
        }
    }, [open, branch?.id, fetchUrl]);

    const handleSubmit = (e) => {
        e.preventDefault();
        const targetSubmitUrl = submitUrl || route('branch-clients.security-deposit', branch.id);
        post(targetSubmitUrl, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Security deposit recorded successfully.');
                loadHistory();
                setTab('history');
            },
            onError: () => {
                toast.error('Failed to record security deposit.');
            },
        });
    };

    const totalDeposit = deposits
        .filter((d) => d.status === 'approved')
        .reduce((sum, d) => sum + Number(d.amount), 0);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col p-0 overflow-hidden">
                <DialogHeader className="p-6 pb-4 border-b bg-muted/30">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <Shield className="w-5 h-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-xl">Project Security Money</DialogTitle>
                                <p className="text-sm text-muted-foreground mt-0.5">
                                    Manage project handover security deposit for <span className="font-semibold text-foreground">{branch?.name}</span>
                                </p>
                            </div>
                        </div>
                        {totalDeposit > 0 && (
                            <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 border-emerald-500/30 text-sm px-3 py-1">
                                Held: ৳{totalDeposit.toLocaleString()}
                            </Badge>
                        )}
                    </div>

                    <div className="flex gap-2 mt-4 pt-2 border-t">
                        <Button
                            type="button"
                            size="sm"
                            variant={tab === 'record' ? 'default' : 'outline'}
                            onClick={() => setTab('record')}
                            className="gap-1.5"
                        >
                            <Plus className="w-4 h-4" /> Record Security Money
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={tab === 'history' ? 'default' : 'outline'}
                            onClick={() => setTab('history')}
                            className="gap-1.5"
                        >
                            <History className="w-4 h-4" /> Deposit History ({deposits.length})
                        </Button>
                    </div>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto p-6">
                    {tab === 'record' ? (
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="p-3.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-700 dark:text-amber-300">
                                <strong>Double-Entry Rule:</strong> Security money increases SuperAdmin's Cash/Bank (Asset) and is credited to <strong>Client Security Deposit (Liability)</strong>. In the client branch, it is booked as <strong>Project Security Expense</strong>.
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="sec_amount">Security Deposit Amount (৳) *</Label>
                                    <Input
                                        id="sec_amount"
                                        type="number"
                                        min="1"
                                        step="any"
                                        placeholder="e.g. 10000"
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        required
                                    />
                                    {errors.amount && <p className="text-xs text-rose-500">{errors.amount}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="sec_paid_at">Payment / Handover Date *</Label>
                                    <Input
                                        id="sec_paid_at"
                                        type="date"
                                        value={data.paid_at}
                                        onChange={(e) => setData('paid_at', e.target.value)}
                                        required
                                    />
                                    {errors.paid_at && <p className="text-xs text-rose-500">{errors.paid_at}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="sec_method">Payment Method / Channel *</Label>
                                    <select
                                        id="sec_method"
                                        className="w-full h-10 px-3 rounded-md border bg-background text-sm"
                                        value={data.payment_method}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            const matched = paymentMethods.find((p) => p.value === val || p.label === val);
                                            setData((prev) => ({
                                                ...prev,
                                                payment_method: val,
                                                payment_account_id: matched ? matched.id : '',
                                            }));
                                        }}
                                        required
                                    >
                                        <option value="bKash">bKash</option>
                                        <option value="Nagad">Nagad</option>
                                        <option value="Cash in Hand">Cash in Hand</option>
                                        <option value="Bank Account">Bank Account / Transfer</option>
                                        <option value="SSLCommerz">SSLCommerz</option>
                                    </select>
                                    {errors.payment_method && <p className="text-xs text-rose-500">{errors.payment_method}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="sec_ref">Trx Reference / Receipt #</Label>
                                    <Input
                                        id="sec_ref"
                                        placeholder="e.g. BKASH-SEC-1234"
                                        value={data.transaction_reference}
                                        onChange={(e) => setData('transaction_reference', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="sec_notes">Notes / Handover Details</Label>
                                <Textarea
                                    id="sec_notes"
                                    rows={2}
                                    placeholder="Project handover security deposit terms..."
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="sec_file">Money Receipt Attachment</Label>
                                <Input
                                    id="sec_file"
                                    type="file"
                                    accept="image/*,.pdf"
                                    onChange={(e) => setData('attachment', e.target.files[0])}
                                />
                            </div>

                            <div className="pt-4 flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing} className="gap-2">
                                    {processing && <Loader2 className="w-4 h-4 animate-spin" />}
                                    Save Security Money
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <div className="space-y-3">
                            {loadingHistory ? (
                                <div className="flex flex-col items-center justify-center py-10 text-muted-foreground">
                                    <Loader2 className="w-6 h-6 animate-spin mb-2" />
                                    <p className="text-xs">Loading deposit history...</p>
                                </div>
                            ) : deposits.length === 0 ? (
                                <div className="text-center py-10 text-muted-foreground">
                                    <Shield className="w-8 h-8 mx-auto mb-2 opacity-30" />
                                    <p className="text-sm">No security money recorded yet.</p>
                                </div>
                            ) : (
                                deposits.map((dep) => (
                                    <div
                                        key={dep.id}
                                        className="p-3.5 rounded-xl border bg-card/60 flex items-center justify-between gap-3 text-sm"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-foreground">৳{Number(dep.amount).toLocaleString()}</span>
                                                <Badge className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 text-xs">
                                                    {dep.payment_method}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Date: {dep.paid_at} • Ref: {dep.transaction_reference || 'N/A'} • By: {dep.recorded_by}
                                            </p>
                                            {dep.notes && <p className="text-xs text-muted-foreground/80 italic">{dep.notes}</p>}
                                        </div>

                                        {dep.attachment_url && (
                                            <a
                                                href={dep.attachment_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-xs text-primary hover:underline flex items-center gap-1"
                                            >
                                                <FileText className="w-3.5 h-3.5" /> View Receipt
                                            </a>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
