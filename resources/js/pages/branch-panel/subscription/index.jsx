import { useState, useMemo, useRef, useEffect } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    CreditCard,
    DollarSign,
    ExternalLink,
    Eye,
    FileText,
    HelpCircle,
    History,
    Image as ImageIcon,
    Info,
    Layers,
    Mail,
    Phone,
    Receipt,
    RefreshCw,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    UploadCloud,
    Wallet,
    X,
} from 'lucide-react';
import { route } from '@/lib/route';
import { useAppToast } from '@/contexts/app-toast-context';
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
import InvoicesHistoryDialog from '@/pages/admin/setting/business-setup/partials/invoices-history-dialog';
import SecurityDepositDialog from '@/pages/admin/setting/business-setup/partials/security-deposit-dialog';

const PAYMENT_METHOD_CONFIG = {
    bkash: {
        label: 'bKash',
        badgeColor: 'bg-[#e2136e]/10 text-[#e2136e] border-[#e2136e]/30',
        activeBtn: 'bg-[#e2136e] text-white shadow-md shadow-[#e2136e]/25',
        cardBg: 'from-[#e2136e]/10 via-[#e2136e]/5 to-transparent border-[#e2136e]/30',
        dotColor: 'bg-[#e2136e]',
    },
    nagad: {
        label: 'Nagad',
        badgeColor: 'bg-[#f7941d]/10 text-[#f7941d] border-[#f7941d]/30',
        activeBtn: 'bg-[#f7941d] text-white shadow-md shadow-[#f7941d]/25',
        cardBg: 'from-[#f7941d]/10 via-[#f7941d]/5 to-transparent border-[#f7941d]/30',
        dotColor: 'bg-[#f7941d]',
    },
    rocket: {
        label: 'Rocket',
        badgeColor: 'bg-[#8c3494]/10 text-[#8c3494] border-[#8c3494]/30',
        activeBtn: 'bg-[#8c3494] text-white shadow-md shadow-[#8c3494]/25',
        cardBg: 'from-[#8c3494]/10 via-[#8c3494]/5 to-transparent border-[#8c3494]/30',
        dotColor: 'bg-[#8c3494]',
    },
    bank: {
        label: 'Bank Transfer',
        badgeColor: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30',
        activeBtn: 'bg-blue-600 text-white shadow-md shadow-blue-500/25',
        cardBg: 'from-blue-500/10 via-blue-500/5 to-transparent border-blue-500/30',
        dotColor: 'bg-blue-600',
    },
    cash: {
        label: 'Cash Handover',
        badgeColor: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
        activeBtn: 'bg-emerald-600 text-white shadow-md shadow-emerald-500/25',
        cardBg: 'from-emerald-500/10 via-emerald-500/5 to-transparent border-emerald-500/30',
        dotColor: 'bg-emerald-600',
    },
    other: {
        label: 'Other Channel',
        badgeColor: 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/30',
        activeBtn: 'bg-slate-700 text-white shadow-md shadow-slate-700/25',
        cardBg: 'from-slate-500/10 via-slate-500/5 to-transparent border-slate-500/30',
        dotColor: 'bg-slate-600',
    },
};

export function getChannelStyle(channelName = '') {
    const lower = String(channelName).toLowerCase();
    if (lower.includes('bkash')) {
        return {
            dotColor: 'bg-[#e2136e]',
            initial: 'bK',
            badgeBg: 'bg-[#e2136e]/10 text-[#e2136e] border-[#e2136e]/30',
        };
    }
    if (lower.includes('nagad')) {
        return {
            dotColor: 'bg-[#f7941d]',
            initial: 'Ng',
            badgeBg: 'bg-[#f7941d]/10 text-[#f7941d] border-[#f7941d]/30',
        };
    }
    if (lower.includes('ssl') || lower.includes('card')) {
        return {
            dotColor: 'bg-[#0284c7]',
            initial: 'SSL',
            badgeBg: 'bg-[#0284c7]/10 text-[#0284c7] border-[#0284c7]/30',
        };
    }
    if (lower.includes('rocket')) {
        return {
            dotColor: 'bg-[#8c3494]',
            initial: 'Rk',
            badgeBg: 'bg-[#8c3494]/10 text-[#8c3494] border-[#8c3494]/30',
        };
    }
    if (lower.includes('bank')) {
        return {
            dotColor: 'bg-[#4f46e5]',
            initial: 'Bk',
            badgeBg: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/30',
        };
    }
    if (lower.includes('cash')) {
        return {
            dotColor: 'bg-[#059669]',
            initial: '৳',
            badgeBg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
        };
    }
    return {
        dotColor: 'bg-slate-700 dark:bg-slate-600',
        initial: channelName ? channelName.charAt(0).toUpperCase() : '•',
        badgeBg: 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/30',
    };
}

/**
 * Intelligent parser that extracts structured payment accounts (bKash, Nagad, Rocket, Bank, numbers)
 * from raw instruction text so users can copy individual numbers directly.
 */
