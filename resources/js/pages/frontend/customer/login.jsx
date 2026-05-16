import { Head, Link, useForm } from '@inertiajs/react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function CustomerLogin() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/customer/login');
    };

    return (
        <FrontendLayout>
            <Head title="লগইন" />
            <div className="mx-auto max-w-md px-4 py-12 sm:px-6">
                <div className="mb-6 text-center">
                    <h1 className="text-2xl font-bold text-gray-900">লগইন</h1>
                    <p className="mt-1 text-sm text-gray-500">আপনার অ্যাকাউন্টে প্রবেশ করুন</p>
                </div>

                <form onSubmit={submit} className="space-y-4 border border-gray-200 p-6">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            ইমেইল বা ফোন নম্বর
                        </label>
                        <input
                            type="text"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                            required
                        />
                        {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">পাসওয়ার্ড</label>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                            required
                        />
                        {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-black py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                    >
                        {processing ? 'লগইন হচ্ছে...' : 'লগইন করুন'}
                    </button>

                    <p className="text-center text-sm text-gray-600">
                        অ্যাকাউন্ট নেই?{' '}
                        <Link href="/customer/register" className="font-medium text-gray-900 underline">
                            রেজিস্ট্রেশন করুন
                        </Link>
                    </p>
                </form>
            </div>
        </FrontendLayout>
    );
}
