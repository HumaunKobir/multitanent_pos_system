import { FormField } from '@/components/form-field';
import { useAppToast } from '@/contexts/app-toast-context';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { route } from '@/lib/route';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CreditCard,
    DollarSign,
    FileText,
    HelpCircle,
    Info,
    Mail,
    Phone,
    Save,
    Settings,
    Shield,
    ShieldAlert,
    Sliders,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

function SettingsSection({ title, description, children, className = '' }) {
    return (
        <section className={`overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-card shadow-xs ${className}`}>
            <div className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 px-5 py-3.5">
                <h2 className="text-sm font-bold text-foreground">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-muted-foreground leading-relaxed">{description}</p>}
            </div>
            <div className="grid gap-4 px-5 py-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

export default function BusinessSetupIndex({ settings = {}, billingCycles = [], overdueActions = [], paymentMethods = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    const [activeTab, setActiveTab] = useState('subscription');

    const { data, setData, put, processing, errors } = useForm({
        // Subscription & Billing Policies
        subscription_billing_cycle: settings.subscription_billing_cycle ?? 'monthly',
        subscription_billing_cycle_days: settings.subscription_billing_cycle_days ?? '30',
        subscription_payment_window_days: settings.subscription_payment_window_days ?? '7',
        subscription_warning_days: settings.subscription_warning_days ?? '5',
        subscription_grace_period_days: settings.subscription_grace_period_days ?? '7',
        subscription_overdue_action: settings.subscription_overdue_action ?? 'restrict_sales',
        subscription_default_fee: settings.subscription_default_fee ?? '1500',
        subscription_payment_instructions: settings.subscription_payment_instructions ?? '',
        subscription_warning_message: settings.subscription_warning_message ?? '',
        subscription_policy_terms: settings.subscription_policy_terms ?? '',

        // Superadmin Contact Info
        superadmin_contact_name: settings.superadmin_contact_name ?? '',
        superadmin_contact_phone: settings.superadmin_contact_phone ?? '',
        superadmin_contact_email: settings.superadmin_contact_email ?? '',
    });

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const submit = (e) => {
        e.preventDefault();
        put(route('setting.business-setup.update'), {
            preserveScroll: true,
        });
    };

    const handleBillingCycleChange = (val) => {
        setData((prev) => {
            let days = prev.subscription_billing_cycle_days;
            if (val === 'monthly') days = '30';
            else if (val === 'quarterly') days = '90';
            else if (val === 'yearly') days = '365';
            return {
                ...prev,
                subscription_billing_cycle: val,
                subscription_billing_cycle_days: days,
            };
        });
    };

    return (
        <div className="space-y-5 p-4 sm:p-6 max-w-6xl mx-auto">
            <Head title="Business Setup & Policies" />

            {/* Header */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl bg-gradient-to-r from-slate-900 via-slate-950 to-blue-950 px-6 py-4 shadow-xl text-white">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-white/10 text-white backdrop-blur-md border border-white/15 shadow-xs">
                        <Settings className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-base font-extrabold text-white">Business Setup & Subscription Policies</h1>
                        <p className="text-xs text-slate-300">
                            Central configuration for SaaS billing cycles, grace periods, overdue enforcement, and official payment accounts.
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        type="submit"
                        form="business-setup-form"
                        disabled={processing}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md h-9 px-4 rounded-xl"
                    >
                        <Save className="size-3.5 mr-1.5" />
                        {processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </div>
            </div>

            {/* Direct Link Banner to Dedicated Branch Clients Hub */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-2xl border border-blue-200 dark:border-blue-900/60 bg-blue-50/70 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 shadow-xs">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 items-center justify-center rounded-xl bg-blue-600 text-white shrink-0 shadow-xs">
                        <UsersRound className="size-4" />
                    </div>
                    <div>
                        <h4 className="text-xs font-bold text-foreground">Dedicated Branch Clients Management Hub</h4>
                        <p className="text-[11px] text-muted-foreground">
                            Manage client branches, view client submitted receipts, renew subscriptions, and track transaction history.
                        </p>
                    </div>
                </div>
                <Button asChild size="sm" className="h-8 text-xs bg-blue-600 hover:bg-blue-700 text-white font-bold shrink-0 rounded-xl">
                    <Link href={route('branch-clients.index')} className="flex items-center gap-1.5">
                        Open Branch Clients <ArrowRight className="size-3.5" />
                    </Link>
                </Button>
            </div>

            {/* Tabs */}
            <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                <TabsList className="grid w-full grid-cols-1 sm:grid-cols-3 h-auto p-1 bg-muted/70 border rounded-xl">
                    <TabsTrigger value="subscription" className="text-xs py-2.5 gap-2 rounded-lg font-semibold">
                        <CreditCard className="size-4 text-primary" />
                        Billing & Overdue Policies
                    </TabsTrigger>
                    <TabsTrigger value="payment_instructions" className="text-xs py-2.5 gap-2 rounded-lg font-semibold">
                        <Wallet className="size-4 text-blue-500" />
                        Payment Accounts & Contacts
                    </TabsTrigger>
                    <TabsTrigger value="policy_terms" className="text-xs py-2.5 gap-2 rounded-lg font-semibold">
                        <FileText className="size-4 text-indigo-500" />
                        Policy Terms & Agreements
                    </TabsTrigger>
                </TabsList>

                <form id="business-setup-form" onSubmit={submit} className="space-y-4">
                    {/* TAB 1: Subscription & Billing Policy */}
                    <TabsContent value="subscription" className="space-y-4 mt-0">
                        <SettingsSection
                            title="Global Subscription Billing Cycles"
                            description="Default duration and pricing applicable across client branches."
                        >
                            <FormField label="Default Billing Cycle" name="subscription_billing_cycle" error={errors.subscription_billing_cycle}>
                                <Select
                                    value={data.subscription_billing_cycle}
                                    onValueChange={handleBillingCycleChange}
                                >
                                    <SelectTrigger className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950">
                                        <SelectValue placeholder="Select Cycle" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {billingCycles.map((bc) => (
                                            <SelectItem key={bc.value} value={bc.value}>
                                                {bc.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Default Cycle Duration (Days)" name="subscription_billing_cycle_days" required error={errors.subscription_billing_cycle_days}>
                                <Input
                                    type="number"
                                    min="1"
                                    max="3650"
                                    value={data.subscription_billing_cycle_days}
                                    onChange={(e) => setData('subscription_billing_cycle_days', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-bold"
                                    required
                                />
                            </FormField>

                            <FormField label="Default Subscription Fee per Cycle (৳)" name="subscription_default_fee" required error={errors.subscription_default_fee}>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.subscription_default_fee}
                                    onChange={(e) => setData('subscription_default_fee', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-extrabold text-emerald-600 dark:text-emerald-400"
                                    required
                                />
                            </FormField>

                            <FormField label="Renewal Payment Window (Days)" name="subscription_payment_window_days" error={errors.subscription_payment_window_days}>
                                <Input
                                    type="number"
                                    min="0"
                                    max="365"
                                    value={data.subscription_payment_window_days}
                                    onChange={(e) => setData('subscription_payment_window_days', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium"
                                />
                            </FormField>
                        </SettingsSection>

                        <SettingsSection
                            title="Grace Period, Warning Alerts & Overdue Actions"
                            description="Enforcement rules when a branch subscription approaches expiry or payment is overdue."
                        >
                            <FormField label="Warning Notice Period (Days before expiry)" name="subscription_warning_days" required error={errors.subscription_warning_days}>
                                <Input
                                    type="number"
                                    min="0"
                                    max="365"
                                    value={data.subscription_warning_days}
                                    onChange={(e) => setData('subscription_warning_days', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium"
                                    required
                                />
                            </FormField>

                            <FormField label="Overdue Grace Period (Days after expiry)" name="subscription_grace_period_days" required error={errors.subscription_grace_period_days}>
                                <Input
                                    type="number"
                                    min="0"
                                    max="365"
                                    value={data.subscription_grace_period_days}
                                    onChange={(e) => setData('subscription_grace_period_days', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-medium"
                                    required
                                />
                            </FormField>

                            <FormField label="Action After Grace Period Expires" name="subscription_overdue_action" required error={errors.subscription_overdue_action} className="sm:col-span-2">
                                <Select
                                    value={data.subscription_overdue_action}
                                    onValueChange={(val) => setData('subscription_overdue_action', val)}
                                >
                                    <SelectTrigger className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-semibold">
                                        <SelectValue placeholder="Select Action" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {overdueActions.map((oa) => (
                                            <SelectItem key={oa.value} value={oa.value}>
                                                {oa.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Custom Warning Alert Message Template" name="subscription_warning_message" error={errors.subscription_warning_message} className="sm:col-span-2">
                                <Textarea
                                    rows={2}
                                    placeholder="Your branch subscription will expire in {days_left} days (Due: {due_date}). Please pay {fee_amount} to avoid service disruption."
                                    value={data.subscription_warning_message}
                                    onChange={(e) => setData('subscription_warning_message', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs"
                                />
                                <p className="mt-1 text-[0.7rem] text-muted-foreground">
                                    Variables: <code className="text-primary font-bold">{'{branch_name}'}</code>, <code className="text-primary font-bold">{'{days_left}'}</code>, <code className="text-primary font-bold">{'{due_date}'}</code>, <code className="text-primary font-bold">{'{fee_amount}'}</code>
                                </p>
                            </FormField>
                        </SettingsSection>
                    </TabsContent>

                    {/* TAB 2: Payment Instructions & Contacts */}
                    <TabsContent value="payment_instructions" className="space-y-4 mt-0">
                        <SettingsSection
                            title="Official Payment Accounts & Instructions"
                            description="Provide clear bank account details, bKash/Nagad/Rocket numbers. Branches can copy individual numbers directly with 1 click."
                        >
                            <FormField label="Official Payment Accounts (bKash, Nagad, Bank details)" name="subscription_payment_instructions" error={errors.subscription_payment_instructions} className="sm:col-span-2">
                                <Textarea
                                    rows={6}
                                    placeholder="Pay via bKash: 01700000000 (Personal)&#10;Pay via Nagad: 01800000000 (Merchant)&#10;Bank Name: City Bank | A/C Name: SoftTech IT | A/C No: 1122334455001 | Routing: 225271890 | Branch: Gulshan"
                                    value={data.subscription_payment_instructions}
                                    onChange={(e) => setData('subscription_payment_instructions', e.target.value)}
                                    className="font-mono text-xs leading-relaxed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                                />
                                <p className="mt-1 text-[0.7rem] text-muted-foreground">
                                    Tip: Enter each wallet or bank account on a new line. The client panel will automatically format them into copyable cards.
                                </p>
                            </FormField>
                        </SettingsSection>

                        <SettingsSection
                            title="SuperAdmin Support Helpline & Contact Info"
                            description="Emergency contact numbers and email displayed on renewal and suspension screens."
                        >
                            <FormField label="SuperAdmin / Organization Name" name="superadmin_contact_name" error={errors.superadmin_contact_name}>
                                <Input
                                    placeholder="e.g. POS System Central Admin"
                                    value={data.superadmin_contact_name}
                                    onChange={(e) => setData('superadmin_contact_name', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                                />
                            </FormField>

                            <FormField label="Hotline / WhatsApp Phone Number" name="superadmin_contact_phone" error={errors.superadmin_contact_phone}>
                                <Input
                                    placeholder="+8801700000000"
                                    value={data.superadmin_contact_phone}
                                    onChange={(e) => setData('superadmin_contact_phone', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 font-bold"
                                />
                            </FormField>

                            <FormField label="Support Email Address" name="superadmin_contact_email" error={errors.superadmin_contact_email} className="sm:col-span-2">
                                <Input
                                    type="email"
                                    placeholder="billing-support@example.com"
                                    value={data.superadmin_contact_email}
                                    onChange={(e) => setData('superadmin_contact_email', e.target.value)}
                                    className="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                                />
                            </FormField>
                        </SettingsSection>
                    </TabsContent>

                    {/* TAB 3: Policy Terms & Agreement */}
                    <TabsContent value="policy_terms" className="space-y-4 mt-0">
                        <SettingsSection
                            title="Master Subscription Policy & Terms of Service"
                            description="Custom terms, billing conditions, and service-level agreements given by SuperAdmin to client branches."
                        >
                            <FormField label="Master Agreement Terms & Conditions" name="subscription_policy_terms" error={errors.subscription_policy_terms} className="sm:col-span-2">
                                <Textarea
                                    rows={10}
                                    placeholder="Detail all subscription terms, billing deadlines, data retention policies, and support terms..."
                                    value={data.subscription_policy_terms}
                                    onChange={(e) => setData('subscription_policy_terms', e.target.value)}
                                    className="font-mono text-xs leading-relaxed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                                />
                            </FormField>
                        </SettingsSection>
                    </TabsContent>
                </form>
            </Tabs>
        </div>
    );
}
