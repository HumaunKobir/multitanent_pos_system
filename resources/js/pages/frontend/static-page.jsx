import { Head } from '@inertiajs/react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

const titles = {
    about: 'আমাদের সম্পর্কে',
    faq: 'সচরাচর জিজ্ঞাসা',
    'size-guide': 'সাইজ গাইড',
    'refund-policy': 'রিফান্ড পলিসি',
    'cancellation-policy': 'বাতিল পলিসি',
    'privacy-policy': 'প্রাইভেসি পলিসি',
    'terms-policy': 'শর্তাবলী',
};

export default function StaticPage({ page, content }) {
    const title = titles[page] ?? page;

    return (
        <FrontendLayout>
            <Head title={title} />
            <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-2xl font-bold text-gray-900">{title}</h1>
                {content ? (
                    <div
                        className="prose prose-gray max-w-none"
                        dangerouslySetInnerHTML={{ __html: content }}
                    />
                ) : (
                    <p className="text-gray-500">এই পেজের তথ্য এখনও যোগ করা হয়নি।</p>
                )}
            </div>
        </FrontendLayout>
    );
}
