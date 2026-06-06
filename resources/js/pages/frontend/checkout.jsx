import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, ShieldCheck, Truck } from 'lucide-react';
import { RequiredMark } from '@/components/form-field';
import { CustomerAuthField, CustomerAuthTextarea } from '@/components/frontend/customer-auth-field';
import { PaymentMethodCards } from '@/components/frontend/payment-method-cards';
import { StoreButton } from '@/components/frontend/store-button';
import FrontendLayout from '@/layouts/frontend/frontend-layout';
import { cn } from '@/lib/utils';

function formatPrice(value) {
    return Number(value).toLocaleString('en-BD', { maximumFractionDigits: 0 });
}

function DeliveryZoneOptions({ value, onChange, error, deliveryCharges = {} }) {
    const insideCharge = deliveryCharges.inside_dhaka ?? 60;
    const outsideCharge = deliveryCharges.outside_dhaka ?? 120;

    const deliveryZones = [
        { value: '1', label: 'Inside Dhaka', desc: `Delivery ৳${formatPrice(insideCharge)}` },
        { value: '2', label: 'Outside Dhaka', desc: `Delivery ৳${formatPrice(outsideCharge)}` },
    ];

    return (
        <div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {deliveryZones.map(({ value: zoneValue, label, desc }) => {
                    const selected = value === zoneValue;

                    return (
                        <label
                            key={zoneValue}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition-all',
                                selected
                                    ? 'border-store-accent/40 bg-store-accent/5 ring-2 ring-store-accent/20'
                                    : 'border-gray-200 bg-white hover:border-store-accent/30 hover:bg-store-surface/50',
                            )}
                        >
                            <input
                                type="radio"
                                name="city_id"
                                value={zoneValue}
                                checked={selected}
                                onChange={() => onChange(zoneValue)}
                                className="mt-1 size-4 shrink-0 accent-store-accent"
                            />
                            <span className="min-w-0">
                                <span className="block text-sm font-semibold text-store-primary">{label}</span>
                                <span className="mt-0.5 block text-xs text-store-muted">{desc}</span>
                            </span>
                        </label>
                    );
                })}
            </div>
            {error && <p className="mt-1.5 text-xs font-medium text-store-accent">{error}</p>}
        </div>
    );
}

