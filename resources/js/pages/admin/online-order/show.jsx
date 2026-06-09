import { Can } from '@/components/can';
import {
    headerActionClassName,
    InvoiceShowHeader,
    PartyInfoCard,
} from '@/components/inventory/invoice-show-layout';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, FileDown, Package, RefreshCw, Truck, User } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { route } from '@/lib/route';

const STATUS_COLORS = {
    1: 'bg-amber-100 text-amber-800',
    2: 'bg-blue-100 text-blue-800',
    3: 'bg-indigo-100 text-indigo-800',
    4: 'bg-cyan-100 text-cyan-800',
    5: 'bg-emerald-100 text-emerald-800',
    6: 'bg-red-100 text-red-800',
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

function formatCourierStatus(status) {
    if (!status) {
        return '—';
    }

    return status.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function SectionCard({ title, icon: Icon, children, action }) {
    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
            <div className="flex items-center justify-between gap-2 border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                <div className="flex items-center gap-2.5">
                    {Icon && (
                        <div className="flex size-6 items-center justify-center rounded bg-white/15">
                            <Icon className="size-3.5 text-white" />
                        </div>
                    )}
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-white">{title}</h2>
                </div>
                {action}
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function SummaryField({ label, value, className = '' }) {
    return (
        <div className="flex justify-between gap-3 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={`text-right font-medium ${className}`}>{value}</dd>
        </div>
    );
}

const itemColumns = [
    {
        id: 'num',
        header: '#',
        render: (_, i) => <span className="text-muted-foreground">{i + 1}</span>,
    },
    {
        id: 'name',
        header: 'Product',
        render: (row) => (
            <div>
                <p className="font-medium">{row.name}</p>
                {row.sku && <p className="text-xs text-muted-foreground">{row.sku}</p>}
            </div>
        ),
    },
    {
        id: 'quantity',
        header: 'Qty',
        align: 'right',
        render: (row) => row.quantity,
    },
    {
        id: 'total',
        header: 'Total',
        align: 'right',
        render: (row) => <span className="font-semibold text-primary">৳{Number(row.total_price).toFixed(0)}</span>,
    },
];

export default function OnlineOrderShow({ order, statuses, canSendToSteadfast, steadfastBlockReason }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [selectedStatus, setSelectedStatus] = useState(String(order.status));
    const [sending, setSending] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [updating, setUpdating] = useState(false);
    const actionClass = headerActionClassName();

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useEffect(() => {
        setSelectedStatus(String(order.status));
    }, [order.status]);

    function handleSendToSteadfast() {
        setSending(true);
        router.post(route('online-order.send-steadfast', { onlineOrder: order.id }), {}, {
            preserveScroll: true,
            onFinish: () => setSending(false),
        });
    }

    function handleSyncStatus() {
        setSyncing(true);
        router.patch(route('online-order.sync-steadfast', { onlineOrder: order.id }), {}, {
            preserveScroll: true,
            onFinish: () => setSyncing(false),
        });
    }

    function handleUpdateStatus() {
        setUpdating(true);
        router.patch(
            route('online-order.update-status', { onlineOrder: order.id }),
            { status: Number(selectedStatus) },
            {
                preserveScroll: true,
                onFinish: () => setUpdating(false),
            },
        );
    }

    const statusLabel = statuses.find((item) => item.value === order.status)?.label ?? 'Unknown';
    const orderNumber = `ORD${String(order.id).padStart(8, '0')}`;

    return (
        <>
            <Head title={`Online Order #${order.id}`} />

            <div className="px-2 py-1">
                <InvoiceShowHeader icon={Package} title="Online Order" invoiceNumber={orderNumber}>
                    <Button size="sm" asChild className={actionClass}>
                        <a href={route('online-order.invoice', { onlineOrder: order.id })} target="_blank" rel="noreferrer">
                            <FileDown className="size-3.5" />
                            Download Invoice
                        </a>
                    </Button>
                    <Button size="sm" asChild className={actionClass}>
                        <Link href={route('online-order.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </InvoiceShowHeader>

                <PartyInfoCard
                    icon={User}
                    label="Customer & Delivery"
                    name={order.name}
                    phone={order.phone}
                    address={order.address}
                />

                {order.email && (
                    <div className="mb-6 overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
                        <div className="border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-white">Contact</h3>
                        </div>
                        <div className="p-4">
                            <p className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Email</p>
                            <p className="text-sm font-medium">{order.email}</p>
                        </div>
                    </div>
                )}

                <div className="mb-6 grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <SectionCard title="Order Items" icon={Package}>
                            <DataTable
                                columns={itemColumns}
                                rows={order.products ?? []}
                                rowKey="id"
                                emptyMessage="No items in this order."
                                caption="Online order line items"
                            />
                        </SectionCard>
                    </div>

                    <div className="space-y-4">
                        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
                            <div className="border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                                <p className="text-xs font-semibold uppercase tracking-wider text-blue-50">Order Summary</p>
                            </div>
                            <dl className="space-y-2 p-4 text-sm">
                                <SummaryField label="Placed" value={formatDate(order.created_at)} />
                                <SummaryField label="Subtotal" value={`৳${Number(order.subtotal).toFixed(0)}`} />
                                <SummaryField label="Delivery" value={`৳${Number(order.delivery_charge).toFixed(0)}`} />
                                <SummaryField
                                    label="Total"
                                    value={`৳${Number(order.total).toFixed(0)}`}
                                    className="border-t border-border pt-2 font-bold text-primary"
                                />
                                <SummaryField label="Payment" value={(order.payment_method ?? '—').toUpperCase()} />
                                <SummaryField label="Payment status" value={order.payment_status} />
                                <div className="flex items-center justify-between gap-3 border-t border-border pt-2">
                                    <dt className="text-muted-foreground">Order status</dt>
                                    <dd>
                                        <Badge className={STATUS_COLORS[order.status] ?? 'bg-muted text-muted-foreground'}>
                                            {statusLabel}
                                        </Badge>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <Can permission="online-order.update">
                            <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm ring-1 ring-blue-950/10 dark:ring-blue-400/14">
                                <div className="border-b border-blue-900/80 bg-blue-950 px-4 py-2.5">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-blue-50">Update Status</p>
                                </div>
                                <div className="space-y-3 p-4">
                                    <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((item) => (
                                                <SelectItem key={item.value} value={String(item.value)}>
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button size="sm" className="w-full bg-emerald-600 text-white hover:bg-emerald-700" onClick={handleUpdateStatus} disabled={updating}>
                                        {updating ? 'Updating…' : 'Update status'}
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        Setting status to Delivered will post inventory and accounting entries.
                                    </p>
                                </div>
                            </div>
                        </Can>
                    </div>
                </div>

                <SectionCard
                    title="Steadfast Courier"
                    icon={Truck}
                    action={(
                        <Can permission="online-order.update">
                            <div className="flex flex-wrap gap-2">
                                {canSendToSteadfast ? (
                                    <Button size="sm" className={actionClass} onClick={handleSendToSteadfast} disabled={sending}>
                                        {sending ? 'Sending…' : 'Send to Steadfast'}
                                    </Button>
                                ) : (
                                    order.courier_consignment_id && (
                                        <Button size="sm" className={actionClass} onClick={handleSyncStatus} disabled={syncing}>
                                            <RefreshCw className={`mr-1.5 size-3.5 ${syncing ? 'animate-spin' : ''}`} />
                                            {syncing ? 'Syncing…' : 'Sync status'}
                                        </Button>
                                    )
                                )}
                            </div>
                        </Can>
                    )}
                >
                    {!canSendToSteadfast && steadfastBlockReason && !order.courier_consignment_id && (
                        <p className="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {steadfastBlockReason}
                        </p>
                    )}

                    <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Courier</dt>
                            <dd className="font-medium uppercase">{order.courier ?? 'Not assigned'}</dd>
                        </div>
                        <div>
                            <dt className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Invoice</dt>
                            <dd className="font-mono text-sm">{order.courier_invoice ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Consignment ID</dt>
                            <dd className="font-mono text-sm">{order.courier_consignment_id ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Tracking code</dt>
                            <dd className="font-mono text-sm font-semibold text-indigo-700">{order.courier_tracking_code ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Courier status</dt>
                            <dd>{formatCourierStatus(order.courier_status)}</dd>
                        </div>
                        <div>
                            <dt className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Sent at</dt>
                            <dd>{formatDate(order.courier_sent_at)}</dd>
                        </div>
                        <div>
                            <dt className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">COD amount</dt>
                            <dd>৳{order.payment_method === 'cod' ? Number(order.total).toFixed(0) : '0'}</dd>
                        </div>
                    </dl>
                </SectionCard>
            </div>
        </>
    );
}
