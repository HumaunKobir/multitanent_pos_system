import { FormField } from '@/components/form-field';
import { useAppToast } from '@/contexts/app-toast-context';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Building2,
    CreditCard,
    FileText,
    History,
    RefreshCw,
    Save,
    Search,
    Settings,
    Sliders,
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

import RenewSubscriptionDialog from './partials/renew-subscription-dialog';
import EditBranchSubscriptionDialog from './partials/edit-branch-subscription-dialog';
import PaymentHistoryDialog from './partials/payment-history-dialog';

function SettingsSection({ title, description, children, className = '' }) {
    return (
        <section className={`overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800 bg-card shadow-xs ${className}`}>
            <div className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 px-5 py-3">
                <h2 className="text-sm font-semibold text-foreground">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
            </div>
            <div className="grid gap-4 px-5 py-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

export default function BusinessSetupIndex({ settings = {}, branches = [], billingCycles = [], overdueActions = [], paymentMethods = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    const [activeTab, setActiveTab] = useState('subscription');
    const [searchBranch, setSearchBranch] = useState('');
    const [selectedBranch, setSelectedBranch] = useState(null);
    const [renewModalOpen, setRenewModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [historyModalOpen, setHistoryModalOpen] = useState(false);

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

    const openRenewModal = (branch) => {
        setSelectedBranch(branch);
        setRenewModalOpen(true);
    };

    const openEditModal = (branch) => {
        setSelectedBranch(branch);
        setEditModalOpen(true);
    };

    const openHistoryModal = (branch) => {
        setSelectedBranch(branch);
        setHistoryModalOpen(true);
    };

    const filteredBranches = branches.filter((b) =>
        b.name?.toLowerCase().includes(searchBranch.toLowerCase()) ||
        b.phone?.toLowerCase().includes(searchBranch.toLowerCase())
    );

    const getStatusBadge = (sub, isMain) => {
        if (isMain) {
            return <Badge className="bg-purple-600 text-white hover:bg-purple-700">Central Main Branch</Badge>;
        }

        const status = sub?.computed_status || sub?.status || 'active';

        if (status === 'lifetime') {
            return <Badge className="bg-indigo-600 text-white hover:bg-indigo-700">Lifetime</Badge>;
        }
        if (status === 'suspended') {
            return <Badge className="bg-rose-600 text-white hover:bg-rose-700">Suspended / Locked</Badge>;
        }
        if (status === 'grace_period') {
            return <Badge className="bg-orange-600 text-white hover:bg-orange-700">In Grace Period ({sub.grace_days_remaining}d left)</Badge>;
        }
        if (status === 'expiring_soon') {
            return <Badge className="bg-amber-600 text-white hover:bg-amber-700">Expiring Soon ({sub.days_remaining}d left)</Badge>;
        }
        if (status === 'trial') {
            return <Badge className="bg-blue-600 text-white hover:bg-blue-700">Free Trial</Badge>;
        }

        return <Badge className="bg-emerald-600 text-white hover:bg-emerald-700">Active</Badge>;
    };

    return (
        <>
            <Head title="Business Setup & Subscriptions" />

            <div className="px-2 py-1">
                {/* Header */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-lg bg-blue-950 px-5 py-3.5 shadow-md">
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 items-center justify-center rounded-lg bg-white/15 text-white shadow-xs">
                            <Settings className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-base font-bold text-white">Business Setup & Subscriptions</h1>
                            <p className="text-xs text-white/70">
                                SaaS subscription policies, payment windows, warning alerts, grace periods, and client branch renewals.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="submit"
                            form="business-setup-form"
                            disabled={processing}
                            className="bg-emerald-600 hover:bg-emerald-700 text-xs font-semibold shadow-xs"
                        >
                            <Save className="size-3.5 mr-1.5" />
                            {processing ? 'Saving...' : 'Save All Settings'}
                        </Button>
                    </div>
                </div>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                    <TabsList className="grid w-full grid-cols-1 sm:grid-cols-3 h-auto p-1 bg-muted/60 border">
                        <TabsTrigger value="subscription" className="text-xs py-2 gap-1.5">
                            <CreditCard className="size-3.5" />
                            Subscription & Billing Policy
                        </TabsTrigger>
                        <TabsTrigger value="branches" className="text-xs py-2 gap-1.5">
                            <Building2 className="size-3.5" />
                            Branch Clients ({branches.length})
                        </TabsTrigger>
                        <TabsTrigger value="policy_terms" className="text-xs py-2 gap-1.5">
                            <FileText className="size-3.5" />
                            Policy Terms & Agreement
                        </TabsTrigger>
                    </TabsList>

                    <form id="business-setup-form" onSubmit={submit} className="space-y-4">
                        {/* TAB 1: Subscription & Billing Policy */}
                        <TabsContent value="subscription" className="space-y-4 mt-0">
                            <SettingsSection
                                title="Subscription Billing & Payment Cycle"
                                description="Define default subscription validity, payment windows, and warning alert thresholds for client branches."
                            >
                                <FormField label="Billing Cycle Type" name="subscription_billing_cycle" required error={errors.subscription_billing_cycle}>
                                    <Select
                                        value={data.subscription_billing_cycle}
                                        onValueChange={handleBillingCycleChange}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Cycle" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {billingCycles.map((cycle) => (
                                                <SelectItem key={cycle.value} value={cycle.value}>
                                                    {cycle.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField label="Billing Cycle Duration (Days)" name="subscription_billing_cycle_days" required error={errors.subscription_billing_cycle_days}>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={data.subscription_billing_cycle_days}
                                        onChange={(e) => setData('subscription_billing_cycle_days', e.target.value)}
                                        required
                                    />
                                </FormField>

                                <FormField label="Payment Window (Days to Pay)" name="subscription_payment_window_days" required error={errors.subscription_payment_window_days}>
                                    <Input
                                        type="number"
                                        min="0"
                                        placeholder="e.g. 7"
                                        value={data.subscription_payment_window_days}
                                        onChange={(e) => setData('subscription_payment_window_days', e.target.value)}
                                        required
                                    />
                                    <p className="mt-1 text-[0.7rem] text-muted-foreground">
                                        Number of days after invoice generation client branch is allowed to pay.
                                    </p>
                                </FormField>

                                <FormField label="Standard Subscription Fee" name="subscription_default_fee" required error={errors.subscription_default_fee}>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="1500.00"
                                        value={data.subscription_default_fee}
                                        onChange={(e) => setData('subscription_default_fee', e.target.value)}
                                        required
                                    />
                                </FormField>
                            </SettingsSection>

                            <SettingsSection
                                title="Warning Alert Threshold & Grace Period Rules"
                                description="Control when clients start seeing warning notifications and how overdue accounts are restricted."
                            >
                                <FormField label="Warning Alert Threshold (Days Before Due)" name="subscription_warning_days" required error={errors.subscription_warning_days}>
                                    <Input
                                        type="number"
                                        min="0"
                                        placeholder="e.g. 5"
                                        value={data.subscription_warning_days}
                                        onChange={(e) => setData('subscription_warning_days', e.target.value)}
                                        required
                                    />
                                    <p className="mt-1 text-[0.7rem] text-muted-foreground">
                                        Displays top alert banner on client dashboard and POS X days before expiry.
                                    </p>
                                </FormField>

                                <FormField label="Grace Period Duration (Days After Due)" name="subscription_grace_period_days" required error={errors.subscription_grace_period_days}>
                                    <Input
                                        type="number"
                                        min="0"
                                        placeholder="e.g. 7"
                                        value={data.subscription_grace_period_days}
                                        onChange={(e) => setData('subscription_grace_period_days', e.target.value)}
                                        required
                                    />
                                    <p className="mt-1 text-[0.7rem] text-muted-foreground">
                                        Allows client to keep using the system for X days after due date before restriction is enforced.
                                    </p>
                                </FormField>

                                <FormField label="Overdue Enforcement Action" name="subscription_overdue_action" required error={errors.subscription_overdue_action} className="sm:col-span-2">
                                    <Select
                                        value={data.subscription_overdue_action}
                                        onValueChange={(val) => setData('subscription_overdue_action', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Overdue Action" />
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
                                    />
                                    <p className="mt-1 text-[0.7rem] text-muted-foreground">
                                        Variables: <code className="text-primary">{'{branch_name}'}</code>, <code className="text-primary">{'{days_left}'}</code>, <code className="text-primary">{'{due_date}'}</code>, <code className="text-primary">{'{fee_amount}'}</code>
                                    </p>
                                </FormField>
                            </SettingsSection>

                            <SettingsSection
                                title="SuperAdmin Contact & Payment Methods"
                                description="Payment instructions and contact numbers displayed to client branches on renewal and suspension screens."
                            >
                                <FormField label="SuperAdmin / Organization Name" name="superadmin_contact_name" error={errors.superadmin_contact_name}>
                                    <Input
                                        placeholder="e.g. System SuperAdmin"
                                        value={data.superadmin_contact_name}
                                        onChange={(e) => setData('superadmin_contact_name', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Support Phone Number" name="superadmin_contact_phone" error={errors.superadmin_contact_phone}>
                                    <Input
                                        placeholder="+8801700000000"
                                        value={data.superadmin_contact_phone}
                                        onChange={(e) => setData('superadmin_contact_phone', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Support Email Address" name="superadmin_contact_email" error={errors.superadmin_contact_email} className="sm:col-span-2">
                                    <Input
                                        type="email"
                                        placeholder="admin@coolness.com"
                                        value={data.superadmin_contact_email}
                                        onChange={(e) => setData('superadmin_contact_email', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Payment Methods & Instructions" name="subscription_payment_instructions" error={errors.subscription_payment_instructions} className="sm:col-span-2">
                                    <Textarea
                                        rows={4}
                                        placeholder="Bank details, bKash/Nagad merchant or personal numbers..."
                                        value={data.subscription_payment_instructions}
                                        onChange={(e) => setData('subscription_payment_instructions', e.target.value)}
                                    />
                                </FormField>
                            </SettingsSection>
                        </TabsContent>

                        {/* TAB 2: Branch Clients & Subscriptions */}
                        <TabsContent value="branches" className="space-y-4 mt-0">
                            <div className="rounded-lg border bg-card p-4">
                                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 className="text-sm font-bold text-foreground">Branch Clients Subscription Manager</h3>
                                        <p className="text-xs text-muted-foreground">
                                            Track client subscription status, due dates, renew subscriptions, and record payments.
                                        </p>
                                    </div>
                                    <div className="relative w-full sm:w-64">
                                        <Search className="absolute left-2.5 top-2.5 size-3.5 text-muted-foreground" />
                                        <Input
                                            placeholder="Search branches..."
                                            value={searchBranch}
                                            onChange={(e) => setSearchBranch(e.target.value)}
                                            className="h-8 pl-8 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 font-semibold text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-3">#</th>
                                                <th className="px-3 py-3">Branch Client</th>
                                                <th className="px-3 py-3">Billing Cycle & Fee</th>
                                                <th className="px-3 py-3">Due / Expiry Date</th>
                                                <th className="px-3 py-3">Status</th>
                                                <th className="px-3 py-3 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                            {filteredBranches.map((branch, index) => {
                                                const sub = branch.subscription || {};
                                                const planKey = sub.plan || 'monthly';
                                                const cycleNameMap = {
                                                    monthly: 'Monthly (30d)',
                                                    quarterly: 'Quarterly (90d)',
                                                    half_yearly: 'Half-Yearly (180d)',
                                                    yearly: 'Yearly (365d)',
                                                    trial: 'Free Trial (14d)',
                                                    lifetime: 'Lifetime',
                                                    custom_days: 'Custom Days',
                                                    basic: 'Monthly (30d)',
                                                    standard: 'Monthly (30d)',
                                                    premium: 'Quarterly (90d)',
                                                    enterprise: 'Yearly (365d)',
                                                };
                                                const cycleLabel = cycleNameMap[planKey] || planKey;

                                                return (
                                                    <tr key={branch.id} className="hover:bg-muted/20 transition-colors">
                                                        <td className="px-3 py-3 text-muted-foreground">{index + 1}</td>
                                                        <td className="px-3 py-3">
                                                            <div className="font-semibold text-foreground flex items-center gap-1.5">
                                                                <Building2 className="size-3.5 text-primary" />
                                                                {branch.name}
                                                            </div>
                                                            {branch.phone && (
                                                                <div className="text-[0.7rem] text-muted-foreground">{branch.phone}</div>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <span className="font-medium text-foreground">{cycleLabel}</span>
                                                            <div className="text-[0.7rem] text-emerald-600 dark:text-emerald-400 font-semibold">
                                                                {sub.fee ? Number(sub.fee).toFixed(2) : '0.00'}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            {branch.is_main_branch ? (
                                                                <span className="text-muted-foreground">Unlimited</span>
                                                            ) : (
                                                                <div>
                                                                    <div className="font-medium text-foreground">{sub.expires_at || 'Not Set'}</div>
                                                                    <div className={`text-[0.7rem] ${
                                                                        sub.is_overdue
                                                                            ? 'text-rose-500 font-semibold'
                                                                            : sub.is_expiring_soon
                                                                            ? 'text-amber-500 font-medium'
                                                                            : 'text-muted-foreground'
                                                                    }`}>
                                                                        {sub.is_overdue
                                                                            ? `${sub.overdue_days}d overdue`
                                                                            : `${sub.days_remaining}d remaining`}
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            {getStatusBadge(sub, branch.is_main_branch)}
                                                        </td>
                                                        <td className="px-3 py-3 text-right">
                                                            {!branch.is_main_branch && (
                                                                <div className="flex items-center justify-end gap-1.5">
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() => openRenewModal(branch)}
                                                                        className="h-7 px-2 text-[0.7rem] font-semibold text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 border-slate-300 dark:border-slate-700"
                                                                        title="Renew Subscription & Record Payment"
                                                                    >
                                                                        <RefreshCw className="size-3 mr-1" />
                                                                        Renew
                                                                    </Button>

                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() => openEditModal(branch)}
                                                                        className="h-7 px-2 text-[0.7rem] border-slate-300 dark:border-slate-700 font-medium"
                                                                        title="Edit Billing Cycle, Status & Overrides"
                                                                    >
                                                                        <Sliders className="size-3 mr-1" />
                                                                        Config
                                                                    </Button>

                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        onClick={() => openHistoryModal(branch)}
                                                                        className="h-7 px-1.5 text-muted-foreground hover:text-foreground"
                                                                        title="Payment History"
                                                                    >
                                                                        <History className="size-3.5" />
                                                                    </Button>
                                                                </div>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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

            {/* Modals */}
            <RenewSubscriptionDialog
                open={renewModalOpen}
                onOpenChange={setRenewModalOpen}
                branch={selectedBranch}
                paymentMethods={paymentMethods}
            />

            <EditBranchSubscriptionDialog
                open={editModalOpen}
                onOpenChange={setEditModalOpen}
                branch={selectedBranch}
                billingCycles={billingCycles}
                overdueActions={overdueActions}
            />

            <PaymentHistoryDialog
                open={historyModalOpen}
                onOpenChange={setHistoryModalOpen}
                branch={selectedBranch}
            />
        </>
    );
}
