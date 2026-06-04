import { Link } from '@inertiajs/react';
import { Clock, Package, Settings } from 'lucide-react';

import { CustomerPanelLayout } from '@/layouts/frontend/customer-panel-layout';

function isOnlineMember(registrationType) {
    return registrationType === 1 || registrationType === 'online' || registrationType === 'Online';
}

export default function CustomerDashboard({ customer, orderCount, pendingCount }) {
    const online = isOnlineMember(customer.registration_type);

    return (
        <CustomerPanelLayout title="Dashboard">
            <section className="overflow-hidden rounded-xl bg-[#ec6278] p-4 text-white shadow-sm">
                <p className="text-[9px] font-bold uppercase tracking-[0.2em] text-white/70">
                    {online ? 'Online member' : 'Store member'}
                </p>
                <h2 className="mt-1 text-lg font-bold tracking-tight sm:text-xl">
                    Welcome back, {customer.name}
                </h2>
                <p className="mt-1 max-w-md text-xs text-white/85">
                    {online
                        ? 'Track orders, update your profile, and shop with your member account.'
                        : 'Your store account is linked — update your profile or password anytime.'}
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                    <Link
                        href="/customer/orders"
                        className="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-1.5 text-xs font-semibold ring-1 ring-white/25 transition hover:bg-white/25"
                    >
                        <Package className="size-3.5" />
                        View orders
                    </Link>
                    <Link
                        href="/customer/settings"
                        className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/10"
                    >
                        <Settings className="size-3.5" />
                        Edit profile
                    </Link>
                </div>
            </section>

            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border border-gray-200/80 bg-white p-3.5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-store-accent/10 text-store-accent">
                            <Package className="size-4" />
                        </span>
                        <span className="text-xl font-bold text-store-primary">{orderCount}</span>
                    </div>
                    <p className="mt-2 text-xs font-medium text-store-muted">Total orders</p>
                </div>
                <div className="rounded-xl border border-gray-200/80 bg-white p-3.5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600">
                            <Clock className="size-4" />
                        </span>
                        <span className="text-xl font-bold text-store-primary">{pendingCount}</span>
                    </div>
                    <p className="mt-2 text-xs font-medium text-store-muted">Pending orders</p>
                </div>
            </div>
        </CustomerPanelLayout>
    );
}
