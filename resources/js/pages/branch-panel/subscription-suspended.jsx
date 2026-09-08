import { Head, Link, usePage } from '@inertiajs/react';
import { AlertOctagon, CreditCard, Lock, LogOut, Mail, Phone, RefreshCw, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { route } from '@/lib/route';

export default function SubscriptionSuspended({ subscription }) {
    const { auth } = usePage().props;

    const {
        branch_name,
        plan,
        fee,
        expires_at,
        overdue_days,
        payment_instructions,
        superadmin_contact,
        warning_message,
    } = subscription || {};

    return (
        <>
            <Head title="Subscription Suspended" />

            <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-12 sm:px-6 lg:px-8">
                <div className="w-full max-w-xl space-y-6">
                    {/* Header Card */}
                    <div className="overflow-hidden rounded-2xl border border-rose-500/30 bg-card p-6 shadow-2xl dark:border-rose-900/50">
                        <div className="flex flex-col items-center text-center">
                            <div className="flex size-16 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-500 ring-8 ring-rose-500/10 dark:bg-rose-950/40">
                                <Lock className="size-8 animate-bounce" />
                            </div>

                            <h1 className="mt-4 text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Branch Subscription Suspended
                            </h1>
                            <p className="mt-1 text-sm font-medium text-rose-500">
                                {branch_name || 'Client Branch'} — Service Access Locked
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground max-w-md">
                                {warning_message ||
                                    'Your subscription validity and grace period have expired. Please complete your subscription payment to restore access.'}
                            </p>
                        </div>

                        {/* Subscription Info Box */}
                        <div className="mt-6 grid grid-cols-2 gap-3 rounded-xl border bg-muted/30 p-4 text-xs">
                            <div>
                                <span className="text-muted-foreground">Branch:</span>
                                <p className="font-semibold text-foreground">{branch_name || 'N/A'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Subscription Plan:</span>
                                <p className="font-semibold capitalize text-foreground">{plan || 'Standard'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Due Date:</span>
                                <p className="font-semibold text-foreground">{expires_at || 'Expired'}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Renewal Amount:</span>
                                <p className="font-bold text-foreground text-sm text-primary">
                                    {fee ? Number(fee).toFixed(2) : '0.00'}
                                </p>
                            </div>
                        </div>

                        {/* Payment Instructions */}
                        {payment_instructions && (
                            <div className="mt-4 rounded-xl border border-border bg-card p-4">
                                <h3 className="flex items-center gap-2 text-xs font-semibold text-foreground mb-2">
                                    <CreditCard className="size-4 text-blue-500" />
                                    How to Pay & Reactivate
                                </h3>
                                <pre className="whitespace-pre-wrap font-sans text-xs text-muted-foreground leading-relaxed">
                                    {payment_instructions}
                                </pre>
                            </div>
                        )}

                        {/* SuperAdmin Contact Info */}
                        {superadmin_contact && (
                            <div className="mt-4 rounded-xl border border-blue-500/20 bg-blue-950/20 p-4">
                                <h3 className="text-xs font-semibold text-foreground mb-2">
                                    SuperAdmin Contact Details
                                </h3>
                                <div className="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                    {superadmin_contact.phone && (
                                        <div className="flex items-center gap-2">
                                            <Phone className="size-3.5 text-emerald-500" />
                                            <a
                                                href={`tel:${superadmin_contact.phone}`}
                                                className="font-medium text-foreground hover:underline"
                                            >
                                                {superadmin_contact.phone}
                                            </a>
                                        </div>
                                    )}
                                    {superadmin_contact.email && (
                                        <div className="flex items-center gap-2">
                                            <Mail className="size-3.5 text-indigo-400" />
                                            <a
                                                href={`mailto:${superadmin_contact.email}`}
                                                className="font-medium text-foreground hover:underline"
                                            >
                                                {superadmin_contact.email}
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="mt-6 flex flex-col gap-2.5 sm:flex-row sm:justify-between">
                            <Button
                                variant="outline"
                                onClick={() => window.location.reload()}
                                className="text-xs flex items-center justify-center gap-1.5"
                            >
                                <RefreshCw className="size-3.5" />
                                Check Status Again
                            </Button>

                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="inline-flex items-center justify-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-xs font-medium text-muted-foreground shadow-xs hover:bg-accent hover:text-accent-foreground"
                            >
                                <LogOut className="size-3.5" />
                                Logout
                            </Link>
                        </div>
                    </div>

                    <p className="text-center text-[0.7rem] text-muted-foreground">
                        Need assistance? Contact your system superadmin for account activation.
                    </p>
                </div>
            </div>
        </>
    );
}
