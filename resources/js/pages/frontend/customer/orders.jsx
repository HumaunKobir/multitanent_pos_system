import { Head, Link } from '@inertiajs/react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

const statusColors = {
    1: 'bg-yellow-100 text-yellow-700',
    2: 'bg-blue-100 text-blue-700',
    3: 'bg-indigo-100 text-indigo-700',
    5: 'bg-green-100 text-green-700',
    6: 'bg-red-100 text-red-700',
};

const statusLabels = {
    1: 'অপেক্ষমান',
    2: 'প্রক্রিয়াধীন',
    3: 'শিপিং',
    5: 'ডেলিভার হয়েছে',
    6: 'বাতিল',
};

export default function CustomerOrders({ orders }) {
    return (
        <FrontendLayout>
            <Head title="আমার অর্ডার" />
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">আমার অর্ডার</h1>
                    <Link href="/customer/dashboard" className="text-sm text-gray-500 underline hover:text-gray-700">
                        ← ড্যাশবোর্ড
                    </Link>
                </div>

                {orders.data?.length === 0 ? (
                    <div className="py-16 text-center text-gray-400">
                        <p>কোনো অর্ডার নেই।</p>
                        <Link href="/" className="mt-3 inline-block text-sm text-gray-700 underline">
                            শপিং শুরু করুন
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {orders.data?.map((order) => (
                            <div key={order.id} className="border border-gray-200 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="font-medium text-gray-900">অর্ডার #{order.id}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {new Date(order.created_at).toLocaleDateString('bn-BD')}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span
                                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${statusColors[order.status] ?? 'bg-gray-100 text-gray-600'}`}
                                        >
                                            {statusLabels[order.status] ?? order.status}
                                        </span>
                                        <span className="font-semibold text-gray-900">৳{Number(order.total).toFixed(0)}</span>
                                        <Link
                                            href={`/customer/orders/${order.id}`}
                                            className="text-sm text-gray-500 underline hover:text-gray-700"
                                        >
                                            বিবরণ
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </FrontendLayout>
    );
}
