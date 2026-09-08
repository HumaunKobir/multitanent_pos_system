import { usePage } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, CheckCircle2, ChevronRight, CreditCard, Info, Phone, Mail, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export function SubscriptionAlertBanner() {
    const { branchSubscription, panelType } = usePage().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [dismissed, setDismissed] = useState(false);

    if (panelType !== 'branch' || !branchSubscription || branchSubscription.is_main_branch) {
        return null;
    }

    const {
        is_expiring_soon,
        is_overdue,
        is_in_grace_period,
        is_suspended,
        is_sales_restricted,
        is_read_only,
        days_remaining,
        overdue_days,
        grace_days_remaining,
        pending_bills_count = 0,
        total_overdue_fee = 0,
        cycle_days,
        plan_label,
        expires_at,
        fee,
        plan,
        payment_instructions,
        superadmin_contact,
        overdue_action,
    } = branchSubscription;

    const isAlertActive = is_expiring_soon || is_overdue || is_in_grace_period || is_suspended || is_sales_restricted || is_read_only;

    if (!isAlertActive) {
        return null;
    }

    const isNonDismissable = is_suspended || is_in_grace_period || is_sales_restricted || is_read_only || is_overdue;

    if (dismissed && !isNonDismissable) {
        return null;
    }

    const actionDescription =
        overdue_action === 'restrict_sales'
            ? 'POS & Sales Checkout will be disabled'
            : overdue_action === 'read_only'
            ? 'Read-only mode will be enforced'
            : overdue_action === 'suspend_branch'
            ? 'Account will be fully locked'
            : 'Service warning notices will remain active';

    const isCritical = is_suspended || is_sales_restricted || is_read_only || (is_in_grace_period && grace_days_remaining <= 2) || (is_overdue && !is_in_grace_period);

    return (
        <>
            <div
                className={`relative border-b px-4 py-2.5 text-xs font-medium transition-colors ${
                    isCritical || is_in_grace_period || is_overdue
                        ? 'border-rose-500/30 bg-rose-950/90 text-rose-100'
                        : 'border-amber-500/30 bg-amber-950/80 text-amber-100 dark:bg-amber-950/90'
                }`}
            >
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-3">
                    <div className="flex items-center gap-2.5 min-w-0 flex-1">
                        {isCritical || is_in_grace_period || is_overdue ? (
                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-rose-600/30 text-rose-300 ring-2 ring-rose-500/50">
                                <AlertTriangle className="size-3.5 animate-pulse" />
                            </span>
                        ) : (
                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-600/30 text-amber-300 ring-2 ring-amber-500/50">
                                <AlertCircle className="size-3.5" />
                            </span>
                        )}

                        <div className="min-w-0 flex-1 truncate">
                            {is_suspended ? (
                                <span>
                                    <strong className="font-semibold text-white">Subscription Suspended:</strong> Grace period expired on {expires_at}. Account access is locked.
                                </span>
                            ) : is_sales_restricted ? (
                                <span>
                                    <strong className="font-semibold text-white">Sales Restricted ({overdue_days}d Overdue{pending_bills_count > 0 ? ` — ${pending_bills_count} Bill(s) Pending` : ''}):</strong>{' '}
                                    Grace period has expired. <span className="font-bold text-rose-300 underline">POS & Sales Checkout is currently disabled</span> until subscription renewal is completed.
                                </span>
                            ) : is_read_only ? (
                                <span>
                                    <strong className="font-semibold text-white">Read-Only Mode ({overdue_days}d Overdue{pending_bills_count > 0 ? ` — ${pending_bills_count} Bill(s) Pending` : ''}):</strong>{' '}
                                    Grace period has expired. Record modification and sales are blocked until subscription renewal.
                                </span>
                            ) : is_in_grace_period ? (
                                <span>
                                    <strong className="font-semibold text-white">Payment Overdue ({overdue_days}d{pending_bills_count > 0 ? ` — ${pending_bills_count} Bill(s) Pending` : ''}):</strong>{' '}
                                    You have <span className="font-bold text-rose-300 underline">{grace_days_remaining} grace day(s)</span> remaining before {actionDescription.toLowerCase()}.
                                </span>
                            ) : is_overdue ? (
                                <span>
                                    <strong className="font-semibold text-white">Payment Overdue ({overdue_days}d{pending_bills_count > 0 ? ` — ${pending_bills_count} Bill(s) Pending` : ''}):</strong>{' '}
                                    Your plan expired on {expires_at}. Please clear your fee (৳{Number(total_overdue_fee || fee).toFixed(2)}) to avoid service disruption.
                                </span>
                            ) : (
                                <span>
                                    <strong className="font-semibold text-white">Subscription Notice:</strong> Your plan expires in{' '}
                                    <span className="font-bold text-amber-300">{Math.max(0, days_remaining)} day(s)</span> on {expires_at}.
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => setModalOpen(true)}
                            className={`h-7 px-2.5 text-[0.7rem] font-semibold tracking-tight shadow-sm ${
                                isCritical || is_in_grace_period || is_overdue
                                    ? 'bg-rose-600 text-white hover:bg-rose-700'
                                    : 'bg-amber-600 text-white hover:bg-amber-700'
                            }`}
                        >
                            <CreditCard className="mr-1 size-3" />
                            Pay & Renewal Info
                        </Button>

                        {!isNonDismissable && (
                            <button
                                type="button"
                                onClick={() => setDismissed(true)}
                                className="rounded p-1 text-white/70 hover:bg-white/10 hover:text-white"
                                title="Dismiss banner"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* Payment & SuperAdmin Contact Modal */}
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base font-semibold">
                            <CreditCard className="size-5 text-primary" />
                            Subscription & Payment Details
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Current branch subscription plan, billing details, and payment instructions.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 pt-2 text-sm">
                        {/* Summary Grid */}
                        <div className="grid grid-cols-2 gap-2.5 rounded-lg border bg-muted/40 p-3 text-xs">
                            <div>
                                <span className="text-muted-foreground">Current Plan:</span>
                                <p className="font-semibold text-foreground">
                                    {plan_label || (plan === 'custom_days' ? (cycle_days ? `Custom (${cycle_days} Days)` : 'Custom Days') : plan)}
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Billing Cycle:</span>
                                <p className="font-semibold text-foreground">
                                    {cycle_days ? `${cycle_days} Days / Cycle` : 'Fixed Period'}
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Fee per Cycle:</span>
                                <p className="font-semibold text-foreground">৳{Number(fee).toFixed(2)}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Renewal / Expiry Due Date:</span>
                                <p className="font-semibold text-foreground">{expires_at || 'N/A'}</p>
                            </div>

                            {is_overdue && (
                                <div className="col-span-2 rounded border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/30 p-2 text-rose-900 dark:text-rose-200">
                                    <div className="flex items-center justify-between font-bold">
                                        <span>Overdue Bills Pending:</span>
                                        <span className="text-rose-600 dark:text-rose-400">{pending_bills_count} Unpaid Bill(s) ({overdue_days}d overdue)</span>
                                    </div>
                                    <div className="flex items-center justify-between text-[11px] pt-1 border-t border-rose-200/60 dark:border-rose-900/40 mt-1">
                                        <span>Total Overdue Amount:</span>
                                        <span className="font-extrabold text-rose-700 dark:text-rose-300">
                                            ৳{Number(total_overdue_fee || (pending_bills_count * fee)).toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                            )}

                            <div className="col-span-2 flex items-center justify-between border-t border-border pt-1.5 mt-0.5">
                                <span className="text-muted-foreground">Status:</span>
                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[0.7rem] font-bold ${
                                    is_suspended
                                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                                        : is_sales_restricted
                                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                                        : is_read_only
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                        : is_in_grace_period
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                        : is_overdue
                                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                }`}>
                                    {is_suspended
                                        ? 'Suspended'
                                        : is_sales_restricted
                                        ? 'Sales Restricted (Overdue)'
                                        : is_read_only
                                        ? 'Read-Only (Overdue)'
                                        : is_in_grace_period
                                        ? `Grace Period (${grace_days_remaining}d left)`
                                        : is_overdue
                                        ? `Payment Overdue (${pending_bills_count} Bill(s) Pending)`
                                        : 'Active'}
                                </span>
                            </div>
                        </div>

                        {/* Payment Instructions */}
                        {payment_instructions && (
                            <div className="rounded-lg border border-border bg-card p-3">
                                <h4 className="mb-1.5 text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <Info className="size-3.5 text-blue-500" />
                                    How to Pay
                                </h4>
                                <pre className="whitespace-pre-wrap font-sans text-xs text-muted-foreground leading-relaxed">
                                    {payment_instructions}
                                </pre>
                            </div>
                        )}

                        {/* SuperAdmin Contact Info */}
                        {superadmin_contact && (
                            <div className="rounded-lg border border-blue-500/20 bg-blue-950/20 p-3">
                                <h4 className="mb-2 text-xs font-semibold text-foreground">
                                    SuperAdmin Support Contact
                                </h4>
                                <div className="space-y-1 text-xs text-muted-foreground">
                                    {superadmin_contact.name && (
                                        <p><span className="font-medium text-foreground">Contact:</span> {superadmin_contact.name}</p>
                                    )}
                                    {superadmin_contact.phone && (
                                        <p className="flex items-center gap-1.5">
                                            <Phone className="size-3 text-emerald-500" />
                                            <span className="font-medium text-foreground">Phone:</span>{' '}
                                            <a href={`tel:${superadmin_contact.phone}`} className="text-primary hover:underline">
                                                {superadmin_contact.phone}
                                            </a>
                                        </p>
                                    )}
                                    {superadmin_contact.email && (
                                        <p className="flex items-center gap-1.5">
                                            <Mail className="size-3 text-indigo-400" />
                                            <span className="font-medium text-foreground">Email:</span>{' '}
                                            <a href={`mailto:${superadmin_contact.email}`} className="text-primary hover:underline">
                                                {superadmin_contact.email}
                                            </a>
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
