import { useEffect, useState, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import { Calendar, Clock, CreditCard, Info, ShieldAlert, Sliders, Sparkles } from 'lucide-react';
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

export default function EditBranchSubscriptionDialog({
    open,
    onOpenChange,
    branch,
    billingCycles = [],
    overdueActions = [],
    submitUrl = null,
}) {
    if (!branch) return null;

    const sub = branch.subscription || {};

    const defaultBillingCycles = [
        { value: 'monthly', label: 'Monthly (30 Days)', days: 30 },
        { value: 'quarterly', label: 'Quarterly (90 Days)', days: 90 },
        { value: 'half_yearly', label: 'Half-Yearly (180 Days)', days: 180 },
        { value: 'yearly', label: 'Yearly (365 Days)', days: 365 },
        { value: 'trial', label: 'Free Trial (14 Days)', days: 14 },
        { value: 'lifetime', label: 'Lifetime (Unlimited)', days: null },
        { value: 'custom_days', label: 'Custom Days Cycle', days: 30 },
    ];

    const cycleOptions = billingCycles.length > 0 ? billingCycles : defaultBillingCycles;

    const { data, setData, put, processing, errors, reset, transform } = useForm({
        subscription_plan: 'monthly',
        subscription_status: 'active',
        subscription_fee: '',
        subscription_starts_at: '',
        custom_cycle_days: '30',
        custom_grace_period_days: '',
        custom_warning_days: '',
        custom_overdue_action: 'default',
        subscription_notes: '',
    });

    useEffect(() => {
        if (open && branch) {
            let initialCycle = sub.plan || 'monthly';
            if (['basic', 'standard', 'premium', 'enterprise'].includes(initialCycle)) {
                initialCycle = 'monthly';
            }

            const startsAt = sub.starts_at || new Date().toISOString().split('T')[0];
            const expiresAt = sub.expires_at || '';

            let calculatedDays = '30';
            if (startsAt && expiresAt) {
                const diffTime = Math.abs(new Date(expiresAt) - new Date(startsAt));
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (diffDays > 0) calculatedDays = String(diffDays);
            }

            const customGrace = branch.custom_grace_period_days ?? sub.custom_grace_period_days;
            const customWarning = branch.custom_warning_days ?? sub.custom_warning_days;
            const customOverdue = branch.custom_overdue_action ?? sub.custom_overdue_action ?? 'default';
            const notes = branch.subscription_notes ?? sub.subscription_notes ?? '';
            const fee = branch.subscription_fee ?? sub.custom_fee ?? (sub.fee !== undefined ? sub.fee : '');

            setData({
                subscription_plan: initialCycle,
                subscription_status: sub.status || 'active',
                subscription_fee: fee !== null && fee !== undefined ? String(fee) : '',
                subscription_starts_at: startsAt,
                custom_cycle_days: calculatedDays,
                custom_grace_period_days: customGrace !== null && customGrace !== undefined ? String(customGrace) : '',
                custom_warning_days: customWarning !== null && customWarning !== undefined ? String(customWarning) : '',
                custom_overdue_action: customOverdue || 'default',
                subscription_notes: notes,
            });
        }
    }, [open, branch]);

    transform((formData) => ({
        ...formData,
        custom_overdue_action: formData.custom_overdue_action === 'default' ? null : formData.custom_overdue_action,
        custom_grace_period_days: formData.custom_grace_period_days !== '' ? formData.custom_grace_period_days : null,
        custom_warning_days: formData.custom_warning_days !== '' ? formData.custom_warning_days : null,
        subscription_fee: formData.subscription_fee !== '' ? formData.subscription_fee : null,
        subscription_notes: formData.subscription_notes !== '' ? formData.subscription_notes : null,
    }));

    // Live preview calculation of expiry date based on Start Date + Duration
    const calculatedExpiry = useMemo(() => {
        if (data.subscription_plan === 'lifetime' || data.subscription_status === 'lifetime') {
            return { text: 'Unlimited Lifetime Access (No Expiry Date)', date: null, isLifetime: true };
        }

        const start = data.subscription_starts_at ? new Date(data.subscription_starts_at) : new Date();
        if (isNaN(start.getTime())) {
            return { text: 'Invalid Start Date', date: null, isLifetime: false };
        }

        let days = 30;
        if (data.subscription_plan === 'monthly') days = 30;
        else if (data.subscription_plan === 'quarterly') days = 90;
        else if (data.subscription_plan === 'half_yearly') days = 180;
        else if (data.subscription_plan === 'yearly') days = 365;
        else if (data.subscription_plan === 'trial') days = 14;
        else if (data.subscription_plan === 'custom_days') {
            days = parseInt(data.custom_cycle_days, 10) || 30;
        }

        const expDate = new Date(start);
        expDate.setDate(expDate.getDate() + days);
        const yyyy = expDate.getFullYear();
        const mm = String(expDate.getMonth() + 1).padStart(2, '0');
        const dd = String(expDate.getDate()).padStart(2, '0');
        const formattedDate = `${yyyy}-${mm}-${dd}`;

        return {
            text: `${formattedDate} (${days} Days Total Validity)`,
            date: formattedDate,
            days,
            isLifetime: false,
        };
    }, [data.subscription_plan, data.subscription_status, data.subscription_starts_at, data.custom_cycle_days]);

    const handleCycleChange = (val) => {
        let updatedStatus = data.subscription_status;
        if (val === 'lifetime') {
            updatedStatus = 'lifetime';
        } else if (val === 'trial') {
            updatedStatus = 'trial';
        } else if (updatedStatus === 'lifetime' || updatedStatus === 'trial') {
            updatedStatus = 'active';
        }

        setData((prev) => ({
            ...prev,
            subscription_plan: val,
            subscription_status: updatedStatus,
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const targetUrl = submitUrl || route('setting.business-setup.branch.update', branch.id);
        put(targetUrl, {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-xl p-6 border-slate-300 dark:border-slate-700 shadow-2xl">
                <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-foreground">
                        <div className="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <Sliders className="size-4" />
                        </div>
                        Edit Subscription & Policy — {branch.name}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Configure client billing cycle, active status, start date, and custom branch policy overrides.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* SECTION 1: Billing Cycle & Status */}
                    <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-3.5 space-y-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                            <CreditCard className="size-3.5 text-primary" />
                            Billing Cycle & Status
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField label="Subscription Billing Cycle" name="subscription_plan" required error={errors.subscription_plan}>
                                <Select
                                    value={data.subscription_plan}
                                    onValueChange={handleCycleChange}
                                >
                                    <SelectTrigger className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium">
                                        <SelectValue placeholder="Select Cycle" />
                                    </SelectTrigger>
                                    <SelectContent className="border-slate-300 dark:border-slate-700">
                                        {cycleOptions.map((cycle) => (
                                            <SelectItem key={cycle.value} value={cycle.value}>
                                                {cycle.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Subscription Status" name="subscription_status" required error={errors.subscription_status}>
                                <Select
                                    value={data.subscription_status}
                                    onValueChange={(val) => setData('subscription_status', val)}
                                >
                                    <SelectTrigger className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium">
                                        <SelectValue placeholder="Select Status" />
                                    </SelectTrigger>
                                    <SelectContent className="border-slate-300 dark:border-slate-700">
                                        <SelectItem value="active">Active (Normal Operation)</SelectItem>
                                        <SelectItem value="suspended">Suspended / Locked</SelectItem>
                                        <SelectItem value="trial">Free Trial</SelectItem>
                                        <SelectItem value="lifetime">Lifetime (No Expiry)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        {/* Custom Cycle Days Input when custom_days is selected */}
                        {data.subscription_plan === 'custom_days' && (
                            <div className="rounded-md border border-amber-200 dark:border-amber-900/50 bg-amber-50/70 dark:bg-amber-950/20 p-3 space-y-2 mt-2 animate-in fade-in-50 duration-200">
                                <div className="flex items-center gap-1.5 text-xs font-semibold text-amber-900 dark:text-amber-300">
                                    <Clock className="size-3.5 text-amber-600" />
                                    Custom Cycle Duration (Days)
                                </div>
                                <FormField label="Number of Days" name="custom_cycle_days" required error={errors.custom_cycle_days}>
                                    <Input
                                        type="number"
                                        min="1"
                                        max="3650"
                                        placeholder="e.g. 45, 60, 120"
                                        value={data.custom_cycle_days}
                                        onChange={(e) => setData('custom_cycle_days', e.target.value)}
                                        className="border-amber-300 dark:border-amber-800 bg-white dark:bg-slate-950 font-bold"
                                        required
                                    />
                                </FormField>
                                <p className="text-[0.7rem] text-amber-800 dark:text-amber-300">
                                    Expiry date will be automatically calculated as: <strong>Start Date + {data.custom_cycle_days || 0} Days</strong>.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* SECTION 2: Pricing & Timeline */}
                    <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-3.5 space-y-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                            <Calendar className="size-3.5 text-primary" />
                            Timeline & Pricing
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField label="Subscription Fee" name="subscription_fee" error={errors.subscription_fee}>
                                <Input
                                    type="number"
                                    step="0.01"
                                    placeholder="Leave empty for global fee"
                                    value={data.subscription_fee}
                                    onChange={(e) => setData('subscription_fee', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium"
                                />
                            </FormField>

                            <FormField label="Subscription Start Date" name="subscription_starts_at" error={errors.subscription_starts_at}>
                                <Input
                                    type="date"
                                    value={data.subscription_starts_at}
                                    onChange={(e) => setData('subscription_starts_at', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium"
                                />
                            </FormField>
                        </div>

                        {/* Live Calculated Expiry Badge Preview */}
                        <div className="flex items-center justify-between rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-2.5 shadow-2xs">
                            <div className="flex items-center gap-2 text-xs">
                                <Sparkles className="size-3.5 text-primary shrink-0" />
                                <span className="text-muted-foreground font-medium">Calculated Expiry / Due Date:</span>
                                <span className={`font-semibold ${calculatedExpiry.isLifetime ? 'text-indigo-600 dark:text-indigo-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                                    {calculatedExpiry.text}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* SECTION 3: Per-Branch Policy Overrides */}
                    <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-3.5 space-y-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                            <ShieldAlert className="size-3.5 text-amber-600" />
                            Custom Policy Overrides (Optional)
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField label="Custom Grace Days" name="custom_grace_period_days" error={errors.custom_grace_period_days}>
                                <Input
                                    type="number"
                                    min="0"
                                    placeholder="System default"
                                    value={data.custom_grace_period_days}
                                    onChange={(e) => setData('custom_grace_period_days', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                                />
                            </FormField>

                            <FormField label="Custom Warning Days" name="custom_warning_days" error={errors.custom_warning_days}>
                                <Input
                                    type="number"
                                    min="0"
                                    placeholder="System default"
                                    value={data.custom_warning_days}
                                    onChange={(e) => setData('custom_warning_days', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                                />
                            </FormField>
                        </div>

                        <FormField label="Overdue Action Override" name="custom_overdue_action" error={errors.custom_overdue_action}>
                            <Select
                                value={data.custom_overdue_action}
                                onValueChange={(val) => setData('custom_overdue_action', val)}
                            >
                                <SelectTrigger className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950">
                                    <SelectValue placeholder="Use Global Setting Default" />
                                </SelectTrigger>
                                <SelectContent className="border-slate-300 dark:border-slate-700">
                                    <SelectItem value="default">Use Global Setting Default</SelectItem>
                                    {overdueActions.map((oa) => (
                                        <SelectItem key={oa.value} value={oa.value}>
                                            {oa.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Subscription Notes" name="subscription_notes" error={errors.subscription_notes}>
                            <Textarea
                                rows={2}
                                placeholder="Special terms, client agreements, or custom notes..."
                                value={data.subscription_notes}
                                onChange={(e) => setData('subscription_notes', e.target.value)}
                                className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                            />
                        </FormField>
                    </div>

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
                            className="bg-primary text-primary-foreground font-semibold px-5 shadow-xs"
                        >
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
