import { Head, Link, router } from '@inertiajs/react';
import { Package, Clock, LogOut } from 'lucide-react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function CustomerDashboard({ customer, orderCount, pendingCount }) {
    const logout = () => {
        router.post('/customer/logout');
    };

    return (
        <FrontendLayout>
            <Head title="আমার অ্যাকাউন্ট" />
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">স্বাগতম, {customer.name}</h1>
                        <p className="mt-1 text-sm text-gray-500">{customer.phone}</p>
                    </div>
                    <button
                        onClick={logout}
                        className="flex items-center gap-2 border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:border-red-400 hover:text-red-600"
                    >
                        <LogOut className="size-4" /> লগআউট
                    </button>
                </div>

                <div className="mb-8 grid gap-4 sm:grid-cols-2">
                    <div className="border border-gray-200 p-5">
                        <div className="flex items-center gap-3">
                            <Package className="size-6 text-gray-400" />
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{orderCount}</p>
                                <p className="text-sm text-gray-500">মোট অর্ডার</p>
                            </div>
                        </div>
                    </div>
                    <div className="border border-gray-200 p-5">
                        <div className="flex items-center gap-3">
                            <Clock className="size-6 text-gray-400" />
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{pendingCount}</p>
                                <p className="text-sm text-gray-500">অপেক্ষমান অর্ডার</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Link
                        href="/customer/orders"
                        className="border border-gray-200 p-4 text-sm font-medium text-gray-900 hover:border-black"
                    >
                        আমার সব অর্ডার →
                    </Link>
                    <Link
                        href="/"
                        className="border border-gray-200 p-4 text-sm font-medium text-gray-900 hover:border-black"
                    >
                        শপিং করুন →
                    </Link>
                </div>
            </div>
        </FrontendLayout>
    );
}
