import { Link } from '@inertiajs/react';
import { Clock, Package } from 'lucide-react';
import { CustomerPortalLayout } from '@/layouts/frontend/customer-portal-layout';

export default function CustomerDashboard({ customer, orderCount, pendingCount }) {
    return (
        <CustomerPortalLayout title="My Account">
            <div className="rounded-xl border border-white/10 bg-white/10 p-6 backdrop-blur-md">
                <h1 className="text-xl font-bold text-white sm:text-2xl">Welcome, {customer.name}</h1>
                <p className="mt-1 text-sm text-white/60">{customer.phone}</p>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur-md">
                    <div className="flex items-center gap-3">
                        <Package className="size-8 text-store-accent" />
                        <div>
                            <p className="text-2xl font-bold text-white">{orderCount}</p>
                            <p className="text-sm text-white/60">Total orders</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur-md">
                    <div className="flex items-center gap-3">
                        <Clock className="size-8 text-yellow-400" />
                        <div>
                            <p className="text-2xl font-bold text-white">{pendingCount}</p>
                            <p className="text-sm text-white/60">Pending</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Link href="/customer/orders" className="rounded-xl border border-white/10 bg-white/5 p-4 text-sm font-medium text-white backdrop-blur-md transition-colors hover:bg-white/10">
                    View all orders →
                </Link>
                <Link href="/" className="rounded-xl border border-white/10 bg-white/5 p-4 text-sm font-medium text-white backdrop-blur-md transition-colors hover:bg-white/10">
                    Continue shopping →
                </Link>
            </div>
        </CustomerPortalLayout>
    );
}
