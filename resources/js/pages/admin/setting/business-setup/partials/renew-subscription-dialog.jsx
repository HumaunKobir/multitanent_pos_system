import { useState, useEffect, useMemo, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Copy,
    CreditCard,
    DollarSign,
    ExternalLink,
    Eye,
    FileText,
    Image as ImageIcon,
    Info,
    Layers,
    Receipt,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    UploadCloud,
    X,
} from 'lucide-react';
import { route } from '@/lib/route';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
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

export default function RenewSubscriptionDialog({
    open,
    onOpenChange,
    branch,
    paymentMethods = [],
    submitUrl = null,
}) {
    if (!branch) return null;

    const sub = branch.subscription || {};
    const cycleDays = parseInt(sub.cycle_days, 10) || 30;
    const feePerCycle = parseFloat(sub.fee) || 1500;
    const isOverdue = Boolean(sub.is_overdue);
    const overdueDays = parseInt(sub.overdue_days, 10) || 0;
    const pendingBillsCount = isOverdue && cycleDays > 0 ? Math.max(1, Math.ceil(overdueDays / cycleDays)) : 0;
    const totalOverdueAmount = pendingBillsCount * feePerCycle;

    const pendingPayment = (sub.has_pending_payment && sub.pending_payment)
        ? sub.pending_payment
        : (branch.latest_payment?.status === 'pending' ? branch.latest_payment : null);

    const [selectedCycles, setSelectedCycles] = useState(1);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [showReceiptViewer, setShowReceiptViewer] = useState(false);
    const [copiedTrx, setCopiedTrx] = useState(false);
    const fileInputRef = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        pending_payment_id: pendingPayment?.id || null,
        duration_days: String(cycleDays),
        amount: String(feePerCycle),
        payment_method: 'cash',
        transaction_reference: '',
        paid_at: new Date().toISOString().split('T')[0],
        notes: '',
        attachment: null,
        existing_attachment_path: '',
    });

    useEffect(() => {
        if (open && branch) {
            const initialCycles = 1;
            setSelectedCycles(initialCycles);
            setPreviewUrl(null);

            if (pendingPayment) {
                const initialAmount = pendingPayment.amount ? String(pendingPayment.amount) : String(initialCycles * feePerCycle);
                const initialMethod = pendingPayment.payment_method || 'bkash';
                const initialTrx = pendingPayment.transaction_reference || '';
                const initialPaidAt = pendingPayment.paid_at || new Date().toISOString().split('T')[0];
                const initialNotes = pendingPayment.notes || '';
                const existingPath = pendingPayment.attachment_path || '';

                setData({
                    pending_payment_id: pendingPayment.id,
                    duration_days: String(initialCycles * cycleDays),
                    amount: initialAmount,
                    payment_method: initialMethod,
                    transaction_reference: initialTrx,
                    paid_at: initialPaidAt,
                    notes: initialNotes,
                    attachment: null,
                    existing_attachment_path: existingPath,
                });
            } else {
                setData({
                    pending_payment_id: null,
                    duration_days: String(initialCycles * cycleDays),
                    amount: String(initialCycles * feePerCycle),
                    payment_method: 'cash',
                    transaction_reference: '',
                    paid_at: new Date().toISOString().split('T')[0],
                    notes: '',
                    attachment: null,
                    existing_attachment_path: '',
                });
            }
        }
    }, [open, branch, cycleDays, feePerCycle, pendingPayment?.id]);

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

    const handleFileChange = (e) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('attachment', file);
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    setPreviewUrl(event.target.result);
                };
                reader.readAsDataURL(file);
            } else {
                setPreviewUrl(null);
            }
        }
    };

    const handleRemoveFile = () => {
        setData('attachment', null);
        setPreviewUrl(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const targetUrl = submitUrl || route('setting.business-setup.branch.renew', branch.id);
        post(targetUrl, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setPreviewUrl(null);
                onOpenChange(false);
            },
        });
    };

    return (
        <>
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
                    {/* Client Submitted Payment Details & Screenshot (ONLY shown if there is a pending payment awaiting approval) */}
                    {pendingPayment && (
                        <div className="rounded-xl border border-amber-300 dark:border-amber-700/60 bg-gradient-to-r from-amber-500/15 via-amber-500/10 to-transparent p-3.5 space-y-3 shadow-2xs">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-1.5 font-bold text-xs text-foreground">
                                    <Receipt className="size-4 text-amber-600 dark:text-amber-400" />
                                    <span>Client Submitted Payment Receipt</span>
                                    <Badge className="bg-amber-500 text-white font-bold text-[10px] px-2 py-0 animate-pulse">
                                        Needs Approval
                                    </Badge>
                                </div>
                                <Badge className="bg-primary text-primary-foreground text-[10px] px-2 py-0 font-bold uppercase">
                                    {pendingPayment.payment_method}
                                </Badge>
                            </div>

                            <div className="flex items-start gap-3">
                                {pendingPayment.attachment_url ? (
                                    <div className="relative group shrink-0">
                                        <img
                                            src={pendingPayment.attachment_url}
                                            alt="Submitted Receipt"
                                            className="size-16 rounded-lg object-cover border border-amber-200 dark:border-amber-800 shadow-xs cursor-pointer hover:opacity-90 transition-opacity bg-white dark:bg-slate-900"
                                            onClick={() => setShowReceiptViewer(true)}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowReceiptViewer(true)}
                                            className="absolute inset-0 flex items-center justify-center bg-black/40 text-white opacity-0 group-hover:opacity-100 rounded-lg transition-opacity text-[10px] font-bold gap-1"
                                        >
                                            <Eye className="size-3.5" /> View
                                        </button>
                                    </div>
                                ) : (
                                    <div className="size-16 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0">
                                        <FileText className="size-7" />
                                    </div>
                                )}

                                <div className="flex-1 min-w-0 space-y-1 text-xs">
                                    {pendingPayment.transaction_reference && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground text-[11px]">Trx / Ref ID:</span>
                                            <div className="flex items-center gap-1">
                                                <span className="font-mono font-extrabold text-foreground text-xs">{pendingPayment.transaction_reference}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(pendingPayment.transaction_reference);
                                                        setCopiedTrx(true);
                                                        setTimeout(() => setCopiedTrx(false), 2000);
                                                    }}
                                                    className="text-muted-foreground hover:text-primary"
                                                    title="Copy TrxID"
                                                >
                                                    {copiedTrx ? <Check className="size-3 text-emerald-500" /> : <Copy className="size-3" />}
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between text-[11px]">
                                        <span className="text-muted-foreground">Submitted Amount:</span>
                                        <span className="font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                            ৳{Number(pendingPayment.amount).toFixed(2)}
                                        </span>
                                    </div>

                                    {pendingPayment.paid_at && (
                                        <div className="flex items-center justify-between text-[11px]">
                                            <span className="text-muted-foreground">Paid Date:</span>
                                            <span className="font-medium text-foreground">{pendingPayment.paid_at}</span>
                                        </div>
                                    )}

                                    {pendingPayment.notes && (
                                        <div className="text-[11px] text-muted-foreground pt-0.5 border-t border-amber-200/50 dark:border-amber-900/40">
                                            <span className="font-semibold text-foreground">Memo:</span> {pendingPayment.notes}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {pendingPayment.attachment_url && (
                                <div className="flex items-center justify-between pt-1 border-t border-amber-200/50 dark:border-amber-900/40 text-[11px]">
                                    <button
                                        type="button"
                                        onClick={() => setShowReceiptViewer(true)}
                                        className="inline-flex items-center gap-1 font-bold text-primary hover:underline text-xs"
                                    >
                                        <Eye className="size-3.5" /> Open / Inspect Receipt Screenshot
                                    </button>
                                    <span className="text-[10px] text-muted-foreground">
                                        Recorded: {pendingPayment.created_at || 'Recent'}
                                    </span>
                                </div>
                            )}
                        </div>
                    )}

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
                    <div className="space-y-2">
                        <label className="text-xs font-bold text-foreground flex items-center justify-between">
                            <span className="flex items-center gap-1.5">
                                <Calendar className="size-3.5 text-primary" />
                                Number of Billing Cycles / Bills to Pay
                            </span>
                            <span className="text-[11px] text-muted-foreground font-normal">
                                {cycleDays} Days / Cycle
                            </span>
                        </label>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            {[1, 2, 3, 4].map((cycles) => {
                                const days = cycles * cycleDays;
                                const cost = cycles * feePerCycle;
                                const isSelected = selectedCycles === cycles;
                                const isOverdueMatch = isOverdue && pendingBillsCount === cycles;

                                return (
                                    <button
                                        key={cycles}
                                        type="button"
                                        onClick={() => handleCyclesChange(cycles)}
                                        className={`relative flex flex-col items-start justify-between rounded-xl p-2.5 text-left transition-all border ${
                                            isSelected
                                                ? 'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-xs'
                                                : 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 hover:border-slate-300 dark:hover:border-slate-700'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between w-full">
                                            <span className="text-xs font-bold text-foreground">
                                                {cycles} Bill{cycles > 1 ? 's' : ''}
                                            </span>
                                            {isSelected && (
                                                <span className="flex size-3.5 items-center justify-center rounded-full bg-primary text-primary-foreground text-[10px]">
                                                    ✓
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-[10px] text-muted-foreground mt-0.5">
                                            +{days} Days
                                        </span>
                                        <div className="mt-1.5 text-xs font-extrabold text-foreground tabular-nums">
                                            ৳{cost.toLocaleString()}
                                        </div>
                                        {isOverdueMatch && (
                                            <span className="mt-1 inline-block rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 px-1 py-0.2 text-[8px] font-bold">
                                                Clears Overdue
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        {pendingBillsCount > 4 && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="w-full mt-1.5 h-8 text-xs font-bold border-rose-300 text-rose-600 dark:border-rose-900 dark:text-rose-400"
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

                    {/* Screenshot / Receipt Upload */}
                    <div className="space-y-1.5">
                        <label className="text-xs font-semibold text-foreground flex items-center justify-between">
                            <span className="flex items-center gap-1.5">
                                <ImageIcon className="size-3.5 text-primary" />
                                Payment Receipt / Screenshot (Optional)
                            </span>
                            {data.attachment && (
                                <button
                                    type="button"
                                    onClick={handleRemoveFile}
                                    className="text-[11px] text-rose-600 hover:underline flex items-center gap-0.5"
                                >
                                    <X className="size-3" /> Remove File
                                </button>
                            )}
                        </label>

                        {!data.attachment ? (
                            <div
                                onClick={() => fileInputRef.current?.click()}
                                className="cursor-pointer rounded-lg border border-dashed border-slate-300 dark:border-slate-700 p-4 text-center hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors bg-white/50 dark:bg-slate-950/40"
                            >
                                <UploadCloud className="size-6 text-muted-foreground mx-auto mb-1.5" />
                                <p className="text-xs font-medium text-foreground">
                                    Click or drag & drop payment screenshot / receipt
                                </p>
                                <p className="text-[10px] text-muted-foreground mt-0.5">
                                    Supports PNG, JPG, JPEG, WEBP or PDF (Max 10MB)
                                </p>
                            </div>
                        ) : (
                            <div className="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 p-2.5 flex items-center gap-3">
                                {previewUrl ? (
                                    <img
                                        src={previewUrl}
                                        alt="Receipt Preview"
                                        className="size-14 rounded-md object-cover border border-slate-300 dark:border-slate-700 shrink-0"
                                    />
                                ) : (
                                    <div className="size-14 rounded-md bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400 flex items-center justify-center shrink-0">
                                        <FileText className="size-6" />
                                    </div>
                                )}
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs font-semibold text-foreground truncate">
                                        {data.attachment.name}
                                    </p>
                                    <p className="text-[10px] text-muted-foreground">
                                        {(data.attachment.size / 1024).toFixed(1)} KB • Ready for upload
                                    </p>
                                </div>
                            </div>
                        )}

                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf"
                            onChange={handleFileChange}
                            className="hidden"
                        />
                        {errors.attachment && (
                            <p className="text-[11px] text-rose-500 font-medium">{errors.attachment}</p>
                        )}
                    </div>

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
                            {processing ? 'Processing...' : pendingPayment ? 'Approve & Confirm Renewal' : 'Confirm & Renew'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        {/* Full Resolution Receipt Viewer Modal */}
        {pendingPayment?.attachment_url && (
            <Dialog open={showReceiptViewer} onOpenChange={setShowReceiptViewer}>
                <DialogContent className="max-w-2xl p-4 border-slate-300 dark:border-slate-700 shadow-2xl z-[100]">
                    <DialogHeader className="pb-2 border-b">
                        <DialogTitle className="flex items-center gap-2 text-sm font-bold">
                            <ImageIcon className="size-4 text-primary" />
                            Submitted Receipt / Deposit Slip — {branch.name}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            TrxID: <span className="font-mono font-bold text-foreground">{pendingPayment.transaction_reference || 'N/A'}</span> • Method: <span className="font-bold uppercase text-foreground">{pendingPayment.payment_method}</span> • Amount: <span className="font-extrabold text-emerald-600">৳{Number(pendingPayment.amount).toFixed(2)}</span>
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-2 max-h-[70vh] overflow-auto flex items-center justify-center bg-slate-950/5 rounded-lg p-2">
                        {pendingPayment.attachment_url.toLowerCase().endsWith('.pdf') ? (
                            <div className="text-center p-8 space-y-3">
                                <FileText className="size-16 text-rose-500 mx-auto" />
                                <p className="text-xs font-semibold">PDF Receipt Document Attached</p>
                                <Button asChild size="sm">
                                    <a href={pendingPayment.attachment_url} target="_blank" rel="noreferrer">
                                        Open PDF in New Window <ExternalLink className="size-3.5 ml-1" />
                                    </a>
                                </Button>
                            </div>
                        ) : (
                            <img
                                src={pendingPayment.attachment_url}
                                alt="Payment Receipt"
                                className="max-h-[65vh] w-auto rounded-md object-contain border shadow-sm"
                            />
                        )}
                    </div>

                    <DialogFooter className="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setShowReceiptViewer(false)}
                        >
                            Close Viewer
                        </Button>
                        <Button asChild size="sm" variant="secondary">
                            <a href={pendingPayment.attachment_url} target="_blank" rel="noreferrer">
                                Open Original <ExternalLink className="size-3.5 ml-1" />
                            </a>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        )}
        </>
    );
}

