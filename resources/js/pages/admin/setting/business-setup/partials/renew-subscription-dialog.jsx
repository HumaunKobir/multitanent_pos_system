import { useState, useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, ArrowRight, Calendar, CheckCircle2, CreditCard, DollarSign, Layers, RefreshCw, ShieldCheck, Sparkles } from 'lucide-react';
import { route } from '@/lib/route';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { FormField } from '@/components/form-field';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function RenewSubscriptionDialog({ open, onOpenChange, branch, paymentMethods = [] }) {
    if (!branch) return null;

    const sub = branch.subscription || {};
    const cycleDays = parseInt(sub.cycle_days, 10) || 30;
    const feePerCycle = parseFloat(sub.fee) || 1500;
    const isOverdue = Boolean(sub.is_overdue);
    const overdueDays = parseInt(sub.overdue_days, 10) || 0;
    const pendingBillsCount = isOverdue && cycleDays > 0 ? Math.max(1, Math.ceil(overdueDays / cycleDays)) : 0;
    const totalOverdueAmount = pendingBillsCount * feePerCycle;

    const [selectedCycles, setSelectedCycles] = useState(1);

    const { data, setData, post, processing, errors, reset } = useForm({
        duration_days: String(cycleDays),
        amount: String(feePerCycle),
        payment_method: 'cash',
        transaction_reference: '',
        paid_at: new Date().toISOString().split('T')[0],
        notes: '',
    });

    useEffect(() => {
        if (open && branch) {
            const initialCycles = 1;
            setSelectedCycles(initialCycles);
            setData({
                duration_days: String(initialCycles * cycleDays),
                amount: String(initialCycles * feePerCycle),
                payment_method: 'cash',
                transaction_reference: '',
                paid_at: new Date().toISOString().split('T')[0],
                notes: '',
            });
        }
    }, [open, branch, cycleDays, feePerCycle]);

    // Live preview calculation of new extended expiry date
    const extensionPreview = useMemo(() => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Continuous extension from current expiry date
        let baseDate = new Date(today);
        if (sub.expires_at) {
            const currentExp = new Date(sub.expires_at);
            currentExp.setHours(0, 0, 0, 0);
            baseDate = currentExp;
        }

        const days = parseInt(data.duration_days, 10) || cycleDays;
        const newExpiry = new Date(baseDate);
        newExpiry.setDate(newExpiry.getDate() + days);

        const formatDate = (d) => {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        };

        const isStillOverdue = newExpiry < today;
        const remainingDaysOverdue = isStillOverdue ? Math.round((today - newExpiry) / (1000 * 60 * 60 * 24)) : 0;
        const remainingBillsUnpaid = isStillOverdue && cycleDays > 0 ? Math.ceil(remainingDaysOverdue / cycleDays) : 0;
        const futureActiveDays = !isStillOverdue ? Math.round((newExpiry - today) / (1000 * 60 * 60 * 24)) : 0;

        return {
            fromDate: formatDate(baseDate),
            toDate: formatDate(newExpiry),
            days,
            isStillOverdue,
            remainingDaysOverdue,
            remainingBillsUnpaid,
            futureActiveDays,
        };
    }, [sub.expires_at, data.duration_days, cycleDays]);

    const handleCyclesChange = (cycles) => {
        const numCycles = Math.max(1, parseInt(cycles, 10) || 1);
        setSelectedCycles(numCycles);
        setData({
            ...data,
            duration_days: String(numCycles * cycleDays),
            amount: String(numCycles * feePerCycle),
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('setting.business-setup.branch.renew', branch.id), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg p-6 border-slate-300 dark:border-slate-700 shadow-2xl max-h-[92vh] overflow-y-auto">
                <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-foreground">
                        <div className="flex size-8 items-center justify-center rounded-md bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                            <RefreshCw className="size-4" />
                        </div>
                        Renew Subscription — {branch.name}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Record a renewal payment. Validity extends continuously from the current expiry date.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Branch Billing Status Summary Card */}
                    <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 p-3 text-xs space-y-2">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                <Layers className="size-3.5 text-primary" />
                                Branch Billing Cycle
                            </div>
                            <span className="font-bold text-primary">
                                {cycleDays} Days / Cycle (৳{feePerCycle.toFixed(2)})
                            </span>
                        </div>

                        {isOverdue ? (
                            <div className="rounded-md border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/30 p-2 text-[11px] text-rose-800 dark:text-rose-200 space-y-0.5">
                                <div className="flex items-center gap-1.5 font-bold">
                                    <AlertTriangle className="size-3.5 text-rose-600 shrink-0" />
                                    <span>Payment Overdue: {overdueDays} Day(s)</span>
                                </div>
                                <p className="text-[10px] text-rose-700/90 dark:text-rose-300/90 leading-tight">
                                    <strong>{pendingBillsCount} Unpaid Bill(s)</strong> pending (Total Due: <strong>৳{totalOverdueAmount.toFixed(2)}</strong>).
                                </p>
                            </div>
                        ) : (
                            <div className="flex items-center justify-between text-[11px] text-muted-foreground pt-0.5">
                                <span>Current Expiry Due Date:</span>
                                <span className="font-bold text-foreground">{sub.expires_at || 'Not set'}</span>
                            </div>
                        )}
                    </div>

                    {/* Cycle Selection Presets */}
                    <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-3 space-y-2">
                        <label className="text-xs font-semibold text-foreground flex items-center justify-between">
                            <span className="flex items-center gap-1.5">
                                <Calendar className="size-3.5 text-primary" />
                                Number of Billing Cycles / Bills to Pay
                            </span>
                        </label>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                            {[1, 2, 3, 4].map((cycles) => {
                                const days = cycles * cycleDays;
                                const cost = cycles * feePerCycle;
                                const isSelected = selectedCycles === cycles;
                                return (
                                    <Button
                                        key={cycles}
                                        type="button"
                                        size="sm"
                                        variant={isSelected ? 'default' : 'outline'}
                                        className={`h-auto py-1.5 px-2 flex flex-col items-center justify-center text-[0.7rem] ${
                                            isSelected
                                                ? 'bg-primary text-primary-foreground shadow-xs font-bold'
                                                : 'border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-foreground hover:bg-slate-100'
                                        }`}
                                        onClick={() => handleCyclesChange(cycles)}
                                    >
                                        <span>{cycles} Bill{cycles > 1 ? 's' : ''} ({days}d)</span>
                                        <span className="text-[0.65rem] opacity-90">৳{cost}</span>
                                    </Button>
                                );
                            })}
                        </div>

                        {pendingBillsCount > 0 && pendingBillsCount > 4 && (
                            <Button
                                type="button"
                                size="sm"
                                variant={selectedCycles === pendingBillsCount ? 'default' : 'outline'}
                                className="w-full mt-1.5 h-7 text-xs font-semibold border-rose-300 text-rose-700 dark:text-rose-300 hover:bg-rose-50"
                                onClick={() => handleCyclesChange(pendingBillsCount)}
                            >
                                Pay All {pendingBillsCount} Pending Overdue Bills ({pendingBillsCount * cycleDays}d — ৳{totalOverdueAmount.toFixed(2)})
                            </Button>
                        )}
                    </div>

                    {/* Live Extension Period Card */}
                    <div className={`rounded-lg border p-3 space-y-2 ${
                        extensionPreview.isStillOverdue
                            ? 'border-amber-200 dark:border-amber-900/50 bg-amber-50/70 dark:bg-amber-950/20'
                            : 'border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/70 dark:bg-emerald-950/20'
                    }`}>
                        <div className="flex items-center justify-between text-xs font-semibold">
                            <span className="flex items-center gap-1.5 text-foreground">
                                <Sparkles className="size-3.5 text-primary" />
                                Extension Timeline Preview
                            </span>
                            <span className={`rounded-full px-2 py-0.5 text-[0.68rem] font-bold ${
                                extensionPreview.isStillOverdue
                                    ? 'bg-amber-200 text-amber-900 dark:bg-amber-900/70 dark:text-amber-100'
                                    : 'bg-emerald-200/70 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200'
                            }`}>
                                +{extensionPreview.days} Days Validity
                            </span>
                        </div>

                        <div className="flex items-center justify-between text-xs font-medium pt-1">
                            <div className="flex flex-col">
                                <span className="text-[0.68rem] text-muted-foreground">Previous Expiry</span>
                                <span className="font-bold text-foreground">{extensionPreview.fromDate}</span>
                            </div>
                            <ArrowRight className="size-4 text-muted-foreground shrink-0" />
                            <div className="flex flex-col text-right">
                                <span className="text-[0.68rem] text-muted-foreground">New Extended Expiry</span>
                                <span className="font-bold text-primary">{extensionPreview.toDate}</span>
                            </div>
                        </div>

                        {/* Status after payment note */}
                        <div className="pt-1 border-t border-border/40 text-[11px]">
                            {extensionPreview.isStillOverdue ? (
                                <p className="text-amber-800 dark:text-amber-200 flex items-center gap-1">
                                    <AlertCircle className="size-3 text-amber-600 shrink-0" />
                                    <span>
                                        Will remain <strong>{extensionPreview.remainingDaysOverdue}d overdue ({extensionPreview.remainingBillsUnpaid} bill(s) remaining)</strong> after this payment.
                                    </span>
                                </p>
                            ) : (
                                <p className="text-emerald-800 dark:text-emerald-200 flex items-center gap-1">
                                    <CheckCircle2 className="size-3 text-emerald-600 shrink-0" />
                                    <span>
                                        Account will be <strong>fully active and valid for {extensionPreview.futureActiveDays} day(s)</strong> ahead.
                                    </span>
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField label="Duration (Total Days)" name="duration_days" required error={errors.duration_days}>
                            <Input
                                type="number"
                                min="1"
                                max="3650"
                                value={data.duration_days}
                                onChange={(e) => {
                                    setData('duration_days', e.target.value);
                                    setSelectedCycles(0);
                                }}
                                className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-bold"
                                required
                            />
                        </FormField>

                        <FormField label="Amount Paid (৳)" name="amount" required error={errors.amount}>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-bold text-emerald-600 dark:text-emerald-400"
                                required
                            />
                        </FormField>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField label="Payment Method" name="payment_method" required error={errors.payment_method}>
                            <Select
                                value={data.payment_method}
                                onValueChange={(val) => setData('payment_method', val)}
                            >
                                <SelectTrigger className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950">
                                    <SelectValue placeholder="Select Method" />
                                </SelectTrigger>
                                <SelectContent className="border-slate-300 dark:border-slate-700">
                                    {paymentMethods.map((pm) => (
                                        <SelectItem key={pm.value} value={pm.value}>
                                            {pm.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Payment Date" name="paid_at" required error={errors.paid_at}>
                            <Input
                                type="date"
                                value={data.paid_at}
                                onChange={(e) => setData('paid_at', e.target.value)}
                                className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium"
                                required
                            />
                        </FormField>
                    </div>

                    <FormField label="Trx / Reference ID (Optional)" name="transaction_reference" error={errors.transaction_reference}>
                        <Input
                            placeholder="e.g. Bank deposit reference, bKash TrxID"
                            value={data.transaction_reference}
                            onChange={(e) => setData('transaction_reference', e.target.value)}
                            className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                        />
                    </FormField>

                    <FormField label="Notes / Memo (Optional)" name="notes" error={errors.notes}>
                        <Textarea
                            rows={2}
                            placeholder="Additional details regarding this renewal..."
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                        />
                    </FormField>

                    <DialogFooter className="pt-2 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                            className="border-slate-300 dark:border-slate-700"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 shadow-xs"
                        >
                            {processing ? 'Processing...' : 'Confirm & Renew'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

