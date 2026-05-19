import { Head, useForm } from '@inertiajs/react';
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
        city_id: '',
        zone_id: '',
        area_id: '',
    });

    const deliveryCharge = data.city_id == '1' ? 60 : 120;
    const total = subtotal + deliveryCharge;

    const submit = (e) => {
        e.preventDefault();
        post('/checkout');
    };

    return (
        <FrontendLayout>
            <Head title="চেকআউট" />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-2xl font-bold text-gray-900">চেকআউট</h1>

                <form onSubmit={submit} className="grid gap-8 lg:grid-cols-3">
                    {/* Form fields */}
                    <div className="space-y-5 lg:col-span-2">
                        <h2 className="font-semibold text-gray-900">ডেলিভারি তথ্য</h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    নাম <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                                    required
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    ফোন নম্বর <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                                    required
                                />
                                {errors.phone && <p className="mt-1 text-xs text-red-500">{errors.phone}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">ইমেইল</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                সম্পূর্ণ ঠিকানা <span className="text-red-500">*</span>
                            </label>
                            <textarea
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                rows={3}
                                className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                                placeholder="বাড়ি/ফ্ল্যাট, রাস্তা, এলাকা, জেলা"
                                required
                            />
                            {errors.address && <p className="mt-1 text-xs text-red-500">{errors.address}</p>}
                        </div>

                        {/* Delivery area */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                ডেলিভারি এলাকা
                            </label>
                            <select
                                value={data.city_id}
                                onChange={(e) => setData('city_id', e.target.value)}
                                className="w-full border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                            >
                                <option value="">এলাকা বেছে নিন</option>
                                <option value="1">ঢাকা (৳60)</option>
                                <option value="2">ঢাকার বাইরে (৳120)</option>
                            </select>
                        </div>

                        {/* Payment method */}
                        <div>
                            <h2 className="mb-3 font-semibold text-gray-900">পেমেন্ট পদ্ধতি</h2>
                            <div className="space-y-2">
                                {[
                                    { value: 'cod', label: 'ক্যাশ অন ডেলিভারি (COD)' },
                                    { value: 'sslcommerz', label: 'অনলাইন পেমেন্ট (SSLCommerz)' },
                                    { value: 'bkash', label: 'বিকাশ' },
                                ].map((method) => (
                                    <label
                                        key={method.value}
                                        className="flex cursor-pointer items-center gap-3 border border-gray-200 p-3 hover:border-gray-400"
                                    >
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value={method.value}
                                            checked={data.payment_method === method.value}
                                            onChange={(e) => setData('payment_method', e.target.value)}
                                            className="size-4"
                                        />
                                        <span className="text-sm font-medium text-gray-800">{method.label}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Order summary */}
                    <div className="h-fit space-y-4 border border-gray-200 p-5">
                        <h2 className="font-semibold text-gray-900">অর্ডার সারসংক্ষেপ</h2>

                        <div className="divide-y divide-gray-100">
                            {items.map((item, i) => (
                                <div key={i} className="flex justify-between py-2 text-sm text-gray-600">
                                    <span className="flex-1 line-clamp-1">
                                        {item.name} × {item.quantity}
                                    </span>
                                    <span className="ml-2 tabular-nums">৳{(item.price * item.quantity).toFixed(0)}</span>
                                </div>
                            ))}
                        </div>

                        <div className="space-y-1.5 border-t border-gray-100 pt-3 text-sm">
                            <div className="flex justify-between text-gray-600">
                                <span>সাবটোটাল</span>
                                <span>৳{subtotal.toFixed(0)}</span>
                            </div>
                            <div className="flex justify-between text-gray-600">
                                <span>ডেলিভারি</span>
                                <span>৳{deliveryCharge}</span>
                            </div>
                            <div className="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900">
                                <span>মোট</span>
                                <span>৳{total.toFixed(0)}</span>
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-black py-3 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {processing ? 'প্রক্রিয়াধীন...' : 'অর্ডার নিশ্চিত করুন'}
                        </button>
                    </div>
                </form>
            </div>
        </FrontendLayout>
    );
}
