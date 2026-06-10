import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, CreditCard, Eye, Package, User, UsersRound } from 'lucide-react';
import { useEffect } from 'react';

import { useAppToast } from '@/contexts/app-toast-context';
import { DataTable } from '@/components/ui/data-table';
import { route } from '@/lib/route';

const STATUS_COLORS = {
    1: 'bg-amber-100 text-amber-800',
    2: 'bg-blue-100 text-blue-800',
    3: 'bg-indigo-100 text-indigo-800',
    4: 'bg-cyan-100 text-cyan-800',
    5: 'bg-emerald-100 text-emerald-800',
    6: 'bg-red-100 text-red-800',
};

const PAYMENT_METHOD_LABELS = {
    cod: 'Cash on Delivery',
    sslcommerz: 'Online Payment',
};

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusLabel(statuses, value) {
    const match = statuses.find((status) => status.value === value);

    return match?.label ?? 'Unknown';
}

function StatCard({ label, value, className = '' }) {
    return (
        <div className="rounded-lg border border-border bg-card px-4 py-3 text-center shadow-sm">
            <p className={`text-2xl font-bold ${className}`}>{value}</p>
            <p className="mt-1 text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

function SectionCard({ title, icon: Icon, children }) {
    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
            <div className="flex items-center gap-2.5 border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                {Icon && (
                    <div className="flex size-6 items-center justify-center rounded bg-white/15">
                        <Icon className="size-3.5 text-white" />
                    </div>
                )}
                <h2 className="text-sm font-semibold uppercase tracking-wide text-white">{title}</h2>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function InfoField({ label, value }) {
    return (
        <div className="flex justify-between gap-3 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value || '—'}</dd>
        </div>
    );
}

export default function OnlineCustomerShow({ customer, orders, statuses }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const orderColumns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (orders.from ?? 0) + i,
        },
        {
            header: 'Order',
            render: (row) => (
                <div>
                    <p className="font-medium">#{row.id}</p>
                    <p className="text-xs text-muted-foreground">{formatDate(row.created_at)}</p>
                </div>
            ),
        },
        {
            header: 'Total',
            render: (row) => <span className="font-semibold">৳{Number(row.total).toFixed(0)}</span>,
        },
        {
            header: 'Payment',
            render: (row) => (
                <div>
                    <p className="text-sm">{PAYMENT_METHOD_LABELS[row.payment_method] ?? row.payment_method ?? '—'}</p>
                    <p className="text-xs text-muted-foreground">{row.payment_status}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            render: (row) => (
                <span
                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_COLORS[row.status] ?? 'bg-muted text-muted-foreground'}`}
                >
                    {statusLabel(statuses, row.status)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end">
                    <Link
                        href={route('online-order.show', { onlineOrder: row.id })}
                        aria-label={`View order #${row.id}`}
                        className="inline-flex size-6 items-center justify-center rounded-none border border-blue-200 bg-blue-50 text-blue-600 transition-all duration-200 hover:-translate-y-1 hover:border-blue-500 hover:bg-blue-600 hover:text-white hover:shadow-sm hover:shadow-blue-500/35"
                    >
                        <Eye className="size-2.5" strokeWidth={2.5} />
                    </Link>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={`Customer: ${customer.name}`} />

            <div className="space-y-4 px-2 py-1">
                <div className="flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <UsersRound className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">{customer.name}</h1>
                            <p className="text-xs text-white/60">Online customer profile and order history.</p>
                        </div>
                    </div>
                    <Link
                        href={route('online-customer.index')}
                        className="inline-flex items-center gap-1.5 rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-white/20"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to list
                    </Link>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <SectionCard title="Customer Info" icon={User}>
                        <dl className="space-y-2">
                            <InfoField label="Name" value={customer.name} />
                            <InfoField label="Phone" value={customer.phone} />
                            <InfoField label="Email" value={customer.email} />
                            <InfoField label="Address" value={customer.address} />
                            <InfoField label="Registered" value={formatDate(customer.created_at)} />
                        </dl>
                    </SectionCard>

                    <SectionCard title="Payment Summary" icon={CreditCard}>
                        <dl className="space-y-2">
                            <InfoField label="Total Orders" value={customer.orders_count} />
                            <InfoField label="COD Orders" value={customer.cod_orders_count} />
                            <InfoField label="Online Payment Orders" value={customer.online_payment_orders_count} />
                        </dl>
                    </SectionCard>
                </div>

                <SectionCard title="Order Status Summary" icon={Package}>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <StatCard label="Pending" value={customer.pending_orders_count} className="text-amber-700" />
                        <StatCard label="Processing" value={customer.processing_orders_count} className="text-blue-700" />
                        <StatCard label="Confirmed" value={customer.confirmed_orders_count} className="text-cyan-700" />
                        <StatCard label="Shipping" value={customer.shipping_orders_count} className="text-indigo-700" />
                        <StatCard label="Delivered" value={customer.delivered_orders_count} className="text-emerald-700" />
                        <StatCard label="Canceled" value={customer.canceled_orders_count} className="text-red-700" />
                    </div>
                </SectionCard>

                <SectionCard title="Order History" icon={Package}>
                    <DataTable columns={orderColumns} rows={orders.data} rowKey="id" emptyMessage="No orders yet." />

                    {orders.links?.length > 3 && (
                        <div className="mt-4 flex flex-wrap gap-1">
                            {orders.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    className={[
                                        'border px-3 py-1 text-sm transition-colors',
                                        link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                                        !link.url ? 'pointer-events-none opacity-50' : '',
                                    ].join(' ')}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    preserveScroll
                                />
                            ))}
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}
