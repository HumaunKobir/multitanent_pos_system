import { Head, useForm, usePage } from '@inertiajs/react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Contact({ email, phone, address }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        subject: '',
        message: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/contact', { onSuccess: () => reset() });
    };

    return (
        <FrontendLayout>
            <Head title="যোগাযোগ" />
            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="mb-8 text-2xl font-bold text-gray-900">যোগাযোগ করুন</h1>

                <div className="grid gap-10 lg:grid-cols-2">
                    <div>
                        <h2 className="mb-4 font-semibold text-gray-900">যোগাযোগের তথ্য</h2>
                        <div className="space-y-3 text-sm text-gray-600">
                            {phone && <p>📞 {phone}</p>}
                            {email && <p>✉️ {email}</p>}
                            {address && <p>📍 {address}</p>}
                        </div>
                    </div>

                    <div>
                        {flash?.success && (
                            <div className="mb-4 border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                                {flash.success}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-4">
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

                            <div className="grid gap-4 sm:grid-cols-2">
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
                                    <label className="mb-1 block text-sm font-medium text-gray-700">ফোন</label>
                                    <input
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">বিষয়</label>
                                <input
                                    type="text"
                                    value={data.subject}
                                    onChange={(e) => setData('subject', e.target.value)}
                                    className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    বার্তা <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    rows={5}
                                    className="w-full border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none"
                                    required
                                />
                                {errors.message && (
                                    <p className="mt-1 text-xs text-red-500">{errors.message}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-black px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                            >
                                {processing ? 'পাঠানো হচ্ছে...' : 'বার্তা পাঠান'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </FrontendLayout>
    );
}
