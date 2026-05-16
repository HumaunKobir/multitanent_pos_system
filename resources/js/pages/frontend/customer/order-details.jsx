import { Head, Link } from '@inertiajs/react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

const statusLabels = {
    1: 'অপেক্ষমান',
    2: 'প্রক্রিয়াধীন',
    3: 'শিপিং',
    5: 'ডেলিভার হয়েছে',
    6: 'বাতিল',
};

export default function OrderDetails({ order }) {
    return (
        <FrontendLayout>
            <Head title={`অর্ডার #${order.id}`} />
            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-bold text-gray-900">অর্ডার #{order.id}</h1>
                    <Link href="/customer/orders" className="text-sm text-gray-500 underline hover:text-gray-700">
                        ← আমার অর্ডার
                    </Link>
                </div>

                <div className="mb-5 grid gap-4 sm:grid-cols-2">
                    <div className="border border-gray-200 p-4 text-sm">
                        <p className="mb-1 font-medium text-gray-900">ডেলিভারি ঠিকানা</p>
                        <p className="text-gray-600">{order.name}</p>
                        <p className="text-gray-600">{order.phone}</p>
                        <p className="text-gray-600">{order.address}</p>
                    </div>
                    <div className="border border-gray-200 p-4 text-sm">
                        <p className="mb-1 font-medium text-gray-900">অর্ডার তথ্য</p>
                        <p className="text-gray-600">
                            স্ট্যাটাস: <span className="font-medium">{statusLabels[order.status] ?? order.status}</span>
                        </p>
                        <p className="text-gray-600">
                            পেমেন্ট: <span className="font-medium">{order.payment_status === 'Paid' ? 'পরিশোধিত' : 'অপেক্ষমান'}</span>
                        </p>
                        <p className="text-gray-600">
                            পদ্ধতি: <span className="font-medium">{order.payment_method?.toUpperCase()}</span>
                        </p>
                    </div>
                </div>

                <div className="border border-gray-200">
                    <div className="divide-y divide-gray-100">
                        {order.products?.map((item, i) => (
                            <div key={i} className="flex items-center gap-4 p-4">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-gray-900 line-clamp-2">{item.name}</p>
                                    {item.sku && <p className="text-xs text-gray-500">SKU: {item.sku}</p>}
                                    {item.tailor_service && (
                                        <p className="text-xs text-indigo-600">+ টেইলর সার্ভিস</p>
                                    )}
                                </div>
                                <div className="text-right text-sm">
                                    <p className="text-gray-500">× {item.quantity}</p>
                                    <p className="font-medium text-gray-900">৳{Number(item.total_price).toFixed(0)}</p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="space-y-1.5 border-t border-gray-100 p-4 text-sm">
                        <div className="flex justify-between text-gray-600">
                            <span>সাবটোটাল</span>
                            <span>৳{Number(order.subtotal).toFixed(0)}</span>
                        </div>
                        {Number(order.tailor_price) > 0 && (
                            <div className="flex justify-between text-gray-600">
                                <span>টেইলর চার্জ</span>
                                <span>৳{Number(order.tailor_price).toFixed(0)}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-gray-600">
                            <span>ডেলিভারি চার্জ</span>
                            <span>৳{Number(order.delivery_charge).toFixed(0)}</span>
                        </div>
                        <div className="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900">
                            <span>মোট</span>
                            <span>৳{Number(order.total).toFixed(0)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </FrontendLayout>
    );
}
