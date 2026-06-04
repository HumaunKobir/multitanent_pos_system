import { Link } from '@inertiajs/react';
import { ChevronRight, Package } from 'lucide-react';

import { CustomerPanelLayout } from '@/layouts/frontend/customer-panel-layout';

const statusColors = {
    1: 'bg-amber-100 text-amber-800',
    2: 'bg-blue-100 text-blue-800',
    3: 'bg-indigo-100 text-indigo-800',
    5: 'bg-emerald-100 text-emerald-800',
    6: 'bg-red-100 text-red-800',
};

const statusLabels = {
    1: 'Pending',
    2: 'Processing',
    3: 'Shipping',
    5: 'Delivered',
    6: 'Cancelled',
};

export default function CustomerOrders({ orders }) {
    return (
        <CustomerPanelLayout title="Online Orders">
            {orders.data?.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white py-12 text-center shadow-sm">
                    <span className="flex size-12 items-center justify-center rounded-lg bg-store-surface text-store-muted">
                        <Package className="size-6" />
                    </span>
                    <p className="mt-3 text-sm font-semibold text-store-primary">No orders yet</p>
                    <p className="mt-1 text-xs text-store-muted">When you place an order, it will show up here.</p>
                    <Link
                        href="/"
                        className="mt-4 inline-flex items-center gap-2 rounded-lg bg-store-accent px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                    >
                        Start shopping
                    </Link>
                </div>
            ) : (
                <div className="space-y-2">
                    {orders.data?.map((order) => (
                        <div
                            key={order.id}
                            className="group rounded-xl border border-gray-200/80 bg-white p-3.5 shadow-sm transition hover:border-store-accent/25"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="font-bold text-store-primary">Order #{order.id}</p>
                                    <p className="mt-0.5 text-xs text-store-muted">
                                        {new Date(order.created_at).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric',
                                        })}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <span
                                        className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusColors[order.status] ?? 'bg-store-surface text-store-muted'}`}
                                    >
                                        {statusLabels[order.status] ?? order.status}
                                    </span>
                                    <span className="text-base font-bold text-store-primary">
                                        ৳{Number(order.total).toFixed(0)}
                                    </span>
                                    <Link
                                        href={`/customer/orders/${order.id}`}
                                        className="inline-flex items-center gap-1 rounded-lg bg-store-surface px-2.5 py-1 text-xs font-semibold text-store-accent transition group-hover:bg-store-accent group-hover:text-white"
                                    >
                                        Details
                                        <ChevronRight className="size-3.5" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </CustomerPanelLayout>
    );
}
