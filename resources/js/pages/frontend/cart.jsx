import { Head, Link, router } from '@inertiajs/react';
import { Trash2, Plus, Minus, ShoppingBag } from 'lucide-react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Cart({ cart }) {
    const items = Object.entries(cart || {}).map(([key, item]) => ({ ...item, cartKey: key }));
    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const tailorTotal = items.reduce(
        (sum, item) => sum + (item.tailor_service ? item.tailor_price * item.quantity : 0),
        0,
    );

    const updateQty = (cartKey, qty) => {
        router.patch(`/cart/${cartKey}`, { quantity: qty }, { preserveScroll: true });
    };

    const remove = (cartKey) => {
        router.delete(`/cart/${cartKey}`, { preserveScroll: true });
    };

    return (
        <FrontendLayout>
            <Head title="কার্ট" />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-2xl font-bold text-gray-900">শপিং কার্ট</h1>

                {items.length === 0 ? (
                    <div className="py-16 text-center">
                        <ShoppingBag className="mx-auto mb-4 size-12 text-gray-300" />
                        <p className="mb-4 text-gray-500">কার্ট খালি আছে</p>
                        <Link
                            href="/"
                            className="bg-black px-6 py-2.5 text-sm text-white hover:bg-gray-800"
                        >
                            শপিং করুন
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-8 lg:grid-cols-3">
                        {/* Items */}
                        <div className="lg:col-span-2 space-y-4">
                            {items.map((item) => (
                                <div
                                    key={item.cartKey}
                                    className="flex gap-4 border border-gray-100 p-4"
                                >
                                    {item.image ? (
                                        <img
                                            src={item.image}
                                            alt={item.name}
                                            className="size-20 shrink-0 object-cover"
                                        />
                                    ) : (
                                        <div className="size-20 shrink-0 bg-gray-100" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-gray-900 line-clamp-2">{item.name}</p>
                                        {item.sku && (
                                            <p className="mt-0.5 text-xs text-gray-500">SKU: {item.sku}</p>
                                        )}
                                        {item.tailor_service && (
                                            <p className="mt-0.5 text-xs text-indigo-600">
                                                + টেইলর সার্ভিস ৳{item.tailor_price}
                                            </p>
                                        )}
                                        <div className="mt-2 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <button
                                                    onClick={() =>
                                                        item.quantity > 1 && updateQty(item.cartKey, item.quantity - 1)
                                                    }
                                                    className="border border-gray-300 p-1 hover:border-black"
                                                >
                                                    <Minus className="size-3" />
                                                </button>
                                                <span className="w-8 text-center text-sm tabular-nums">
                                                    {item.quantity}
                                                </span>
                                                <button
                                                    onClick={() => updateQty(item.cartKey, item.quantity + 1)}
                                                    className="border border-gray-300 p-1 hover:border-black"
                                                >
                                                    <Plus className="size-3" />
                                                </button>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="font-semibold">
                                                    ৳{(item.price * item.quantity).toFixed(0)}
                                                </span>
                                                <button
                                                    onClick={() => remove(item.cartKey)}
                                                    className="text-red-400 hover:text-red-600"
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Summary */}
                        <div className="h-fit border border-gray-200 p-5">
                            <h2 className="mb-4 font-semibold text-gray-900">অর্ডার সারসংক্ষেপ</h2>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between text-gray-600">
                                    <span>সাবটোটাল</span>
                                    <span>৳{subtotal.toFixed(0)}</span>
                                </div>
                                {tailorTotal > 0 && (
                                    <div className="flex justify-between text-gray-600">
                                        <span>টেইলর চার্জ</span>
                                        <span>৳{tailorTotal.toFixed(0)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between text-gray-600">
                                    <span>ডেলিভারি</span>
                                    <span className="text-gray-400">চেকআউটে নির্ধারিত</span>
                                </div>
                                <div className="border-t border-gray-200 pt-2">
                                    <div className="flex justify-between font-semibold text-gray-900">
                                        <span>মোট</span>
                                        <span>৳{(subtotal + tailorTotal).toFixed(0)}+</span>
                                    </div>
                                </div>
                            </div>
                            <Link
                                href="/checkout"
                                className="mt-4 block w-full bg-black py-3 text-center text-sm font-semibold text-white hover:bg-gray-800"
                            >
                                চেকআউট করুন
                            </Link>
                            <Link
                                href="/"
                                className="mt-2 block text-center text-sm text-gray-500 underline hover:text-gray-700"
                            >
                                শপিং চালিয়ে যান
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </FrontendLayout>
    );
}
