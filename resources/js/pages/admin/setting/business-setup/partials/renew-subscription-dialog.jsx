import { useState, useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import { ArrowRight, Calendar, CreditCard, DollarSign, RefreshCw, ShieldCheck, Sparkles } from 'lucide-react';
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
    const defaultFee = sub.fee ?? 1500;
    const branchCycleDays = sub.cycle_days ? String(sub.cycle_days) : '30';

    const { data, setData, post, processing, errors, reset } = useForm({
        duration_days: branchCycleDays,
        amount: String(defaultFee),
        payment_method: 'cash',
        transaction_reference: '',
        paid_at: new Date().toISOString().split('T')[0],
        notes: '',
    });

    useEffect(() => {
        if (open && branch) {
            const initialDays = sub.cycle_days ? String(sub.cycle_days) : '30';
            setData({
                duration_days: initialDays,
                amount: String(sub.fee ?? 1500),
                payment_method: 'cash',
                transaction_reference: '',
                paid_at: new Date().toISOString().split('T')[0],
                notes: '',
            });
        }
    }, [open, branch]);

    // Live preview calculation of new extended expiry date
    const extensionPreview = useMemo(() => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let baseDate = new Date(today);
        if (sub.expires_at) {
            const currentExp = new Date(sub.expires_at);
            currentExp.setHours(0, 0, 0, 0);
            if (currentExp > today) {
                baseDate = currentExp;
            }
        }

        const days = parseInt(data.duration_days, 10) || 30;
        const newExpiry = new Date(baseDate);
        newExpiry.setDate(newExpiry.getDate() + days);

        const formatDate = (d) => {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        };

        return {
            fromDate: formatDate(baseDate),
            toDate: formatDate(newExpiry),
            days,
        };
    }, [sub.expires_at, data.duration_days]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('setting.business-setup.branch.renew', branch.id), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    const handleDurationPreset = (days) => {
        setData('duration_days', String(days));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg p-6 border-slate-300 dark:border-slate-700 shadow-2xl">
                <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-foreground">
                        <div className="flex size-8 items-center justify-center rounded-md bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                            <RefreshCw className="size-4" />
                        </div>
                        Renew Subscription — {branch.name}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Record a renewal payment and continuously extend validity by the branch billing cycle period.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Live Extension Period Card */}
                    <div className="rounded-lg border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/70 dark:bg-emerald-950/20 p-3 space-y-2">
                        <div className="flex items-center justify-between text-xs font-semibold text-emerald-900 dark:text-emerald-300">
                            <span className="flex items-center gap-1.5">
                                <Sparkles className="size-3.5 text-emerald-600" />
                                Extension Timeline Preview
                            </span>
                            <span className="rounded-full bg-emerald-200/70 dark:bg-emerald-900/60 px-2 py-0.5 text-[0.68rem] font-bold text-emerald-800 dark:text-emerald-200">
                                +{extensionPreview.days} Days Validity
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-xs text-emerald-950 dark:text-emerald-200 font-medium pt-1">
                            <div className="flex flex-col">
                                <span className="text-[0.68rem] text-emerald-700 dark:text-emerald-400">Current / Base Date</span>
                                <span className="font-bold">{extensionPreview.fromDate}</span>
                            </div>
                            <ArrowRight className="size-4 text-emerald-600 shrink-0" />
                            <div className="flex flex-col text-right">
                                <span className="text-[0.68rem] text-emerald-700 dark:text-emerald-400">New Extended Expiry</span>
                                <span className="font-bold text-emerald-700 dark:text-emerald-300">{extensionPreview.toDate}</span>
                            </div>
                        </div>
                    </div>

                    {/* Duration preset buttons */}
                    <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-3 space-y-2">
                        <label className="text-xs font-semibold text-foreground flex items-center justify-between">
                            <span className="flex items-center gap-1.5">
                                <Calendar className="size-3.5 text-primary" />
                                Renewal Duration Period
                            </span>
                            {sub.cycle_days && (
                                <span className="text-[0.7rem] text-muted-foreground font-normal">
                                    Client Cycle: <strong>{sub.cycle_days} Days</strong>
                                </span>
                            )}
                        </label>
                        <div className="grid grid-cols-4 gap-2">
                            {[
                                { days: 30, label: '1 Month (30d)' },
                                { days: 90, label: '3 Months (90d)' },
                                { days: 180, label: '6 Months (180d)' },
                                { days: 365, label: '1 Year (365d)' },
                            ].map((preset) => {
                                const isSelected = data.duration_days === String(preset.days);
                                return (
                                    <Button
                                        key={preset.days}
                                        type="button"
                                        size="sm"
                                        variant={isSelected ? 'default' : 'outline'}
                                        className={`h-8 text-[0.72rem] font-semibold ${
                                            isSelected
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-foreground hover:bg-slate-100'
                                        }`}
                                        onClick={() => handleDurationPreset(preset.days)}
                                    >
                                        {preset.label}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField label="Duration (Total Days)" name="duration_days" required error={errors.duration_days}>
                            <Input
                                type="number"
                                min="1"
                                max="3650"
                                value={data.duration_days}
                                onChange={(e) => setData('duration_days', e.target.value)}
                                className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-bold"
                                required
                            />
                        </FormField>

                        <FormField label="Amount Paid" name="amount" required error={errors.amount}>
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
