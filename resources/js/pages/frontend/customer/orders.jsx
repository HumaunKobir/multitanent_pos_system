import { Link } from '@inertiajs/react';
import { ChevronRight, Clock, FileDown, Package, ShoppingBag } from 'lucide-react';

import {
    getOrderStatusLabel,
    OrderTrackingProgress,
} from '@/components/frontend/customer-panel/order-tracking-progress';
import { CustomerPanelLayout } from '@/layouts/frontend/customer-panel-layout';
import { cn } from '@/lib/utils';

const statusTones = {
    1: 'bg-amber-50 text-amber-700 ring-amber-200',
    2: 'bg-blue-50 text-blue-700 ring-blue-200',
    3: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    4: 'bg-sky-50 text-sky-700 ring-sky-200',
    5: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    6: 'bg-red-50 text-red-700 ring-red-200',
};

function formatPrice(value) {
    return Number(value).toLocaleString('en-BD', { maximumFractionDigits: 0 });
}

function formatOrderDate(value) {
    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function paymentLabel(method, status) {
    const isPaid = status === 'Paid' || status === 'paid';

    if (method === 'cod') {
        return 'COD';
    }

    if (method === 'sslcommerz') {
        return isPaid ? 'Paid online' : 'Online · pending';
    }

    return method?.toUpperCase() ?? '—';
}

function productPreview(products = []) {
    if (products.length === 0) {
        return null;
    }

    const preview = products
        .slice(0, 2)
        .map((item) => item.name)
        .join(', ');

    if (products.length > 2) {
        return `${preview} +${products.length - 2} more`;
    }

    return preview;
}

function OrdersPageHeader({ total, pendingCount }) {
    return (
        <section className="mb-3 overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
            <div className="relative overflow-hidden bg-linear-to-br from-store-surface/90 via-white to-white px-4 py-3">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <span className="inline-block rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-muted ring-1 ring-gray-200">
                            My account
                        </span>
                        <h1 className="mt-1.5 text-base font-bold text-store-primary sm:text-lg">Online Orders</h1>
                        <p className="mt-0.5 text-[11px] text-store-muted">Track status and view order details</p>
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <div className="rounded-lg bg-white px-2.5 py-1.5 text-center ring-1 ring-gray-200">
                            <p className="text-base font-bold leading-none text-store-primary">{total}</p>
                            <p className="mt-0.5 text-[9px] font-semibold uppercase tracking-wide text-store-muted">Total</p>
                        </div>
                        {pendingCount > 0 && (
                            <div className="rounded-lg bg-amber-50 px-2.5 py-1.5 text-center ring-1 ring-amber-200">
                                <p className="text-base font-bold leading-none text-amber-700">{pendingCount}</p>
                                <p className="mt-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-600/80">Pending</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}

function OrderStatusBadge({ status }) {
    const normalized = typeof status === 'object' && status?.value != null ? status.value : Number(status);

    return (
        <span
            className={cn(
                'inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1',
                statusTones[normalized] ?? 'bg-store-surface text-store-muted ring-gray-200',
            )}
        >
            {getOrderStatusLabel(status)}
        </span>
    );
}

function OrderCard({ order }) {
    const itemCount = order.products?.length ?? 0;
    const preview = productPreview(order.products);

    return (
        <div className="group overflow-hidden rounded-xl bg-white ring-1 ring-gray-200 transition hover:ring-store-accent/35">
            <Link href={`/customer/orders/${order.id}`} className="block">
            <div className="flex items-center gap-2.5 border-b border-gray-100 px-3 py-2.5">
                <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-store-accent/20 bg-store-accent/5">
                    <Package className="size-3.5 text-store-accent" aria-hidden />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <p className="text-sm font-bold text-store-primary">Order #{order.id}</p>
                        <span className="text-[10px] text-store-muted">·</span>
                        <p className="text-[10px] text-store-muted">{formatOrderDate(order.created_at)}</p>
                        {itemCount > 0 && (
                            <>
                                <span className="text-[10px] text-store-muted">·</span>
                                <p className="text-[10px] font-medium text-store-muted">
                                    {itemCount} item{itemCount === 1 ? '' : 's'}
                                </p>
                            </>
                        )}
                    </div>
                    {preview && (
                        <p className="mt-0.5 line-clamp-1 text-[10px] text-store-muted">{preview}</p>
                    )}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <div className="text-right">
                        <p className="text-sm font-bold text-store-accent">৳{formatPrice(order.total)}</p>
                        <p className="text-[9px] font-medium text-store-muted">
                            {paymentLabel(order.payment_method, order.payment_status)}
                        </p>
                    </div>
                    <ChevronRight className="size-4 text-store-muted transition group-hover:text-store-accent" aria-hidden />
                </div>
            </div>

            <div className="space-y-2 px-3 py-2.5">
                <div className="flex items-center justify-between gap-2">
                    <OrderStatusBadge status={order.status} />
                    <span className="text-[10px] font-medium text-store-muted">Tap for details</span>
                </div>
                <OrderTrackingProgress status={order.status} variant="compact" />
            </div>
            </Link>
            <div className="flex items-center justify-end border-t border-gray-100 px-3 py-1.5">
                <a
                    href={`/customer/orders/${order.id}/invoice`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[10px] font-semibold text-store-muted transition hover:bg-store-accent/5 hover:text-store-accent"
                >
                    <FileDown className="size-3" />
                    Invoice PDF
                </a>
            </div>
        </div>
    );
}

function OrdersPagination({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Orders pagination" className="mt-3 flex flex-wrap justify-center gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        className={cn(
                            'min-w-7 rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                            link.active
                                ? 'bg-store-accent text-white'
                                : 'bg-white text-store-primary ring-1 ring-gray-200 hover:ring-store-accent',
                        )}
                    />
                ) : (
                    <span
                        key={index}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        className="min-w-7 rounded-full px-2.5 py-1 text-xs text-gray-300"
                    />
                ),
            )}
        </nav>
    );
}

export default function CustomerOrders({ orders, orderCount, pendingCount }) {
    const total = orderCount ?? orders.total ?? orders.data?.length ?? 0;
    const pending = pendingCount ?? 0;

    return (
        <CustomerPanelLayout title="Online Orders">
            <OrdersPageHeader total={total} pendingCount={pending} />

            {orders.data?.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-xl bg-white py-10 text-center ring-1 ring-gray-200">
                    <span className="inline-flex size-11 items-center justify-center rounded-xl border border-store-accent/20 bg-store-accent/5">
                        <ShoppingBag className="size-5 text-store-accent" />
                    </span>
                    <p className="mt-3 text-sm font-bold text-store-primary">No orders yet</p>
                    <p className="mt-1 max-w-xs text-[11px] text-store-muted">
                        When you place an order, it will appear here with live tracking.
                    </p>
                    <Link
                        href="/"
                        className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-store-accent px-4 py-2 text-xs font-bold text-white transition hover:bg-store-accent/90"
                    >
                        <ShoppingBag className="size-3.5" />
                        Start shopping
                    </Link>
                </div>
            ) : (
                <>
                    <div className="space-y-2">
                        {orders.data.map((order) => (
                            <OrderCard key={order.id} order={order} />
                        ))}
                    </div>
                    <OrdersPagination links={orders.links} />
                </>
            )}

            {total > 0 && pending === 0 && (
                <p className="mt-3 flex items-center justify-center gap-1.5 text-[10px] text-store-muted">
                    <Clock className="size-3" aria-hidden />
                    All orders are being processed or completed
                </p>
            )}
        </CustomerPanelLayout>
    );
}
