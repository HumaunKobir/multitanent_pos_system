import { formatBdDate, toDateInputValue } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Paperclip,
    Printer,
    Receipt,
    RotateCcw,
    Search,
    ShieldAlert,
    Wallet,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    MoneyCell,
    ReportDateInput,
    ReportFilterField,
    ReportFilterReset,
    ReportPage,
    ReportSelect,
    useLiveReportFilters,
} from '@/pages/admin/reports/_shared/report-shell';
import { AdminPagination } from '@/components/admin/pagination';

function StatusBadge({ status }) {
    switch (status) {
        case 'approved':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300">
                    <CheckCircle2 className="size-3" />
                    Approved
                </span>
            );
        case 'pending':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/80 dark:text-amber-300">
                    <Clock className="size-3" />
                    Pending Approval
                </span>
            );
        case 'rejected':
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950/80 dark:text-rose-300">
                    <XCircle className="size-3" />
                    Rejected
                </span>
            );
        default:
            return (
                <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    {status}
                </span>
            );
    }
}

function StatCard({ title, amount, subtitle, icon: Icon, tone = 'default' }) {
    const toneStyles = {
        default: 'border-slate-200 bg-card text-card-foreground dark:border-slate-800',
        emerald: 'border-emerald-200/80 bg-emerald-50/50 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-200',
        amber: 'border-amber-200/80 bg-amber-50/50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200',
        rose: 'border-rose-200/80 bg-rose-50/50 text-rose-950 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-200',
        blue: 'border-blue-200/80 bg-blue-50/50 text-blue-950 dark:border-blue-900/50 dark:bg-blue-950/20 dark:text-blue-200',
    };

    const iconBgStyles = {
        default: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        emerald: 'bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        amber: 'bg-amber-500/15 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
        rose: 'bg-rose-500/15 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        blue: 'bg-blue-500/15 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
    };

    return (
        <div className={['flex items-center gap-3.5 rounded-xl border p-4 shadow-sm transition-all', toneStyles[tone]].join(' ')}>
            <div className={['flex size-11 shrink-0 items-center justify-center rounded-lg', iconBgStyles[tone]].join(' ')}>
                <Icon className="size-5.5" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-semibold uppercase tracking-wider text-muted-foreground">{title}</p>
                <p className="mt-0.5 text-xl font-bold tracking-tight">
                    {typeof amount === 'number' ? <MoneyCell value={amount} /> : amount}
                </p>
                {subtitle ? (
                    <p className="mt-0.5 truncate text-xs text-muted-foreground">{subtitle}</p>
                ) : null}
            </div>
        </div>
    );
}