function CheckoutSection({ badge, title, subtitle, children }) {
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

function CheckoutSteps({ currentStep, steps }) {
    return (
        <div className="mb-5 overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
            <div className="grid grid-cols-3 divide-x divide-gray-200">
                {steps.map((step, index) => {
                    const active = index <= currentStep;

                    return (
                        <div
                            key={step}
                            className={cn(
                                'relative px-3 py-3 text-center',
                                active ? 'bg-store-surface/40' : 'bg-white',
                            )}
                        >
                            {active && (
                                <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />
                            )}
                            <span
                                className={cn(
                                    'inline-flex size-6 items-center justify-center rounded-full text-[11px] font-bold',
                                    active
                                        ? 'bg-store-accent text-white'
                                        : 'bg-gray-100 text-store-muted ring-1 ring-gray-200',
                                )}
                            >
                                {index + 1}
                            </span>
                            <p
                                className={cn(
                                    'mt-1.5 text-[10px] font-semibold uppercase tracking-wide',
                                    active ? 'text-store-primary' : 'text-store-muted',
                                )}
                            >
                                {step}
                            </p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function OrderSummaryPanel({ items, subtotal, deliveryCharge, total, processing }) {
    const assurances = [
        { icon: Truck, title: 'Cash on delivery', desc: 'Inside & outside Dhaka' },
        { icon: ShieldCheck, title: 'Secure checkout', desc: 'SSLCommerz · COD' },
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
                <p className="text-[11px] text-store-muted">{items.length} item{items.length === 1 ? '' : 's'} in your bag</p>
            </div>

            <ul className="max-h-52 divide-y divide-gray-200 overflow-y-auto">
                {items.map((item, index) => (
                    <li key={index} className="flex items-start gap-3 px-4 py-2.5">
                        {item.image ? (
                            <img src={item.image} alt="" className="size-12 shrink-0 rounded-md object-cover ring-1 ring-gray-200" />
                        ) : (
                            <div className="size-12 shrink-0 rounded-md bg-store-surface ring-1 ring-gray-200" />
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="line-clamp-2 text-[11px] font-semibold text-store-primary">{item.name}</p>
                            {item.sku && <p className="mt-0.5 text-[10px] text-store-muted">{item.sku}</p>}
                            <p className="mt-1 text-[10px] text-store-muted">Qty {item.quantity}</p>
                        </div>
                        <p className="shrink-0 text-[11px] font-bold text-store-accent">
                            ৳{formatPrice(item.price * item.quantity)}
                        </p>
                    </li>
                ))}
            </ul>

            <div className="space-y-2 border-t border-gray-200 px-4 py-3 text-[11px]">
                <div className="flex justify-between text-store-muted">
                    <span>Subtotal</span>
                    <span className="font-medium text-store-primary">৳{formatPrice(subtotal)}</span>
                </div>
                <div className="flex justify-between text-store-muted">
                    <span>Delivery</span>
                    <span className="font-medium text-store-primary">৳{formatPrice(deliveryCharge)}</span>
                </div>
                <div className="flex justify-between border-t border-gray-200 pt-2 text-sm font-bold">
                    <span className="text-store-primary">Total</span>
                    <span className="text-store-accent">৳{formatPrice(total)}</span>
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

            <div className="border-t border-gray-200 px-4 py-3">
                <StoreButton type="submit" variant="accent" className="w-full rounded-xl py-3 text-sm font-bold" disabled={processing}>
                    {processing ? 'Processing...' : `Place Order — ৳${formatPrice(total)}`}
                </StoreButton>
            </div>
        </div>
    );
}

export default function Checkout({ cart, customer }) {
    const { deliveryCharges = {} } = usePage().props;
    const items = cart || [];
    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);

    const insideCharge = deliveryCharges.inside_dhaka ?? 60;
    const outsideCharge = deliveryCharges.outside_dhaka ?? 120;

    const { data, setData, post, processing, errors } = useForm({
        name: customer?.name ?? '',
        email: customer?.email ?? '',
        phone: customer?.phone ?? '',
        address: '',
        payment_method: 'cod',
        city_id: '1',
        zone_id: '',
        area_id: '',
    });

    const deliveryCharge = data.city_id === '1' ? insideCharge : outsideCharge;
    const total = subtotal + deliveryCharge;

    const submit = (e) => {
        e.preventDefault();
        post('/checkout');
    };

    const steps = ['Contact', 'Shipping', 'Payment'];
    const currentStep = data.address ? (data.payment_method ? 2 : 1) : 0;

    return (
        <FrontendLayout>
            <Head title="Checkout" />
            <div className="bg-store-surface/60">
                <div className="store-container py-4 pb-28 sm:py-5 lg:pb-5">
                    <div className="mb-4">
                        <span className="inline-block rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-muted ring-1 ring-gray-200">
                            Checkout
                        </span>
                        <h1 className="mt-2 text-xl font-bold text-store-primary sm:text-2xl">Complete your order</h1>
                        {!customer && (
                            <p className="mt-1 text-[11px] text-store-muted">
                                Checking out as a guest?{' '}
                                <Link href="/customer/login" className="font-semibold text-store-accent hover:underline">
                                    Log in
                                </Link>
                            </p>
                        )}
                    </div>

                    <CheckoutSteps currentStep={currentStep} steps={steps} />

                    <form id="checkout-form" onSubmit={submit} className="grid gap-4 lg:grid-cols-3 lg:items-start">
                        <div className="space-y-4 lg:col-span-2">
                            <CheckoutSection
                                badge="Step 1"
                                title="Delivery details"
                                subtitle="Where should we deliver your order?"
                            >
                                <div className="space-y-3.5">
                                    <div className="grid gap-3.5 sm:grid-cols-2">
                                        <CustomerAuthField
                                            label="Full name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            error={errors.name}
                                            autoComplete="name"
                                            required
                                        />
                                        <CustomerAuthField
                                            label="Phone"
                                            type="tel"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            error={errors.phone}
                                            autoComplete="tel"
                                            required
                                        />
                                    </div>

                                    <CustomerAuthField
                                        label="Email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        error={errors.email}
                                        autoComplete="email"
                                    />

                                    <div>
                                        <p className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-store-muted">
                                            Delivery area
                                            <RequiredMark />
                                        </p>
                                        <DeliveryZoneOptions
                                            value={data.city_id}
                                            onChange={(v) => setData('city_id', v)}
                                            error={errors.city_id}
                                            deliveryCharges={deliveryCharges}
                                        />
                                    </div>

                                    <CustomerAuthTextarea
                                        label="Full address"
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        error={errors.address}
                                        rows={3}
                                        placeholder="House, road, area, district…"
                                        autoComplete="street-address"
                                        required
                                    />
                                </div>
                            </CheckoutSection>

                            <CheckoutSection badge="Step 2" title="Payment method" subtitle="Choose how you want to pay">
                                <PaymentMethodCards
                                    value={data.payment_method}
                                    onChange={(v) => setData('payment_method', v)}
                                    error={errors.payment_method}
                                />
                            </CheckoutSection>
                        </div>

                        <div className="lg:col-span-1">
                            <OrderSummaryPanel
                                items={items}
                                subtotal={subtotal}
                                deliveryCharge={deliveryCharge}
                                total={total}
                                processing={processing}
                            />
                        </div>
                    </form>
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 p-2 shadow-lg backdrop-blur-sm lg:hidden">
                <StoreButton
                    type="submit"
                    form="checkout-form"
                    variant="accent"
                    className="w-full rounded-xl py-2.5 text-xs font-bold"
                    disabled={processing}
                >
                    {processing ? 'Processing...' : `Place Order — ৳${formatPrice(total)}`}
                </StoreButton>
            </div>
        </FrontendLayout>
    );
}
