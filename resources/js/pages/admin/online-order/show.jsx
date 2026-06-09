import { Can } from '@/components/can';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, FileDown, Package, RefreshCw, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
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

export default function OnlineOrderShow({ order, statuses, canSendToSteadfast, steadfastBlockReason }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [selectedStatus, setSelectedStatus] = useState(String(order.status));
    const [sending, setSending] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [updating, setUpdating] = useState(false);

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

    return (
        <>
            <Head title={`Online Order #${order.id}`} />

            <div className="px-2 py-1">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Package className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Order #{order.id}</h1>
                            <p className="text-xs text-white/60">Placed {formatDate(order.created_at)}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="secondary" size="sm" asChild>
                            <a href={route('online-order.invoice', { onlineOrder: order.id })} target="_blank" rel="noreferrer">
                                <FileDown className="size-3.5" />
                                Download Invoice
                            </a>
                        </Button>
                        <Link
                            href={route('online-order.index')}
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-white/80 transition hover:text-white"
                        >
                            <ArrowLeft className="size-3.5" />
                            Back to orders
                        </Link>
                    </div>
                </div>

                <div className="mb-4 grid gap-3 lg:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4 lg:col-span-2">
                        <h2 className="mb-3 text-sm font-semibold">Customer & delivery</h2>
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">Name</dt>
                                <dd className="font-medium">{order.name}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Phone</dt>
                                <dd className="font-medium">{order.phone}</dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-muted-foreground">Address</dt>
                                <dd className="font-medium">{order.address}</dd>
                            </div>
                            {order.email && (
                                <div>
                                    <dt className="text-muted-foreground">Email</dt>
                                    <dd className="font-medium">{order.email}</dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    <div className="rounded-lg border bg-white p-4">
                        <h2 className="mb-3 text-sm font-semibold">Order summary</h2>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Subtotal</dt>
                                <dd>৳{Number(order.subtotal).toFixed(0)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Delivery</dt>
                                <dd>৳{Number(order.delivery_charge).toFixed(0)}</dd>
                            </div>
                            <div className="flex justify-between border-t pt-2 font-semibold">
                                <dt>Total</dt>
                                <dd>৳{Number(order.total).toFixed(0)}</dd>
                            </div>
                            <div className="flex justify-between pt-1">
                                <dt className="text-muted-foreground">Payment</dt>
                                <dd className="uppercase">{order.payment_method ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Payment status</dt>
                                <dd>{order.payment_status}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Order status</dt>
                                <dd>
                                    <span
                                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_COLORS[order.status] ?? 'bg-muted text-muted-foreground'}`}
                                    >
                                        {statusLabel}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div className="mb-4 rounded-lg border bg-white p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <Truck className="size-4 text-indigo-600" />
                            <h2 className="text-sm font-semibold">Steadfast Courier</h2>
                        </div>
                        <Can permission="online-order.update">
                            <div className="flex flex-wrap gap-2">
                                {canSendToSteadfast ? (
                                    <Button size="sm" onClick={handleSendToSteadfast} disabled={sending}>
                                        {sending ? 'Sending…' : 'Send to Steadfast'}
                                    </Button>
                                ) : (
                                    order.courier_consignment_id && (
                                        <Button size="sm" variant="outline" onClick={handleSyncStatus} disabled={syncing}>
                                            <RefreshCw className={`mr-1.5 size-3.5 ${syncing ? 'animate-spin' : ''}`} />
                                            {syncing ? 'Syncing…' : 'Sync status'}
                                        </Button>
                                    )
                                )}
                            </div>
                        </Can>
                    </div>

                    {!canSendToSteadfast && steadfastBlockReason && !order.courier_consignment_id && (
                        <p className="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {steadfastBlockReason}
                        </p>
                    )}

                    <dl className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt className="text-muted-foreground">Courier</dt>
                            <dd className="font-medium uppercase">{order.courier ?? 'Not assigned'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Invoice</dt>
                            <dd className="font-mono text-sm">{order.courier_invoice ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Consignment ID</dt>
                            <dd className="font-mono text-sm">{order.courier_consignment_id ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Tracking code</dt>
                            <dd className="font-mono text-sm font-semibold text-indigo-700">
                                {order.courier_tracking_code ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Courier status</dt>
                            <dd>{formatCourierStatus(order.courier_status)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Sent at</dt>
                            <dd>{formatDate(order.courier_sent_at)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">COD amount</dt>
                            <dd>৳{order.payment_method === 'cod' ? Number(order.total).toFixed(0) : '0'}</dd>
                        </div>
                    </dl>
                </div>

                <Can permission="online-order.update">
                    <div className="mb-4 rounded-lg border bg-white p-4">
                        <h2 className="mb-3 text-sm font-semibold">Update order status</h2>
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="min-w-[180px]">
                                <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                    <SelectTrigger>
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
                            </div>
                            <Button size="sm" onClick={handleUpdateStatus} disabled={updating}>
                                {updating ? 'Updating…' : 'Update status'}
                            </Button>
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Setting status to Delivered will post inventory and accounting entries.
                        </p>
                    </div>
                </Can>

                <div className="overflow-hidden rounded-lg border bg-white">
                    <div className="border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">Order items</h2>
                    </div>
                    <div className="divide-y">
                        {order.products?.map((item) => (
                            <div key={item.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                <div>
                                    <p className="font-medium">{item.name}</p>
                                    {item.sku && <p className="text-xs text-muted-foreground">{item.sku}</p>}
                                </div>
                                <div className="text-right">
                                    <p className="text-muted-foreground">× {item.quantity}</p>
                                    <p className="font-semibold">৳{Number(item.total_price).toFixed(0)}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