export function parsePaymentInstructions(text) {
    if (!text || typeof text !== 'string') return { accounts: [], generalNotes: [] };

    const rawLines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
    const accounts = [];
    const generalNotes = [];

    let currentBank = null;

    const flushCurrentBank = () => {
        if (currentBank && (currentBank.accountNumber || currentBank.bankName)) {
            accounts.push({
                id: `acc-bank-${accounts.length}`,
                method: 'bank',
                type: currentBank.type || 'Bank Transfer',
                title: currentBank.bankName || 'Bank Account',
                accountName: currentBank.accountName || '',
                number: currentBank.accountNumber || '',
                routingNumber: currentBank.routingNumber || '',
                branchName: currentBank.branchName || '',
                fullText: currentBank.rawText || '',
            });
            currentBank = null;
        }
    };

    for (let i = 0; i < rawLines.length; i++) {
        const idx = i;
        const line = rawLines[i];
        const lower = line.toLowerCase();

        // Check if part of a multi-line bank account description
        const isBankLine =
            lower.startsWith('bank name:') ||
            lower.startsWith('bank:') ||
            lower.startsWith('a/c no') ||
            lower.startsWith('a/c number') ||
            lower.startsWith('account no') ||
            lower.startsWith('account number') ||
            lower.startsWith('routing:') ||
            lower.startsWith('branch:') ||
            lower.startsWith('account name:') ||
            lower.startsWith('a/c name:');

        if (isBankLine) {
            if (!currentBank) {
                currentBank = { rawText: line };
            } else {
                currentBank.rawText += '\n' + line;
            }

            if (lower.includes('bank name:') || lower.includes('bank:')) {
                currentBank.bankName = line.replace(/^(?:bank\s*name|bank)\s*[:\-–—]\s*/i, '').trim();
            }
            if (lower.includes('account name:') || lower.includes('a/c name:')) {
                currentBank.accountName = line.replace(/^(?:account\s*name|a\/c\s*name)\s*[:\-–—]\s*/i, '').trim();
            }
            if (lower.includes('account no') || lower.includes('account number') || lower.includes('a/c no') || lower.includes('a/c number')) {
                const match = line.match(/(?:account\s*(?:no\.?|number|num)?|a\/?c\s*(?:no\.?|number|num)?)\s*[:\-–—]?\s*([0-9A-Za-z\-]+)/i);
                if (match) currentBank.accountNumber = match[1].trim();
            }
            if (lower.includes('routing')) {
                const match = line.match(/routing\s*(?:no\.?|number)?\s*[:\-–—]?\s*([0-9A-Za-z]+)/i);
                if (match) currentBank.routingNumber = match[1].trim();
            }
            if (lower.includes('branch')) {
                currentBank.branchName = line.replace(/^(?:branch\s*name|branch)\s*[:\-–—]\s*/i, '').trim();
            }
            continue;
        } else if (currentBank) {
            flushCurrentBank();
        }

        // Single line parsing
        const bdPhoneMatch = line.match(/(?:(?:\+?88)?01[3-9]\d{8})/);
        const rocketMatch = line.match(/(?:(?:\+?88)?01[3-9]\d{8}[-–—]?\d)/);
        const acNoMatch = line.match(/(?:a\/?c\s*(?:no\.?|number|num)?|account\s*(?:no\.?|number)?|acc\s*#?)\s*[:\-–—]?\s*([0-9A-Za-z\-]{8,25})/i);
        const routingMatch = line.match(/(?:routing\s*(?:no\.?|number)?|swift|ifsc)\s*[:\-–—]?\s*([0-9A-Za-z]{6,15})/i);
        const genericLongNum = line.match(/\b(\d{10,20})\b/);

        let method = null;
        if (lower.includes('bkash')) method = 'bkash';
        else if (lower.includes('nagad')) method = 'nagad';
        else if (lower.includes('rocket')) method = 'rocket';
        else if (lower.includes('upay')) method = 'upay';
        else if (
            lower.includes('bank') ||
            lower.includes('islami') ||
            lower.includes('city bank') ||
            lower.includes('brac') ||
            lower.includes('dutch bangla') ||
            lower.includes('ebl') ||
            lower.includes('eastern bank') ||
            lower.includes('sonali') ||
            lower.includes('dhaka bank') ||
            lower.includes('southeast') ||
            lower.includes('prime bank') ||
            lower.includes('ucb')
        ) {
            method = 'bank';
        }

        let type = '';
        if (lower.includes('merchant')) type = 'Merchant';
        else if (lower.includes('personal')) type = 'Personal';
        else if (lower.includes('agent')) type = 'Agent';
        else if (lower.includes('current')) type = 'Current Account';
        else if (lower.includes('savings')) type = 'Savings Account';

        if (method === 'bank' || acNoMatch || routingMatch) {
            const bankNameMatch = line.match(/(?:bank(?:\s+name)?\s*[:\-–—]?\s*)([^|,\n]+)/i);
            const acNameMatch = line.match(/(?:a\/?c\s*(?:name|title)|account\s*name)\s*[:\-–—]?\s*([^|,\n]+)/i);
            const branchMatch = line.match(/(?:branch(?:\s+name)?)\s*[:\-–—]?\s*([^|,\n]+)/i);

            const accountNumber = acNoMatch ? acNoMatch[1].trim() : (genericLongNum ? genericLongNum[1] : (bdPhoneMatch ? bdPhoneMatch[0] : ''));
            const routingNumber = routingMatch ? routingMatch[1].trim() : '';
            const bankName = bankNameMatch ? bankNameMatch[1].trim() : (method === 'bank' ? 'Bank Transfer' : 'Bank Account');
            const accountName = acNameMatch ? acNameMatch[1].trim() : '';
            const branchName = branchMatch ? branchMatch[1].trim() : '';

            accounts.push({
                id: `acc-bank-single-${idx}`,
                method: 'bank',
                type: type || 'Bank Transfer',
                title: bankName,
                accountName,
                number: accountNumber,
                routingNumber,
                branchName,
                fullText: line,
            });
        } else if (method === 'bkash' || method === 'nagad' || method === 'rocket' || method === 'upay' || bdPhoneMatch || genericLongNum) {
            const num = (method === 'rocket' && rocketMatch) ? rocketMatch[0] : (bdPhoneMatch ? bdPhoneMatch[0] : (genericLongNum ? genericLongNum[1] : ''));
            let label = PAYMENT_METHOD_CONFIG[method]?.label || (method ? method.toUpperCase() : 'Account');

            accounts.push({
                id: `acc-wallet-${idx}-${method || 'other'}`,
                method: method || 'other',
                type: type || (method === 'bkash' || method === 'nagad' || method === 'rocket' ? 'Personal' : 'Payment Account'),
                title: label,
                number: num,
                fullText: line,
            });
        } else {
            generalNotes.push(line);
        }
    }

    if (currentBank) {
        flushCurrentBank();
    }

    return { accounts, generalNotes };
}

export default function BranchSubscriptionIndex({
    branch = {},
    subscription = {},
    payments = [],
    paymentMethods = [],
}) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const cycleDays = parseInt(subscription.cycle_days, 10) || 30;
    const feePerCycle = parseFloat(subscription.fee) || 1500;
    const isOverdue = Boolean(subscription.is_overdue);
    const overdueDays = parseInt(subscription.overdue_days, 10) || 0;
    const pendingBillsCount = isOverdue && cycleDays > 0 ? Math.max(1, Math.ceil(overdueDays / cycleDays)) : 0;
    const totalOverdueAmount = pendingBillsCount * feePerCycle;

    const [selectedCycles, setSelectedCycles] = useState(isOverdue && pendingBillsCount > 0 ? pendingBillsCount : 1);
    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
    const [isInvoicesModalOpen, setIsInvoicesModalOpen] = useState(false);
    const [isSecurityModalOpen, setIsSecurityModalOpen] = useState(false);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [previewAttachment, setPreviewAttachment] = useState(null);
    const [copiedId, setCopiedId] = useState(null);
    const fileInputRef = useRef(null);

    const parsedInstructions = useMemo(() => {
        return parsePaymentInstructions(subscription.payment_instructions);
    }, [subscription.payment_instructions]);

    const defaultPaymentMethod = paymentMethods[0]?.value || 'Cash in Hand';

    const { data, setData, post, processing, errors, reset } = useForm({
        duration_days: String((isOverdue && pendingBillsCount > 0 ? pendingBillsCount : 1) * cycleDays),
        amount: String((isOverdue && pendingBillsCount > 0 ? pendingBillsCount : 1) * feePerCycle),
        payment_method: defaultPaymentMethod,
        payment_account_id: paymentMethods[0]?.id ? String(paymentMethods[0].id) : '',
        transaction_reference: '',
        paid_at: new Date().toISOString().split('T')[0],
        notes: '',
        attachment: null,
    });

    useEffect(() => {
        if (paymentMethods.length === 0) {
            return;
        }

        if (!data.payment_method) {
            setData('payment_method', paymentMethods[0].value);
        }

        if (!data.payment_account_id && paymentMethods[0]?.id) {
            setData('payment_account_id', String(paymentMethods[0].id));
        }
    }, [paymentMethods]);

    const extensionPreview = useMemo(() => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let baseDate = new Date(today);
        if (subscription.expires_at) {
            const currentExp = new Date(subscription.expires_at);
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
    }, [subscription.expires_at, data.duration_days, cycleDays]);

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

    const handleCopy = (textToCopy, label = 'Number', idKey) => {
        if (!textToCopy) return;
        navigator.clipboard.writeText(textToCopy);
        setCopiedId(idKey);
        toast.success(`${label} copied: ${textToCopy}`);
        setTimeout(() => {
            setCopiedId((prev) => (prev === idKey ? null : prev));
        }, 2200);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('branch-panel.subscription.pay'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setPreviewUrl(null);
                setIsPaymentModalOpen(false);
            },
        });
    };

    return (
        <div className="space-y-6 p-4 sm:p-6 max-w-7xl mx-auto">
            <Head title="Subscription & Billing Hub" />

            {/* Page Header */}
            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 p-6 text-white shadow-xl">
                <div className="absolute right-0 top-0 -mt-10 -mr-10 h-64 w-64 rounded-full bg-blue-500/10 blur-3xl" />
                <div className="absolute left-1/3 bottom-0 -mb-10 h-48 w-48 rounded-full bg-indigo-500/10 blur-2xl" />

                <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-0.5 text-xs font-semibold backdrop-blur-md border border-white/15">
                                <Building2 className="size-3.5 text-blue-300" />
                                {branch.name || 'Branch Outlet'}
                            </span>
                            <span className="text-xs text-blue-200/80 font-mono">ID: #{branch.id}</span>
                        </div>
                        <h1 className="text-xl sm:text-2xl font-extrabold tracking-tight text-white flex items-center gap-2.5">
                            Subscription & Billing Hub
                        </h1>
                        <p className="text-xs text-blue-100/70 max-w-2xl leading-relaxed">
                            Monitor subscription validity, view outstanding bills, download receipts, and submit renewal payments with proof screenshots.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="text-right sm:block hidden">
                            <p className="text-[11px] text-blue-200/70 uppercase tracking-wider font-semibold">Current Plan</p>
                            <p className="text-sm font-bold text-white">{subscription.plan_label || 'Standard'}</p>
                        </div>
                        <div className="h-10 w-px bg-white/15 sm:block hidden" />
                        <div className="rounded-xl bg-white/10 p-3 backdrop-blur-md border border-white/15 text-center min-w-[110px]">
                            <p className="text-[10px] text-blue-200 uppercase tracking-wider font-bold">Status</p>
                            <p className="text-xs font-extrabold capitalize text-white">
                                {subscription.computed_status?.replace('_', ' ') || 'Active'}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsInvoicesModalOpen(true)}
                            className="bg-white/10 hover:bg-white/20 text-white border-white/20 font-bold text-xs h-10 px-3 rounded-xl backdrop-blur-md flex items-center gap-1.5"
                        >
                            <FileText className="size-4" />
                            Invoices & Advance
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsSecurityModalOpen(true)}
                            className="bg-white/10 hover:bg-white/20 text-white border-white/20 font-bold text-xs h-10 px-3 rounded-xl backdrop-blur-md flex items-center gap-1.5"
                        >
                            <Shield className="size-4" />
                            Security Deposit
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setIsPaymentModalOpen(true)}
                            className="bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm px-4 h-10 rounded-xl shadow-lg flex items-center gap-2 ml-1"
                        >
                            <CreditCard className="size-4" />
                            Submit Subscription Payment
                        </Button>
                    </div>
                </div>
            </div>

            {/* Pending Payment Approval Notice Banner */}
            {subscription.has_pending_payment && subscription.pending_payment && (
                <div className="relative overflow-hidden rounded-2xl border border-amber-500/50 bg-gradient-to-r from-amber-500/15 via-amber-500/10 to-transparent p-5 text-foreground shadow-md backdrop-blur-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-start gap-3.5">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-amber-500 text-white shadow-md shrink-0">
                                <Clock className="size-6 animate-pulse" />
                            </div>
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-base font-bold text-amber-600 dark:text-amber-400">
                                        Payment Submitted — Awaiting SuperAdmin Approval
                                    </h3>
                                    <Badge className="bg-amber-500 text-white text-[11px] font-bold px-2.5 py-0.5">
                                        Pending Verification
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground leading-relaxed">
                                    You submitted a deposit of <strong className="text-foreground">৳{Number(subscription.pending_payment.amount).toFixed(2)}</strong> via <strong className="text-foreground uppercase">{subscription.pending_payment.payment_method}</strong> (TrxID: <span className="font-mono font-bold text-foreground">{subscription.pending_payment.transaction_reference || 'N/A'}</span>). The SuperAdmin will verify your receipt and extend your subscription validity.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className="flex flex-col sm:items-end justify-center rounded-xl bg-amber-500/10 border border-amber-500/20 px-4 py-2.5 shrink-0">
                                <span className="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400 tracking-wider">
                                    Submitted Deposit
                                </span>
                                <span className="text-xl font-extrabold text-amber-600 dark:text-amber-400 tabular-nums">
                                    ৳{Number(subscription.pending_payment.amount).toFixed(2)}
                                </span>
                                <span className="text-[10px] text-muted-foreground">
                                    {subscription.pending_payment.created_at || 'Recently submitted'}
                                </span>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsPaymentModalOpen(true)}
                                className="border-amber-500/50 text-amber-700 dark:text-amber-300 text-xs font-bold hover:bg-amber-500/10 shrink-0 h-10 px-3"
                            >
                                <RefreshCw className="size-3.5 mr-1" /> Submit Another
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Overdue Warning Alert - High Polish */}
            {isOverdue && (
                <div className="relative overflow-hidden rounded-2xl border border-rose-500/40 bg-gradient-to-r from-rose-500/15 via-rose-500/10 to-rose-500/5 p-5 text-foreground shadow-lg backdrop-blur-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-start gap-3.5">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-rose-600 text-white shadow-md shrink-0">
                                <AlertTriangle className="size-6 animate-pulse" />
                            </div>
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-base font-bold text-rose-600 dark:text-rose-400">
                                        Subscription Payment Overdue
                                    </h3>
                                    <Badge className="bg-rose-600 text-white text-[11px] font-bold px-2.5 py-0.5 shadow-xs">
                                        {pendingBillsCount} Unpaid Bill{pendingBillsCount > 1 ? 's' : ''} ({overdueDays} Days Past Due)
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground leading-relaxed">
                                    {subscription.warning_message || `Your branch subscription expired on ${subscription.expires_at}.`}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className="flex flex-col sm:items-end justify-center rounded-xl bg-rose-500/10 dark:bg-rose-950/40 border border-rose-500/20 px-4 py-2.5 shrink-0">
                                <span className="text-[10px] uppercase font-bold text-rose-600 dark:text-rose-400 tracking-wider">
                                    Total Outstanding Due
                                </span>
                                <span className="text-xl font-extrabold text-rose-600 dark:text-rose-400 tabular-nums">
                                    ৳{totalOverdueAmount.toFixed(2)}
                                </span>
                                <span className="text-[10px] text-muted-foreground">
                                    {pendingBillsCount} cycle{pendingBillsCount > 1 ? 's' : ''} × ৳{feePerCycle.toFixed(2)}
                                </span>
                            </div>
                            <Button
                                type="button"
                                onClick={() => {
                                    handleCyclesChange(pendingBillsCount > 0 ? pendingBillsCount : 1);
                                    setIsPaymentModalOpen(true);
                                }}
                                className="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-md px-4 h-10 rounded-xl flex items-center gap-2 shrink-0"
                            >
                                <CreditCard className="size-4" /> Pay Overdue Bills Now
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Expiring Soon Notice */}
            {!isOverdue && subscription.is_expiring_soon && (
                <div className="rounded-2xl border border-amber-500/40 bg-gradient-to-r from-amber-500/15 via-amber-500/10 to-transparent p-5 text-foreground shadow-md">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-center gap-3.5">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm shrink-0">
                                <Clock className="size-5" />
                            </div>
                            <div className="space-y-0.5">
                                <h3 className="text-sm font-bold text-amber-600 dark:text-amber-400">
                                    Subscription Expiring in {subscription.days_remaining} Day(s)
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Your validity ends on <strong>{subscription.expires_at}</strong>. Renew early to maintain uninterrupted POS sales & inventory access.
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            onClick={() => setIsPaymentModalOpen(true)}
                            className="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-md px-4 h-9 rounded-xl flex items-center gap-2 shrink-0"
                        >
                            <CreditCard className="size-4" /> Renew Early Now
                        </Button>
                    </div>
                </div>
            )}

            {/* KPI Overview Cards - Modern Elevated Style */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {/* Card 1 */}
                <div className="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-card p-5 shadow-xs transition-all hover:shadow-md hover:border-primary/40">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Plan & Frequency</span>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/70 dark:text-blue-400">
                            <Layers className="size-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="text-lg font-extrabold text-foreground">
                            {subscription.plan_label || subscription.plan || 'Standard'}
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                            <span>Cycle Fee:</span>
                            <span className="font-bold text-foreground text-sm tabular-nums">৳{feePerCycle.toFixed(2)}</span>
                        </div>
                    </div>
                </div>

                {/* Card 2 */}
                <div className="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-card p-5 shadow-xs transition-all hover:shadow-md hover:border-primary/40">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Expiry & Validity</span>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/70 dark:text-emerald-400">
                            <Calendar className="size-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-center justify-between">
                            <span className="text-lg font-extrabold text-foreground tabular-nums">
                                {subscription.expires_at || 'Unlimited'}
                            </span>
                            {isOverdue ? (
                                <Badge className="bg-rose-600 text-white font-bold border-none text-xs">
                                    Overdue ({overdueDays}d)
                                </Badge>
                            ) : subscription.is_expiring_soon ? (
                                <Badge className="bg-amber-500 text-white font-bold border-none text-xs">
                                    {subscription.days_remaining}d Left
                                </Badge>
                            ) : (
                                <Badge className="bg-emerald-600 text-white font-bold border-none text-xs">
                                    Active ({subscription.days_remaining}d)
                                </Badge>
                            )}
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                            <span>Last Paid:</span>
                            <span className="font-medium text-foreground">{subscription.last_paid_at || '—'}</span>
                        </div>
                    </div>
                </div>

                {/* Card 3 */}
                <div className="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-card p-5 shadow-xs transition-all hover:shadow-md hover:border-primary/40">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Bills & Overdue</span>
                        <div className="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/70 dark:text-indigo-400">
                            <Receipt className="size-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-center justify-between">
                            <span className="text-lg font-extrabold text-foreground">
                                {pendingBillsCount} Pending Bill{pendingBillsCount === 1 ? '' : 's'}
                            </span>
                            <span className={`text-sm font-bold tabular-nums ${isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                                {isOverdue ? `৳${totalOverdueAmount.toFixed(2)} Due` : 'Clear'}
                            </span>
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                            <span>Policy Enforcement:</span>
                            <span className="font-semibold text-foreground capitalize">
                                {subscription.overdue_action ? subscription.overdue_action.replace('_', ' ') : 'None'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Interactive Workspace (2 Columns) */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {/* Left: Quick Payment Action Card & Support Contact (5 Cols) */}
                <div className="lg:col-span-5 space-y-4">
                    {/* Quick Pay CTA Card */}
                    <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-gradient-to-br from-card to-emerald-500/5 p-6 shadow-sm space-y-5">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md">
                                <CreditCard className="size-5" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-foreground">Renew & Submit Payment</h2>
                                <p className="text-xs text-muted-foreground">
                                    Send payment deposit to official accounts & submit verification proof
                                </p>
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 p-3.5 space-y-2 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground font-medium">Standard Billing Rate:</span>
                                <span className="font-bold text-foreground">৳{feePerCycle.toFixed(2)} / {cycleDays} Days</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground font-medium">Current Expiry:</span>
                                <span className="font-bold text-foreground">{subscription.expires_at || 'Unlimited'}</span>
                            </div>
                            {isOverdue && (
                                <div className="flex items-center justify-between text-rose-600 dark:text-rose-400 font-bold pt-1 border-t border-rose-200/50 dark:border-rose-900/40">
                                    <span>Outstanding Dues:</span>
                                    <span>৳{totalOverdueAmount.toFixed(2)} ({pendingBillsCount} Bills)</span>
                                </div>
                            )}
                        </div>

                        <Button
                            type="button"
                            onClick={() => setIsPaymentModalOpen(true)}
                            className="w-full h-12 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-md transition-all rounded-xl flex items-center justify-center gap-2"
                        >
                            <CreditCard className="size-4" />
                            Submit Subscription Payment
                        </Button>
                    </div>

                    {/* Support Contact Card */}
                    <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-card shadow-sm p-5 space-y-3">
                        <div className="flex items-center gap-2 pb-2 border-b border-slate-200 dark:border-slate-800">
                            <div className="flex size-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400">
                                <HelpCircle className="size-4" />
                            </div>
                            <h3 className="text-sm font-bold text-foreground">Billing Support Helpline</h3>
                        </div>

                        <div className="space-y-2.5 text-xs">
                            {subscription.superadmin_contact?.name && (
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Admin Officer:</span>
                                    <span className="font-bold text-foreground">{subscription.superadmin_contact.name}</span>
                                </div>
                            )}
                            {subscription.superadmin_contact?.phone && (
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground flex items-center gap-1.5">
                                        <Phone className="size-3.5 text-primary" /> Hotline / WhatsApp:
                                    </span>
                                    <a
                                        href={`tel:${subscription.superadmin_contact.phone}`}
                                        className="font-extrabold text-primary hover:underline"
                                    >
                                        {subscription.superadmin_contact.phone}
                                    </a>
                                </div>
                            )}
                            {subscription.superadmin_contact?.email && (
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground flex items-center gap-1.5">
                                        <Mail className="size-3.5 text-primary" /> Email:
                                    </span>
                                    <a
                                        href={`mailto:${subscription.superadmin_contact.email}`}
                                        className="font-medium text-primary hover:underline truncate max-w-[180px]"
                                    >
                                        {subscription.superadmin_contact.email}
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Right: Official Payment Instructions & Accounts (7 Cols) */}
                <div className="lg:col-span-7 space-y-4">
                    {/* Official Payment Instructions & Accounts */}
                    <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-card shadow-sm p-6 space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                            <div className="flex items-center gap-2.5">
                                <div className="flex size-8 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400">
                                    <Wallet className="size-4" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-bold text-foreground">Official Payment Accounts</h3>
                                    <p className="text-[11px] text-muted-foreground">Click Copy to copy account/phone numbers directly</p>
                                </div>
                            </div>
                            {parsedInstructions.accounts.length > 0 && (
                                <Badge variant="outline" className="text-xs text-muted-foreground border-slate-300 dark:border-slate-700">
                                    {parsedInstructions.accounts.length} Account{parsedInstructions.accounts.length === 1 ? '' : 's'}
                                </Badge>
                            )}
                        </div>

                        {/* List of Parsed Payment Account Cards */}
                        {parsedInstructions.accounts.length > 0 ? (
                            <div className="space-y-3">
                                {parsedInstructions.accounts.map((acc) => {
                                    const isBank = acc.method === 'bank';
                                    const isBkash = acc.method === 'bkash';
                                    const isNagad = acc.method === 'nagad';
                                    const isRocket = acc.method === 'rocket';

                                    const numberCopied = copiedId === `${acc.id}-num`;
                                    const routingCopied = copiedId === `${acc.id}-routing`;

                                    return (
                                        <div
                                            key={acc.id}
                                            className={`group relative rounded-xl border p-3.5 transition-all shadow-xs ${
                                                isBkash
                                                    ? 'bg-gradient-to-br from-[#e2136e]/10 via-[#e2136e]/5 to-transparent border-[#e2136e]/30 hover:border-[#e2136e]/60'
                                                    : isNagad
                                                    ? 'bg-gradient-to-br from-[#f7941d]/10 via-[#f7941d]/5 to-transparent border-[#f7941d]/30 hover:border-[#f7941d]/60'
                                                    : isRocket
                                                    ? 'bg-gradient-to-br from-[#8c3494]/10 via-[#8c3494]/5 to-transparent border-[#8c3494]/30 hover:border-[#8c3494]/60'
                                                    : 'bg-gradient-to-br from-blue-500/10 via-blue-500/5 to-transparent border-blue-500/30 hover:border-blue-500/60'
                                            }`}
                                        >
                                            {/* Top: Account Title & Quick Select */}
                                            <div className="flex items-center justify-between gap-2 mb-2">
                                                <div className="flex items-center gap-2">
                                                    {isBank ? (
                                                        <div className="flex size-7 items-center justify-center rounded-lg bg-blue-600 text-white shadow-2xs">
                                                            <Building2 className="size-4" />
                                                        </div>
                                                    ) : (
                                                        <div className={`flex size-7 items-center justify-center rounded-lg font-black text-xs text-white shadow-2xs ${
                                                            isBkash ? 'bg-[#e2136e]' : isNagad ? 'bg-[#f7941d]' : isRocket ? 'bg-[#8c3494]' : 'bg-slate-700'
                                                        }`}>
                                                            {acc.title.charAt(0)}
                                                        </div>
                                                    )}
                                                    <div>
                                                        <h4 className="text-xs font-bold text-foreground leading-none">{acc.title}</h4>
                                                        <span className="text-[10px] text-muted-foreground font-medium">{acc.type}</span>
                                                    </div>
                                                </div>

                                                {PAYMENT_METHOD_CONFIG[acc.method] && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setData('payment_method', acc.method);
                                                            setIsPaymentModalOpen(true);
                                                        }}
                                                        className={`text-[10px] font-bold px-2 py-0.5 rounded-md transition-all border ${
                                                            data.payment_method === acc.method
                                                                ? 'bg-primary text-primary-foreground border-primary shadow-2xs'
                                                                : 'bg-background/80 text-muted-foreground border-border hover:text-foreground'
                                                        }`}
                                                    >
                                                        Pay via {PAYMENT_METHOD_CONFIG[acc.method].label}
                                                    </button>
                                                )}
                                            </div>

                                            {/* Account / Phone Number Box with Dedicated Copy Button */}
                                            {acc.number ? (
                                                <div className="flex items-center justify-between gap-2 rounded-lg bg-background/90 dark:bg-slate-950/80 p-2 border border-border/70 mt-2">
                                                    <div className="min-w-0 flex-1">
                                                        <span className="text-[9px] uppercase tracking-wider text-muted-foreground font-bold block">
                                                            {isBank ? 'Account Number' : 'Account / Phone Number'}
                                                        </span>
                                                        <span className="font-mono text-sm font-black text-foreground tracking-wide select-all">
                                                            {acc.number}
                                                        </span>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() => handleCopy(acc.number, isBank ? 'Account number' : `${acc.title} number`, `${acc.id}-num`)}
                                                        className={`h-7 px-2.5 text-[11px] font-bold rounded-md transition-all shrink-0 ${
                                                            numberCopied
                                                                ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                                                : 'bg-primary hover:bg-primary/90 text-primary-foreground shadow-2xs'
                                                        }`}
                                                    >
                                                        {numberCopied ? (
                                                            <>
                                                                <Check className="size-3 mr-1" /> Copied!
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Copy className="size-3 mr-1" /> Copy Number
                                                            </>
                                                        )}
                                                    </Button>
                                                </div>
                                            ) : (
                                                <p className="text-xs text-muted-foreground font-mono mt-1">{acc.fullText}</p>
                                            )}

                                            {/* Bank Account Details Breakdown */}
                                            {isBank && (acc.accountName || acc.branchName || acc.routingNumber) && (
                                                <div className="mt-2.5 grid grid-cols-1 gap-1.5 pt-2 border-t border-border/50 text-[11px]">
                                                    {acc.accountName && (
                                                        <div className="flex items-center justify-between text-muted-foreground">
                                                            <span>A/C Holder Name:</span>
                                                            <span className="font-semibold text-foreground">{acc.accountName}</span>
                                                        </div>
                                                    )}
                                                    {acc.branchName && (
                                                        <div className="flex items-center justify-between text-muted-foreground">
                                                            <span>Branch:</span>
                                                            <span className="font-semibold text-foreground">{acc.branchName}</span>
                                                        </div>
                                                    )}
                                                    {acc.routingNumber && (
                                                        <div className="flex items-center justify-between rounded-md bg-background/60 p-1.5 border border-border/50 mt-1">
                                                            <div>
                                                                <span className="text-[9px] uppercase text-muted-foreground font-bold block">Routing Number</span>
                                                                <span className="font-mono font-bold text-foreground text-xs">{acc.routingNumber}</span>
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => handleCopy(acc.routingNumber, 'Routing number', `${acc.id}-routing`)}
                                                                className={`h-6 px-2 text-[10px] font-semibold ${
                                                                    routingCopied ? 'border-emerald-500 text-emerald-600' : ''
                                                                }`}
                                                            >
                                                                {routingCopied ? (
                                                                    <>
                                                                        <Check className="size-2.5 mr-1" /> Copied
                                                                    </>
                                                                ) : (
                                                                    <>
                                                                        <Copy className="size-2.5 mr-1" /> Copy Routing
                                                                    </>
                                                                )}
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : subscription.payment_instructions ? (
                            <div className="whitespace-pre-line text-foreground/90 bg-slate-50 dark:bg-slate-900/60 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs leading-relaxed">
                                {subscription.payment_instructions}
                            </div>
                        ) : (
                            <div className="text-muted-foreground text-xs p-4 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">
                                Send subscription payment to the SuperAdmin accounts and upload screenshot receipt above.
                            </div>
                        )}

                        {/* General Notes */}
                        {parsedInstructions.generalNotes.length > 0 && (
                            <div className="rounded-xl bg-slate-100/70 dark:bg-slate-900/60 p-3 border border-slate-200 dark:border-slate-800 text-xs space-y-1">
                                <span className="text-[10px] uppercase font-bold text-muted-foreground block">Additional Payment Notes:</span>
                                {parsedInstructions.generalNotes.map((note, nIdx) => (
                                    <p key={nIdx} className="text-muted-foreground text-[11px] leading-relaxed">
                                        {note}
                                    </p>
                                ))}
                            </div>
                        )}

                        <div className="rounded-xl bg-blue-50/60 dark:bg-blue-950/30 p-3 border border-blue-200/60 dark:border-blue-900/40 text-[11px] text-blue-900 dark:text-blue-200 space-y-1">
                            <p className="font-semibold flex items-center gap-1.5">
                                <ShieldCheck className="size-3.5 text-blue-600 dark:text-blue-400" /> Fast Verification
                            </p>
                            <p className="text-muted-foreground leading-tight">
                                Enter the exact Transaction ID (TrxID) and upload a receipt screenshot so the admin can reconcile payments immediately.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Dialog: Submit Subscription Payment */}
            <Dialog open={isPaymentModalOpen} onOpenChange={setIsPaymentModalOpen}>
                <DialogContent className="max-w-2xl p-6 border-slate-300 dark:border-slate-700 shadow-2xl max-h-[92vh] overflow-y-auto">
                    <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                        <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-foreground">
                            <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs">
                                <CreditCard className="size-4" />
                            </div>
                            Submit Subscription Payment — {branch.name}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Select billing cycles, provide your payment transaction details, and upload receipt screenshot for SuperAdmin verification.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                        {/* Interactive Cycle Selector */}
                        <div className="space-y-2">
                            <label className="text-xs font-bold text-foreground flex items-center justify-between">
                                <span className="flex items-center gap-1.5">
                                    <Calendar className="size-3.5 text-primary" />
                                    Select Number of Bills / Cycles to Pay
                                </span>
                                <span className="text-[11px] text-muted-foreground font-normal">
                                    {cycleDays} Days per Bill Cycle
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
                                            className={`relative flex flex-col items-start justify-between rounded-xl p-3 text-left transition-all border ${
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
                                                    <span className="flex size-4 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                        <Check className="size-2.5 stroke-[3]" />
                                                    </span>
                                                )}
                                            </div>
                                            <span className="text-[11px] text-muted-foreground mt-0.5">
                                                +{days} Days
                                            </span>
                                            <div className="mt-2 text-sm font-extrabold text-foreground tabular-nums">
                                                ৳{cost.toLocaleString()}
                                            </div>
                                            {isOverdueMatch && (
                                                <span className="mt-1 inline-block rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 px-1.5 py-0.2 text-[9px] font-bold">
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
                                    className="w-full h-8 text-xs font-bold border-rose-300 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400"
                                    onClick={() => handleCyclesChange(pendingBillsCount)}
                                >
                                    Pay All {pendingBillsCount} Pending Overdue Bills ({pendingBillsCount * cycleDays}d — ৳{totalOverdueAmount.toFixed(2)})
                                </Button>
                            )}
                        </div>

                        {/* Live Extension Timeline Preview Box */}
                        <div className={`rounded-xl border p-4 transition-colors ${
                            extensionPreview.isStillOverdue
                                ? 'border-amber-300/70 bg-amber-50/70 dark:border-amber-900/50 dark:bg-amber-950/20'
                                : 'border-emerald-300/70 bg-emerald-50/70 dark:border-emerald-900/50 dark:bg-emerald-950/20'
                        }`}>
                            <div className="flex items-center justify-between text-xs font-bold">
                                <span className="flex items-center gap-1.5 text-foreground">
                                    <Sparkles className="size-3.5 text-primary" /> Extension Timeline Preview
                                </span>
                                <Badge className={extensionPreview.isStillOverdue ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white'}>
                                    +{extensionPreview.days} Days Validity
                                </Badge>
                            </div>

                            <div className="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                                <div>
                                    <p className="text-[10px] text-muted-foreground uppercase font-semibold">Previous Expiry</p>
                                    <p className="font-bold text-foreground tabular-nums">{extensionPreview.fromDate}</p>
                                </div>
                                <div className="flex flex-col items-center">
                                    <ArrowRight className="size-4 text-muted-foreground" />
                                    <span className="text-[9px] text-muted-foreground font-semibold">Continuous</span>
                                </div>
                                <div className="text-right">
                                    <p className="text-[10px] text-muted-foreground uppercase font-semibold">New Extended Expiry</p>
                                    <p className="font-extrabold text-primary text-sm tabular-nums">{extensionPreview.toDate}</p>
                                </div>
                            </div>

                            <div className="mt-2 text-[11px] pt-1">
                                {extensionPreview.isStillOverdue ? (
                                    <p className="text-amber-800 dark:text-amber-200 flex items-center gap-1.5 font-medium">
                                        <AlertCircle className="size-3.5 text-amber-600 shrink-0" />
                                        <span>
                                            Will remain <strong>{extensionPreview.remainingDaysOverdue}d overdue ({extensionPreview.remainingBillsUnpaid} bill(s) remaining)</strong> after this payment.
                                        </span>
                                    </p>
                                ) : (
                                    <p className="text-emerald-800 dark:text-emerald-200 flex items-center gap-1.5 font-medium">
                                        <CheckCircle2 className="size-3.5 text-emerald-600 shrink-0" />
                                        <span>
                                            Account will be <strong>fully active and valid for {extensionPreview.futureActiveDays} day(s)</strong> ahead.
                                        </span>
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Payment Method Selector Grid */}
                        <div className="space-y-2">
                            <label className="text-xs font-bold text-foreground flex items-center justify-between">
                                <span className="flex items-center gap-1.5">
                                    <CreditCard className="size-3.5 text-primary" />
                                    Payment Channel (Asset Account)
                                </span>
                                <span className="text-[10px] text-muted-foreground font-normal">
                                    Choose channel to record payment
                                </span>
                            </label>
                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                {paymentMethods.map((pm) => {
                                    const isSelected = pm.id
                                        ? String(data.payment_account_id) === String(pm.id)
                                        : data.payment_method === pm.value || data.payment_method === pm.label;
                                    const style = getChannelStyle(pm.value || pm.label);

                                    return (
                                        <button
                                            key={pm.id || pm.value}
                                            type="button"
                                            onClick={() => {
                                                setData('payment_method', pm.value);
                                                setData('payment_account_id', pm.id ? String(pm.id) : '');
                                            }}
                                            className={`relative flex items-center gap-2 rounded-xl p-2 text-left transition-all border ${
                                                isSelected
                                                    ? 'border-primary bg-primary/10 ring-2 ring-primary/20 shadow-xs dark:bg-primary/15'
                                                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-foreground hover:border-slate-300 dark:hover:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900/50'
                                            }`}
                                        >
                                            <div className={`flex size-6.5 shrink-0 items-center justify-center rounded-lg text-white font-extrabold text-[10px] shadow-2xs ${style.dotColor}`}>
                                                {style.initial}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-[11px] font-bold text-foreground truncate leading-tight">
                                                    {pm.label}
                                                </p>
                                                {pm.code && (
                                                    <span className="text-[8.5px] font-mono text-muted-foreground block truncate">
                                                        {pm.code}
                                                    </span>
                                                )}
                                            </div>
                                            {isSelected && (
                                                <div className="flex size-3.5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                    <Check className="size-2 stroke-[3]" />
                                                </div>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                            {errors.payment_method && (
                                <p className="text-[11px] text-rose-500">{errors.payment_method}</p>
                            )}
                            {errors.payment_account_id && (
                                <p className="text-[11px] text-rose-500">{errors.payment_account_id}</p>
                            )}
                        </div>

                        {/* Amount & Date & TrxID */}
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <FormField label="Amount to Pay (৳)" name="amount" required error={errors.amount}>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    className="font-extrabold text-emerald-600 dark:text-emerald-400 text-sm bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700"
                                    required
                                />
                            </FormField>

                            <FormField label="Transaction / TrxID" name="transaction_reference" error={errors.transaction_reference}>
                                <Input
                                    placeholder="e.g. 9B37FD9X1"
                                    value={data.transaction_reference}
                                    onChange={(e) => setData('transaction_reference', e.target.value)}
                                    className="font-mono text-xs uppercase bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700"
                                />
                            </FormField>

                            <FormField label="Payment Date" name="paid_at" required error={errors.paid_at}>
                                <Input
                                    type="date"
                                    value={data.paid_at}
                                    onChange={(e) => setData('paid_at', e.target.value)}
                                    className="text-xs font-medium bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700"
                                    required
                                />
                            </FormField>
                        </div>

                        {/* Screenshot / Receipt File Upload Dropzone */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-bold text-foreground flex items-center justify-between">
                                <span className="flex items-center gap-1.5">
                                    <ImageIcon className="size-3.5 text-primary" />
                                    Upload Receipt / Screenshot / Deposit Slip (Optional)
                                </span>
                                {data.attachment && (
                                    <button
                                        type="button"
                                        onClick={handleRemoveFile}
                                        className="text-[11px] text-rose-600 hover:underline flex items-center gap-0.5 font-medium"
                                    >
                                        <X className="size-3" /> Remove File
                                    </button>
                                )}
                            </label>

                            {!data.attachment ? (
                                <div
                                    onClick={() => fileInputRef.current?.click()}
                                    className="group cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-primary/60 p-5 text-center transition-all bg-slate-50/50 dark:bg-slate-900/30 hover:bg-primary/5"
                                >
                                    <div className="mx-auto flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition-transform">
                                        <UploadCloud className="size-5" />
                                    </div>
                                    <p className="mt-2 text-xs font-semibold text-foreground">
                                        Click or drop payment screenshot here
                                    </p>
                                    <p className="text-[10px] text-muted-foreground mt-0.5">
                                        PNG, JPG, WEBP, or PDF (Max 10MB)
                                    </p>
                                </div>
                            ) : (
                                <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/50 p-3 flex items-center gap-3.5">
                                    {previewUrl ? (
                                        <img
                                            src={previewUrl}
                                            alt="Receipt Preview"
                                            className="size-16 rounded-xl object-cover border border-slate-300 dark:border-slate-700 shadow-xs shrink-0"
                                        />
                                    ) : (
                                        <div className="size-16 rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400 flex items-center justify-center shrink-0">
                                            <FileText className="size-7" />
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs font-bold text-foreground truncate">
                                            {data.attachment.name}
                                        </p>
                                        <p className="text-[10px] text-muted-foreground mt-0.5">
                                            {(data.attachment.size / 1024).toFixed(1)} KB • Ready for upload
                                        </p>
                                        <Badge variant="outline" className="mt-1 text-[9px] px-1.5 py-0 border-emerald-500/30 text-emerald-600">
                                            File Attached
                                        </Badge>
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

                        {/* Notes / Memo */}
                        <FormField label="Notes / Memo (Optional)" name="notes" error={errors.notes}>
                            <Textarea
                                rows={2}
                                placeholder="Additional details or reference remarks..."
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                className="text-xs bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700"
                            />
                        </FormField>

                        <DialogFooter className="pt-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsPaymentModalOpen(false)}
                                disabled={processing}
                                className="border-slate-300 dark:border-slate-700"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-md transition-all px-5"
                            >
                                <RefreshCw className={`size-4 mr-2 ${processing ? 'animate-spin' : ''}`} />
                                {processing ? 'Submitting Payment...' : 'Submit Payment for Admin Approval'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Complete Payment & Transaction History Table */}
            <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-card shadow-sm overflow-hidden">
                <div className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 px-6 py-4 flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-blue-600 text-white shadow-xs">
                            <History className="size-4" />
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-foreground">Subscription Bill & Transaction History</h3>
                            <p className="text-[11px] text-muted-foreground">
                                Audit trail of all renewals, transaction IDs, and uploaded receipts
                            </p>
                        </div>
                    </div>
                    <Badge variant="outline" className="text-xs border-slate-300 dark:border-slate-700">
                        {payments.length} Transaction{payments.length === 1 ? '' : 's'}
                    </Badge>
                </div>

                {payments.length === 0 ? (
                    <div className="p-12 text-center text-xs text-muted-foreground">
                        No subscription payment records found for this branch yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-200 dark:border-slate-800 bg-slate-100/70 dark:bg-slate-900/60 font-semibold text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3">Date Paid</th>
                                    <th className="px-5 py-3">Amount</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">Method</th>
                                    <th className="px-5 py-3">Validity Period</th>
                                    <th className="px-5 py-3">Trx / Reference ID</th>
                                    <th className="px-5 py-3">Receipt Document</th>
                                    <th className="px-5 py-3">Recorded By</th>
                                    <th className="px-5 py-3">Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800 bg-card">
                                {payments.map((p) => {
                                    const isApproved = p.status === 'approved';
                                    const isPending = p.status === 'pending';
                                    return (
                                        <tr key={p.id} className="hover:bg-muted/30 transition-colors">
                                            <td className="px-5 py-3.5 font-medium text-foreground whitespace-nowrap">
                                                {p.paid_at}
                                            </td>
                                            <td className="px-5 py-3.5 font-extrabold text-emerald-600 dark:text-emerald-400 whitespace-nowrap tabular-nums">
                                                ৳{Number(p.amount).toFixed(2)}
                                            </td>
                                            <td className="px-5 py-3.5 whitespace-nowrap">
                                                {isApproved ? (
                                                    <Badge className="bg-emerald-600 text-white font-bold text-[0.65rem] px-2 py-0.5 border-none">
                                                        Approved
                                                    </Badge>
                                                ) : isPending ? (
                                                    <Badge className="bg-amber-500 text-white font-bold text-[0.65rem] px-2 py-0.5 border-none animate-pulse">
                                                        Pending Admin Approval
                                                    </Badge>
                                                ) : (
                                                    <Badge className="bg-rose-600 text-white font-bold text-[0.65rem] px-2 py-0.5 border-none capitalize">
                                                        {p.status}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 uppercase whitespace-nowrap">
                                                <Badge variant="outline" className="text-[0.65rem] px-2 py-0.5 border-slate-300 dark:border-slate-700 font-bold">
                                                    {p.payment_method}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3.5 text-muted-foreground whitespace-nowrap">
                                                {p.billing_period_starts_at} → {p.billing_period_ends_at}
                                            </td>
                                            <td className="px-5 py-3.5 text-muted-foreground font-mono text-[0.75rem] max-w-[140px] truncate" title={p.transaction_reference}>
                                                {p.transaction_reference || '—'}
                                            </td>
                                            <td className="px-5 py-3.5 whitespace-nowrap">
                                                {p.attachment_url ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreviewAttachment(p)}
                                                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950/70 dark:text-blue-300 border border-blue-200 dark:border-blue-900 text-xs font-semibold hover:bg-blue-100 transition-colors shadow-2xs"
                                                    >
                                                        <Eye className="size-3.5" />
                                                        <span>View Receipt</span>
                                                    </button>
                                                ) : (
                                                    <span className="text-muted-foreground text-[11px]">No Receipt</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-muted-foreground whitespace-nowrap">
                                                {p.recorded_by}
                                            </td>
                                            <td className="px-5 py-3.5 text-muted-foreground text-[11px] max-w-[180px] truncate" title={p.notes}>
                                                {p.notes || '—'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Receipt Modal Preview */}
            <Dialog open={Boolean(previewAttachment)} onOpenChange={(val) => !val && setPreviewAttachment(null)}>
                <DialogContent className="max-w-xl p-6 border-slate-300 dark:border-slate-700 shadow-2xl">
                    <DialogHeader className="pb-3 border-b border-slate-200 dark:border-slate-800">
                        <DialogTitle className="flex items-center gap-2 text-base font-bold text-foreground">
                            <ImageIcon className="size-4 text-primary" /> Payment Receipt Preview
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            TrxID: <strong>{previewAttachment?.transaction_reference || 'N/A'}</strong> • Amount: <strong>৳{Number(previewAttachment?.amount || 0).toFixed(2)}</strong> ({previewAttachment?.payment_method})
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-4 flex flex-col items-center justify-center">
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
                            <div className="max-h-[60vh] overflow-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-900/10 p-1 w-full">
                                <img
                                    src={previewAttachment?.attachment_url}
                                    alt="Payment Receipt"
                                    className="max-h-[55vh] w-auto rounded-lg object-contain mx-auto"
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

            {/* Invoices & Advance History Dialog */}
            <InvoicesHistoryDialog
                open={isInvoicesModalOpen}
                onOpenChange={setIsInvoicesModalOpen}
                branch={branch}
                fetchUrl={route('branch-panel.subscription.invoices')}
            />

            {/* Security Deposit History Dialog */}
            <SecurityDepositDialog
                open={isSecurityModalOpen}
                onOpenChange={setIsSecurityModalOpen}
                branch={branch}
                paymentMethods={paymentMethods}
                fetchUrl={route('branch-panel.subscription.security-deposits')}
            />
        </div>
    );
}
