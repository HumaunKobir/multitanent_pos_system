import { Head, Link, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { PaymentMethodCards } from '@/components/frontend/payment-method-cards';
import { StoreButton } from '@/components/frontend/store-button';
import { StoreInput } from '@/components/frontend/store-input';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Checkout({ cart, customer }) {
    const items = cart || [];
    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);

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

    const deliveryCharge = data.city_id === '1' ? 60 : 120;
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
            <div className="store-container py-6">
                <h1 className="mb-2 text-xl font-bold text-store-primary sm:text-2xl">Checkout</h1>

                {!customer && (
                    <p className="mb-4 text-sm text-store-muted">
                        Checking out as a guest?{' '}
                        <Link href="/customer/login" className="font-medium text-store-accent hover:underline">
                            Log in
                        </Link>
                    </p>
                )}

                <div className="mb-6 flex gap-2">
                    {steps.map((step, i) => (
                        <div key={step} className="flex flex-1 flex-col items-center gap-1">
                            <div
                                className={`flex size-6 items-center justify-center rounded-full text-xs font-bold ${
                                    i <= currentStep ? 'store-gradient text-white' : 'bg-gray-200 text-gray-500'
                                }`}
                            >
                                {i + 1}
                            </div>
                            <span className="text-[10px] text-store-muted">{step}</span>
                        </div>
                    ))}
                </div>

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                            <h2 className="mb-3 font-semibold text-store-primary">Delivery details</h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <StoreInput
                                    label="Name *"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    error={errors.name}
                                    required
                                />
                                <StoreInput
                                    label="Phone *"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    error={errors.phone}
                                    required
                                />
                            </div>
                            <div className="mt-3">
                                <StoreInput
                                    label="Email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    error={errors.email}
                                />
                            </div>
                            <div className="mt-3">
                                <label className="mb-1 block text-sm font-medium text-store-primary">City *</label>
                                <select
                                    value={data.city_id}
                                    onChange={(e) => setData('city_id', e.target.value)}
                                    className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-store-accent focus:outline-none"
                                >
                                    <option value="1">Dhaka (৳60)</option>
                                    <option value="2">Outside Dhaka (৳120)</option>
                                </select>
                            </div>
                            <div className="mt-3">
                                <StoreInput
                                    label="Full address *"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    error={errors.address}
                                    required
                                />
                            </div>
                        </div>

                        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                            <PaymentMethodCards
                                value={data.payment_method}
                                onChange={(v) => setData('payment_method', v)}
                                error={errors.payment_method}
                            />
                        </div>
                    </div>

                    <div>
                        <div className="sticky top-20 rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                            <h2 className="mb-3 font-semibold text-store-primary">Order summary</h2>
                            <ul className="mb-3 max-h-48 space-y-2 overflow-y-auto text-sm">
                                {items.map((item, i) => (
                                    <li key={i} className="flex justify-between gap-2">
                                        <span className="line-clamp-1 text-store-muted">
                                            {item.name} × {item.quantity}
                                        </span>
                                        <span className="shrink-0">৳{item.price * item.quantity}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="space-y-1.5 border-t border-gray-100 pt-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-store-muted">Subtotal</span>
                                    <span>৳{subtotal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-store-muted">Delivery</span>
                                    <span>৳{deliveryCharge}</span>
                                </div>
                                <div className="flex justify-between font-bold">
                                    <span>Total</span>
                                    <span className="text-store-accent">৳{total}</span>
                                </div>
                            </div>

                            <div className="mt-3 flex items-center gap-2 rounded-md bg-store-warm/50 p-2 text-[11px] text-store-primary">
                                <ShieldCheck className="size-4 shrink-0 text-store-accent" />
                                Secure checkout · COD · SSLCommerz · bKash
                            </div>

                            <StoreButton type="submit" className="mt-4 w-full" disabled={processing}>
                                {processing ? 'Processing...' : `Place Order — ৳${total}`}
                            </StoreButton>
                        </div>
                    </div>
                </form>
            </div>
        </FrontendLayout>
    );
}