export default function SubscriptionBillingReport({
    payments,
    summary,
    branchSubscription,
    isBranchScoped,
    filters,
    branches,
    paymentMethods,
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ?? 'all');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [paymentMethod, setPaymentMethod] = useState(filters.payment_method ?? 'all');
    const [dateFrom, setDateFrom] = useState(toDateInputValue(filters.date_from));
    const [dateTo, setDateTo] = useState(toDateInputValue(filters.date_to));

    const [selectedPayment, setSelectedPayment] = useState(null);

    useLiveReportFilters(
        'report.subscription-billing',
        {
            search,
            branch_id: branchId,
            status,
            payment_method: paymentMethod,
            date_from: dateFrom,
            date_to: dateTo,
        },
        [search, branchId, status, paymentMethod, dateFrom, dateTo],
    );

    const handleReset = () => {
        setSearch('');
        setBranchId('all');
        setStatus('all');
        setPaymentMethod('all');
        setDateFrom('');
        setDateTo('');
        router.get(route('report.subscription-billing'), {}, { preserveState: true, replace: true });
    };

    const hasActiveFilters = Boolean(
        search ||
        (branchId && branchId !== 'all') ||
        (status && status !== 'all') ||
        (paymentMethod && paymentMethod !== 'all') ||
        dateFrom ||
        dateTo
    );

    const exportParams = new URLSearchParams(
        Object.entries({
            search,
            branch_id: branchId !== 'all' ? branchId : '',
            status: status !== 'all' ? status : '',
            payment_method: paymentMethod !== 'all' ? paymentMethod : '',
            date_from: dateFrom,
            date_to: dateTo,
        }).filter(([, v]) => Boolean(v))
    ).toString();

    const excelUrl = `${route('report.subscription-billing.export-excel')}${exportParams ? `?${exportParams}` : ''}`;
    const csvUrl = `${route('report.subscription-billing.export-csv')}${exportParams ? `?${exportParams}` : ''}`;
    const printUrl = `${route('report.subscription-billing.export-print')}${exportParams ? `?${exportParams}` : ''}`;

    const branchOptions = [
        { value: 'all', label: 'All Client Branches' },
        ...Object.entries(branches || {}).map(([id, name]) => ({
            value: String(id),
            label: name,
        })),
    ];

    const statusOptions = [
        { value: 'all', label: 'All Statuses' },
        { value: 'approved', label: 'Approved' },
        { value: 'pending', label: 'Pending Approval' },
        { value: 'rejected', label: 'Rejected' },
    ];

    const paymentMethodOptions = [
        { value: 'all', label: 'All Payment Methods' },
        ...Object.entries(paymentMethods || {}).map(([key, label]) => ({
            value: key,
            label,
        })),
    ];

    return (
        <ReportPage
            title="Subscription Billing Report"
            description={
                isBranchScoped
                    ? "View your branch's subscription fees, billing cycles, invoice history, and deposit receipts."
                    : 'System-wide audit report of subscription renewals, billing cycles, collected fees, and pending approvals.'
            }
            filterBar={
                <>
                    <ReportFilterField label="Search Reference / Branch / Notes">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Search ref#, phone, notes..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-9 pl-8 text-sm"
                            />
                        </div>
                    </ReportFilterField>

                    {!isBranchScoped ? (
                        <ReportFilterField label="Client Branch">
                            <ReportSelect
                                value={branchId}
                                onValueChange={setBranchId}
                                options={branchOptions}
                                placeholder="Select branch"
                            />
                        </ReportFilterField>
                    ) : null}

                    <ReportFilterField label="Payment Status">
                        <ReportSelect
                            value={status}
                            onValueChange={setStatus}
                            options={statusOptions}
                            placeholder="Select status"
                        />
                    </ReportFilterField>

                    <ReportFilterField label="Payment Method">
                        <ReportSelect
                            value={paymentMethod}
                            onValueChange={setPaymentMethod}
                            options={paymentMethodOptions}
                            placeholder="Select method"
                        />
                    </ReportFilterField>

                    <ReportFilterField label="Paid From">
                        <ReportDateInput value={dateFrom} onChange={setDateFrom} />
                    </ReportFilterField>

                    <ReportFilterField label="Paid To">
                        <ReportDateInput value={dateTo} onChange={setDateTo} />
                    </ReportFilterField>
                </>
            }
            filterActions={
                <div className="flex items-center gap-2">
                    <ReportFilterReset onClick={handleReset} disabled={!hasActiveFilters} />
                    <a
                        href={excelUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex h-7 items-center gap-1.5 rounded-md border border-white/20 bg-white/10 px-2.5 text-xs font-medium text-white hover:bg-white/20"
                    >
                        <FileSpreadsheet className="size-3.5 text-emerald-300" />
                        Excel
                    </a>
                    <a
                        href={csvUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex h-7 items-center gap-1.5 rounded-md border border-white/20 bg-white/10 px-2.5 text-xs font-medium text-white hover:bg-white/20"
                    >
                        <Download className="size-3.5 text-blue-300" />
                        CSV
                    </a>
                    <a
                        href={printUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex h-7 items-center gap-1.5 rounded-md border border-white/20 bg-white/10 px-2.5 text-xs font-medium text-white hover:bg-white/20"
                    >
                        <Printer className="size-3.5 text-amber-300" />
                        Print
                    </a>
                </div>
            }
        >
            <Head title="Subscription Billing Report" />

            {/* Top Stat Cards */}
            <div className="mb-5 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Collected (Approved)"
                    amount={summary?.total_collected ?? 0}
                    subtitle={`${summary?.approved_count ?? 0} approved transaction(s)`}
                    icon={Wallet}
                    tone="emerald"
                />
                <StatCard
                    title="Pending Verification"
                    amount={summary?.pending_amount ?? 0}
                    subtitle={`${summary?.pending_count ?? 0} awaiting approval`}
                    icon={Clock}
                    tone="amber"
                />
                <StatCard
                    title="Total Billed Submissions"
                    amount={summary?.total_amount ?? 0}
                    subtitle={`${summary?.total_count ?? 0} total records`}
                    icon={Receipt}
                    tone="blue"
                />
                {branchSubscription ? (
                    <StatCard
                        title="Current Branch Status"
                        amount={branchSubscription.plan ? branchSubscription.plan.toUpperCase() : 'ACTIVE'}
                        subtitle={
                            branchSubscription.is_active
                                ? `Expires: ${branchSubscription.expires_at || 'Lifetime'}`
                                : 'Subscription Overdue / Suspended'
                        }
                        icon={branchSubscription.is_active ? CheckCircle2 : AlertCircle}
                        tone={branchSubscription.is_active ? 'default' : 'rose'}
                    />
                ) : (
                    <StatCard
                        title="Rejected Submissions"
                        amount={summary?.rejected_amount ?? 0}
                        subtitle={`${summary?.rejected_count ?? 0} rejected payment(s)`}
                        icon={XCircle}
                        tone="rose"
                    />
                )}
            </div>

            {/* Data Table */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b border-border bg-muted/60 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">#</th>
                                <th className="px-4 py-3">Paid Date</th>
                                {!isBranchScoped ? <th className="px-4 py-3">Client Branch</th> : null}
                                <th className="px-4 py-3">Billing Period</th>
                                <th className="px-4 py-3">Payment Channel</th>
                                <th className="px-4 py-3">Trx Reference</th>
                                <th className="px-4 py-3 text-right">Amount</th>
                                <th className="px-4 py-3 text-center">Status</th>
                                <th className="px-4 py-3">Recorded By</th>
                                <th className="px-4 py-3 text-center">Receipt</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border font-medium">
                            {payments?.data?.length ? (
                                payments.data.map((row, index) => (
                                    <tr
                                        key={row.id}
                                        className="transition-colors hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {(payments.current_page - 1) * payments.per_page + index + 1}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-xs font-semibold">
                                            {formatBdDate(row.paid_at) || row.paid_at || '—'}
                                        </td>
                                        {!isBranchScoped ? (
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="size-3.5 shrink-0 text-muted-foreground" />
                                                    <span className="font-semibold text-foreground">{row.branch_name}</span>
                                                </div>
                                                {row.branch_phone ? (
                                                    <p className="text-xs text-muted-foreground">{row.branch_phone}</p>
                                                ) : null}
                                            </td>
                                        ) : null}
                                        <td className="whitespace-nowrap px-4 py-3 text-xs">
                                            <span className="text-foreground">
                                                {formatBdDate(row.billing_period_starts_at) || row.billing_period_starts_at || '—'}
                                            </span>
                                            <span className="mx-1 text-muted-foreground">→</span>
                                            <span className="font-semibold text-foreground">
                                                {formatBdDate(row.billing_period_ends_at) || row.billing_period_ends_at || '—'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-foreground">
                                            {row.payment_method}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {row.transaction_reference || '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-bold text-foreground">
                                            <MoneyCell value={row.amount} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center">
                                            <StatusBadge status={row.status} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                            {row.recorded_by}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setSelectedPayment(row)}
                                                className="h-7 gap-1 px-2 text-xs"
                                            >
                                                <Eye className="size-3.5" />
                                                View
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={isBranchScoped ? 9 : 10}
                                        className="py-12 text-center text-sm text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center justify-center gap-2">
                                            <Receipt className="size-8 text-muted-foreground/40" />
                                            <p className="font-semibold">No subscription billing records found.</p>
                                            <p className="text-xs">Try adjusting your date range, status, or search filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {payments?.total > 0 ? (
                    <div className="border-t border-border p-3">
                        <AdminPagination paginator={payments} className="mt-0 border-t-0 pt-0" />
                    </div>
                ) : null}
            </div>

            {/* Payment Details & Receipt Slip Dialog */}
            <Dialog open={selectedPayment !== null} onOpenChange={(open) => !open && setSelectedPayment(null)}>
                <DialogContent className="max-w-md p-0 overflow-hidden">
                    <div className="bg-blue-950 px-5 py-4 text-white">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-base font-semibold text-white">
                                <Receipt className="size-5 text-blue-300" />
                                Subscription Billing Slip
                            </DialogTitle>
                            <DialogDescription className="text-xs text-blue-200">
                                Invoice voucher details and deposit receipt verification.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    {selectedPayment ? (
                        <div className="space-y-4 p-5 text-sm">
                            <div className="rounded-lg border border-border bg-muted/40 p-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Client Branch</span>
                                    <span className="font-semibold text-foreground">{selectedPayment.branch_name}</span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Paid Date</span>
                                    <span className="font-medium text-foreground">
                                        {formatBdDate(selectedPayment.paid_at) || selectedPayment.paid_at || '—'}
                                    </span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Billing Period</span>
                                    <span className="font-medium text-foreground">
                                        {formatBdDate(selectedPayment.billing_period_starts_at) || selectedPayment.billing_period_starts_at} →{' '}
                                        {formatBdDate(selectedPayment.billing_period_ends_at) || selectedPayment.billing_period_ends_at}
                                    </span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Payment Method</span>
                                    <span className="font-medium text-foreground">{selectedPayment.payment_method}</span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Trx Reference</span>
                                    <span className="font-mono text-xs font-semibold text-foreground">
                                        {selectedPayment.transaction_reference || '—'}
                                    </span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Approval Status</span>
                                    <StatusBadge status={selectedPayment.status} />
                                </div>
                                <div className="mt-3 border-t border-border pt-2.5 flex items-center justify-between">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Amount Paid</span>
                                    <span className="text-lg font-bold text-blue-950 dark:text-blue-400">
                                        <MoneyCell value={selectedPayment.amount} />
                                    </span>
                                </div>
                            </div>

                            {selectedPayment.notes ? (
                                <div>
                                    <p className="text-xs font-semibold text-muted-foreground uppercase">Notes / Instructions</p>
                                    <p className="mt-1 text-xs text-foreground bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-md border border-border">
                                        {selectedPayment.notes}
                                    </p>
                                </div>
                            ) : null}

                            {selectedPayment.attachment_url ? (
                                <div>
                                    <p className="text-xs font-semibold text-muted-foreground uppercase flex items-center gap-1">
                                        <Paperclip className="size-3.5" />
                                        Deposit Receipt Attachment
                                    </p>
                                    <div className="mt-2 overflow-hidden rounded-lg border border-border">
                                        {selectedPayment.attachment_url.toLowerCase().endsWith('.pdf') ? (
                                            <a
                                                href={selectedPayment.attachment_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex items-center gap-2 p-3 text-xs font-semibold text-blue-600 hover:underline"
                                            >
                                                <FileText className="size-5" />
                                                View PDF Receipt Document
                                            </a>
                                        ) : (
                                            <a href={selectedPayment.attachment_url} target="_blank" rel="noreferrer">
                                                <img
                                                    src={selectedPayment.attachment_url}
                                                    alt="Payment Slip"
                                                    className="max-h-56 w-full object-contain bg-slate-100 dark:bg-slate-950 p-2"
                                                />
                                            </a>
                                        )}
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
        </ReportPage>
    );
}
