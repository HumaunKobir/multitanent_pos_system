import { Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck, CheckCircle2, Mail, MapPin, Package, Phone, ShieldCheck, Truck, User } from 'lucide-react';
import { StoreButton } from '@/components/frontend/store-button';
import FrontendLayout from '@/layouts/frontend/frontend-layout';
import { cn } from '@/lib/utils';

function formatPrice(value) {
    return Number(value).toLocaleString('en-BD', { maximumFractionDigits: 0 });
}

function SuccessSection({ badge, title, subtitle, children }) {
    return (
        <div className="overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
            <div className="relative overflow-hidden bg-linear-to-br from-store-surface/90 via-white to-white px-4 py-3">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <span className="inline-block rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-muted ring-1 ring-gray-200">
                            {badge}
                        </span>
                        <h2 className="mt-1.5 text-sm font-bold text-store-primary">{title}</h2>
                        {subtitle && <p className="mt-0.5 text-[11px] text-store-muted">{subtitle}</p>}
                    </div>
                </div>
            </div>
            <div className="px-4 py-4 sm:px-5">{children}</div>
        </div>
    );
}

function CompletedCheckoutSteps({ steps }) {
    return (
        <div className="mb-5 overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
            <div className="grid grid-cols-3 divide-x divide-gray-200">
                {steps.map((step, index) => (
                    <div key={step} className="relative bg-store-surface/40 px-3 py-3 text-center">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />
                        <span className="inline-flex size-6 items-center justify-center rounded-full bg-store-accent text-[11px] font-bold text-white">
                            {index + 1}
                        </span>
                        <p className="mt-1.5 text-[10px] font-semibold uppercase tracking-wide text-store-primary">{step}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DetailField({ icon: Icon, label, value }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex items-start gap-3">
            <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-store-accent/20 bg-store-accent/5">
                <Icon className="size-3.5 text-store-accent" aria-hidden />
            </span>
            <div className="min-w-0">
                <p className="text-[10px] font-semibold uppercase tracking-wide text-store-muted">{label}</p>
                <p className="mt-0.5 text-sm font-medium text-store-primary">{value}</p>
            </div>
        </div>
    );
}

function PaymentStatusBadge({ paymentMethod, paymentStatus }) {
    const isPaid = paymentStatus === 'Paid' || paymentStatus === 'paid';
    const isCod = paymentMethod === 'cod';

    const label = isCod
        ? 'Cash on Delivery'
        : paymentMethod === 'sslcommerz' && isPaid
          ? 'SSLCommerz · Paid'
          : paymentMethod === 'sslcommerz'
            ? 'SSLCommerz · Pending'
            : paymentStatus;

    const tone = isCod || isPaid ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200';

    return (
        <span className={cn('inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1', tone)}>
            {label}
        </span>
    );
}

function OrderSummaryPanel({ order }) {
    const assurances = [
        { icon: Truck, title: 'On its way', desc: 'We will process your order soon' },
        { icon: ShieldCheck, title: 'Secure order', desc: 'Your payment details are protected' },
        { icon: BadgeCheck, title: 'Quality assured', desc: '100% authentic products' },
    ];

    return (
        <div className="sticky top-20 overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
            <div className="relative overflow-hidden bg-linear-to-br from-store-surface/90 via-white to-white px-4 py-3">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />
                <span className="inline-block rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-muted ring-1 ring-gray-200">
                    Summary
                </span>
                <p className="mt-1.5 text-sm font-bold text-store-primary">Order summary</p>
                <p className="text-[11px] text-store-muted">
                    {order.products?.length ?? 0} item{(order.products?.length ?? 0) === 1 ? '' : 's'} in this order
                </p>
            </div>

            <ul className="max-h-60 divide-y divide-gray-200 overflow-y-auto">
                {order.products?.map((item, index) => (
                    <li key={index} className="flex items-start gap-3 px-4 py-2.5">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-md bg-store-surface ring-1 ring-gray-200">
                            <Package className="size-4 text-store-muted" aria-hidden />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="line-clamp-2 text-[11px] font-semibold text-store-primary">{item.name}</p>
                            {item.sku && <p className="mt-0.5 text-[10px] text-store-muted">{item.sku}</p>}
                            <p className="mt-1 text-[10px] text-store-muted">Qty {item.quantity}</p>
                        </div>
                        <p className="shrink-0 text-[11px] font-bold text-store-accent">৳{formatPrice(item.total_price)}</p>
                    </li>
                ))}
            </ul>

            <div className="space-y-2 border-t border-gray-200 px-4 py-3 text-[11px]">
                {order.subtotal != null && (
                    <div className="flex justify-between text-store-muted">
                        <span>Subtotal</span>
                        <span className="font-medium text-store-primary">৳{formatPrice(order.subtotal)}</span>
                    </div>
                )}
                <div className="flex justify-between text-store-muted">
                    <span>Delivery</span>
                    <span className="font-medium text-store-primary">৳{formatPrice(order.delivery_charge)}</span>
                </div>
                <div className="flex justify-between border-t border-gray-200 pt-2 text-sm font-bold">
                    <span className="text-store-primary">Total</span>
                    <span className="text-store-accent">৳{formatPrice(order.total)}</span>
                </div>
                <div className="flex items-center justify-between pt-1">
                    <span className="text-store-muted">Payment</span>
                    <PaymentStatusBadge paymentMethod={order.payment_method} paymentStatus={order.payment_status} />
                </div>
            </div>

            <div className="grid grid-cols-3 divide-x divide-gray-200 border-t border-gray-200">
                {assurances.map(({ icon: Icon, title, desc }) => (
                    <div key={title} className="px-2 py-2.5 text-center">
                        <span className="mx-auto inline-flex size-7 items-center justify-center rounded-lg border border-store-accent/20 bg-store-accent/5">
                            <Icon className="size-3.5 text-store-accent" aria-hidden />
                        </span>
                        <p className="mt-1.5 text-[9px] font-bold leading-tight text-store-primary">{title}</p>
                        <p className="mt-0.5 text-[8px] leading-snug text-store-muted">{desc}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function OrderSuccess({ order }) {
    const { flash } = usePage().props;
    const steps = ['Contact', 'Shipping', 'Payment'];

    return (
        <FrontendLayout>
            <Head title="Order Successful" />
            <div className="bg-store-surface/60">
                <div className="store-container py-4 pb-28 sm:py-5 lg:pb-5">
                    <div className="mb-4">
                        <span className="inline-block rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-muted ring-1 ring-gray-200">
                            Order placed
                        </span>
                        <h1 className="mt-2 text-xl font-bold text-store-primary sm:text-2xl">Thank you for your order!</h1>
                        <p className="mt-1 text-[11px] text-store-muted">
                            Order <span className="font-semibold text-store-primary">#{order.id}</span> has been placed successfully.
                        </p>
                    </div>

                    <CompletedCheckoutSteps steps={steps} />

                    {flash?.success && (
                        <div className="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100">
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" aria-hidden />
                            <p className="text-xs font-medium text-emerald-800">{flash.success}</p>
                        </div>
                    )}

                    <div className="mb-5 overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
                        <div className="relative overflow-hidden bg-linear-to-br from-emerald-50/80 via-white to-white px-4 py-5 text-center sm:px-6">
                            <div className="absolute inset-x-0 top-0 h-0.5 bg-emerald-500/80" aria-hidden />
                            <span className="mx-auto inline-flex size-14 items-center justify-center rounded-full bg-emerald-100 ring-4 ring-emerald-50">
                                <CheckCircle2 className="size-7 text-emerald-600" aria-hidden />
                            </span>
                            <p className="mt-3 text-sm font-bold text-store-primary">Your order has been placed successfully</p>
                            <p className="mt-1 text-[11px] text-store-muted">
                                We will review your order and notify you once it is confirmed and ready to ship.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-3 lg:items-start">
                        <div className="space-y-4 lg:col-span-2">
                            <SuccessSection badge="Delivery" title="Shipping details" subtitle="Where we will deliver your order">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <DetailField icon={User} label="Name" value={order.name} />
                                    <DetailField icon={Phone} label="Phone" value={order.phone} />
                                    <DetailField icon={Mail} label="Email" value={order.email} />
                                    <div className="sm:col-span-2">
                                        <DetailField icon={MapPin} label="Address" value={order.address} />
                                    </div>
                                </div>
                            </SuccessSection>

                            <div className="flex flex-wrap gap-3">
                                <Link href="/">
                                    <StoreButton variant="accent" className="rounded-xl px-5 py-2.5 text-sm font-bold">
                                        Continue Shopping
                                    </StoreButton>
                                </Link>
                                <Link href="/customer/orders">
                                    <StoreButton variant="outline" className="rounded-xl px-5 py-2.5 text-sm font-bold">
                                        View My Orders
                                    </StoreButton>
                                </Link>
                            </div>
                        </div>

                        <div className="lg:col-span-1">
                            <OrderSummaryPanel order={order} />
                        </div>
                    </div>
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 p-2 shadow-lg backdrop-blur-sm lg:hidden">
                <div className="grid grid-cols-2 gap-2">
                    <Link href="/" className="block">
                        <StoreButton variant="accent" className="w-full rounded-xl py-2.5 text-xs font-bold">
                            Continue Shopping
                        </StoreButton>
                    </Link>
                    <Link href="/customer/orders" className="block">
                        <StoreButton variant="outline" className="w-full rounded-xl py-2.5 text-xs font-bold">
                            My Orders
                        </StoreButton>
                    </Link>
                </div>
            </div>
        </FrontendLayout>
    );
}
