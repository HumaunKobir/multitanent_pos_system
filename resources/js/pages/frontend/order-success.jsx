import { Head, Link } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function OrderSuccess({ order }) {
    return (
        <FrontendLayout>
            <Head title="অর্ডার সফল" />
            <div className="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <CheckCircle className="mx-auto mb-4 size-16 text-green-500" />
                <h1 className="mb-2 text-2xl font-bold text-gray-900">অর্ডার সফলভাবে হয়েছে!</h1>
                <p className="mb-1 text-gray-600">আপনার অর্ডার নম্বর: <strong>#{order.id}</strong></p>
                <p className="mb-8 text-sm text-gray-500">
                    আমরা শীঘ্রই আপনার সাথে যোগাযোগ করব।
                </p>

                <div className="mb-8 border border-gray-200 p-5 text-left">
                    <h2 className="mb-3 font-semibold text-gray-900">অর্ডারের বিবরণ</h2>
                    <div className="divide-y divide-gray-100">
                        {order.products?.map((item, i) => (
                            <div key={i} className="flex justify-between py-2 text-sm text-gray-600">
                                <span>{item.name} × {item.quantity}</span>
                                <span>৳{(item.total_price).toFixed(0)}</span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 border-t border-gray-100 pt-3 text-sm">
                        <div className="flex justify-between text-gray-600">
                            <span>ডেলিভারি চার্জ</span>
                            <span>৳{Number(order.delivery_charge).toFixed(0)}</span>
                        </div>
                        <div className="mt-1 flex justify-between font-semibold text-gray-900">
                            <span>মোট</span>
                            <span>৳{Number(order.total).toFixed(0)}</span>
                        </div>
                    </div>
                </div>

                <div className="flex justify-center gap-3">
                    <Link
                        href="/"
                        className="bg-black px-6 py-2.5 text-sm text-white hover:bg-gray-800"
                    >
                        হোমে ফিরুন
                    </Link>
                    <Link
                        href="/customer/orders"
                        className="border border-gray-300 px-6 py-2.5 text-sm text-gray-700 hover:border-black"
                    >
                        আমার অর্ডার
                    </Link>
                </div>
            </div>
        </FrontendLayout>
    );
}
