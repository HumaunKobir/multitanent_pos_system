import { Link } from '@inertiajs/react';
import { CustomerPortalLayout } from '@/layouts/frontend/customer-portal-layout';

const statusLabels = {
    1: 'Pending',
    2: 'Processing',
    3: 'Shipping',
    5: 'Delivered',
    6: 'Cancelled',
};

export default function OrderDetails({ order }) {
    return (
        <CustomerPortalLayout title={`Order #${order.id}`}>
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-xl font-bold text-white">Order #{order.id}</h1>
                <Link href="/customer/orders" className="text-sm text-white/60 hover:text-white">
                    ← Back
                </Link>
            </div>

            <div className="mb-4 grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border border-white/10 bg-white/10 p-4 text-sm backdrop-blur-md">
                    <p className="mb-2 font-medium text-white">Delivery</p>
                    <p className="text-white/70">{order.name}</p>
                    <p className="text-white/70">{order.phone}</p>
                    <p className="text-white/70">{order.address}</p>
                </div>
                <div className="rounded-xl border border-white/10 bg-white/10 p-4 text-sm backdrop-blur-md">
                    <p className="mb-2 font-medium text-white">Order info</p>
                    <p className="text-white/70">Status: {statusLabels[order.status] ?? order.status}</p>
                    <p className="text-white/70">Payment: {order.payment_status}</p>
                    <p className="text-white/70">Method: {order.payment_method?.toUpperCase()}</p>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-white/10 bg-white/10 backdrop-blur-md">
                <div className="divide-y divide-white/10">
                    {order.products?.map((item, i) => (
                        <div key={i} className="flex items-center justify-between gap-4 p-4 text-sm">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-white line-clamp-2">{item.name}</p>
                                {item.sku && <p className="text-xs text-white/50">{item.sku}</p>}
                            </div>
                            <div className="text-right text-white/80">
                                <p>× {item.quantity}</p>
                                <p className="font-medium">৳{Number(item.total_price).toFixed(0)}</p>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="space-y-1.5 border-t border-white/10 p-4 text-sm text-white/80">
                    <div className="flex justify-between">
                        <span>Subtotal</span>
                        <span>৳{Number(order.subtotal).toFixed(0)}</span>
                    </div>
                    <div className="flex justify-between">
                        <span>Delivery</span>
                        <span>৳{Number(order.delivery_charge).toFixed(0)}</span>
                    </div>
                    <div className="flex justify-between border-t border-white/10 pt-2 font-bold text-white">
                        <span>Total</span>
                        <span>৳{Number(order.total).toFixed(0)}</span>
                    </div>
                </div>
            </div>
        </CustomerPortalLayout>
    );
}
