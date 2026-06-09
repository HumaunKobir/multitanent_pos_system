import { Link } from '@inertiajs/react';
import { ArrowLeft, FileDown } from 'lucide-react';

import {
    getOrderStatusLabel,
    OrderTrackingProgress,
} from '@/components/frontend/customer-panel/order-tracking-progress';
import { CustomerPanelLayout } from '@/layouts/frontend/customer-panel-layout';

export default function OrderDetails({ order }) {
    return (
        <CustomerPanelLayout title={`Order #${order.id}`}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <Link
                    href="/customer/orders"
                    className="inline-flex items-center gap-1.5 text-xs font-medium text-store-muted transition hover:text-store-accent"
                >
                    <ArrowLeft className="size-4" />
                    Back to orders
                </Link>
                <a
                    href={`/customer/orders/${order.id}/invoice`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 rounded-xl bg-store-accent px-3 py-1.5 text-xs font-bold text-white transition hover:bg-store-accent/90"
                >
                    <FileDown className="size-3.5" />
                    Download Invoice PDF
                </a>
            </div>

            <OrderTrackingProgress status={order.status} className="mb-3" />

            <div className="mb-3 grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border border-gray-200/80 bg-white p-3.5 shadow-sm">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-store-accent">Delivery</p>
                    <p className="font-medium text-store-primary">{order.name}</p>
                    <p className="mt-1 text-sm text-store-muted">{order.phone}</p>
                    <p className="mt-1 text-sm text-store-muted">{order.address}</p>
                </div>
                <div className="rounded-xl border border-gray-200/80 bg-white p-3.5 shadow-sm">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-store-accent">Order info</p>
                    <p className="text-sm text-store-muted">
                        Status:{' '}
                        <span className="font-semibold text-store-primary">{getOrderStatusLabel(order.status)}</span>
                    </p>
                    <p className="mt-2 text-sm text-store-muted">Payment: {order.payment_status}</p>
                    <p className="mt-1 text-sm text-store-muted">Method: {order.payment_method?.toUpperCase()}</p>
                    {order.courier_tracking_code && (
                        <div className="mt-3 rounded-lg border border-indigo-100 bg-indigo-50/60 px-3 py-2">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Courier tracking</p>
                            <p className="mt-1 font-mono text-sm font-semibold text-indigo-900">{order.courier_tracking_code}</p>
                            {order.courier_status && (
                                <p className="mt-1 text-xs capitalize text-indigo-700/80">
                                    {order.courier_status.replace(/_/g, ' ')}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-sm">
                <div className="divide-y divide-gray-100">
                    {order.products?.map((item, i) => (
                        <div key={i} className="flex items-center justify-between gap-3 p-3.5">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-store-primary line-clamp-2">{item.name}</p>
                                {item.sku && <p className="text-xs text-store-muted">{item.sku}</p>}
                            </div>
                            <div className="text-right text-sm text-store-muted">
                                <p>× {item.quantity}</p>
                                <p className="font-bold text-store-primary">৳{Number(item.total_price).toFixed(0)}</p>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="space-y-1.5 border-t border-gray-100 bg-store-surface/50 p-3.5">
                    <div className="flex justify-between text-sm text-store-muted">
                        <span>Subtotal</span>
                        <span>৳{Number(order.subtotal).toFixed(0)}</span>
                    </div>
                    <div className="flex justify-between text-sm text-store-muted">
                        <span>Delivery</span>
                        <span>৳{Number(order.delivery_charge).toFixed(0)}</span>
                    </div>
                    <div className="flex justify-between border-t border-gray-200 pt-3 text-base font-bold text-store-primary">
                        <span>Total</span>
                        <span>৳{Number(order.total).toFixed(0)}</span>
                    </div>
                </div>
            </div>
        </CustomerPanelLayout>
    );
}
