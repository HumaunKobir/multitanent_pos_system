import { Link } from '@inertiajs/react';
import { CustomerPortalLayout } from '@/layouts/frontend/customer-portal-layout';

const statusColors = {
    1: 'bg-yellow-500/20 text-yellow-300',
    2: 'bg-blue-500/20 text-blue-300',
    3: 'bg-indigo-500/20 text-indigo-300',
    5: 'bg-green-500/20 text-green-300',
    6: 'bg-red-500/20 text-red-300',
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
        <CustomerPortalLayout title="My Orders">
            <h1 className="mb-4 text-xl font-bold text-white">My Orders</h1>

            {orders.data?.length === 0 ? (
                <div className="rounded-xl border border-white/10 bg-white/5 py-16 text-center backdrop-blur-md">
                    <p className="text-white/60">No orders yet</p>
                    <Link href="/" className="mt-3 inline-block text-sm text-store-accent hover:underline">
                        Start shopping
                    </Link>
                </div>
            ) : (
                <div className="space-y-3">
                    {orders.data?.map((order) => (
                        <div key={order.id} className="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur-md">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="font-medium text-white">#{order.id}</p>
                                    <p className="text-xs text-white/50">
                                        {new Date(order.created_at).toLocaleDateString('en-US')}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${statusColors[order.status] ?? 'bg-white/10 text-white/70'}`}>
                                        {statusLabels[order.status] ?? order.status}
                                    </span>
                                    <span className="font-semibold text-white">৳{Number(order.total).toFixed(0)}</span>
                                    <Link href={`/customer/orders/${order.id}`} className="text-xs text-store-accent hover:underline">
                                        Details
                                    </Link>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </CustomerPortalLayout>
    );
}
