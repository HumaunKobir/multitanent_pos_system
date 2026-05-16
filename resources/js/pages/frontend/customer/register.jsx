import { Head, Link, useForm } from '@inertiajs/react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function CustomerRegister() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/customer/register');
    };

    return (
        <FrontendLayout>
            <Head title="রেজিস্ট্রেশন" />
            <div className="mx-auto max-w-md px-4 py-12 sm:px-6">
                <div className="mb-6 text-center">
                    <h1 className="text-2xl font-bold text-gray-900">অ্যাকাউন্ট তৈরি করুন</h1>
                    <p className="mt-1 text-sm text-gray-500">বিনামূল্যে রেজিস্ট্রেশন করুন</p>
                </div>

                <form onSubmit={submit} className="space-y-4 border border-gray-200 p-6">
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

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">ইমেইল</label>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                        />
                        {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            পাসওয়ার্ড <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                            required
                        />
                        {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            পাসওয়ার্ড নিশ্চিত করুন <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-black py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                    >
                        {processing ? 'রেজিস্ট্রেশন হচ্ছে...' : 'রেজিস্ট্রেশন করুন'}
                    </button>

                    <p className="text-center text-sm text-gray-600">
                        ইতিমধ্যে অ্যাকাউন্ট আছে?{' '}
                        <Link href="/customer/login" className="font-medium text-gray-900 underline">
                            লগইন করুন
                        </Link>
                    </p>
                </form>
            </div>
        </FrontendLayout>
    );
}
