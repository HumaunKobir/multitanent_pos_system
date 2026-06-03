import { Head } from '@inertiajs/react';
import { PageHero } from '@/components/frontend/page-hero';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

const titles = {
    about: 'About Us',
    faq: 'FAQ',
    'size-guide': 'Size Guide',
    'refund-policy': 'Refund Policy',
    'cancellation-policy': 'Cancellation Policy',
    'privacy-policy': 'Privacy Policy',
    'terms-policy': 'Terms of Service',
};

export default function StaticPage({ page, content }) {
    const title = titles[page] ?? page;

    return (
        <FrontendLayout>
            <Head title={title} />
            <PageHero title={title} />
            <div className="store-container max-w-3xl py-8">
                {content ? (
                    <div
                        className="prose prose-sm max-w-none text-store-primary prose-headings:text-store-primary prose-a:text-store-accent"
                        dangerouslySetInnerHTML={{ __html: content }}
                    />
                ) : (
                    <p className="text-center text-store-muted">Content for this page has not been added yet.</p>
                )}
            </div>
        </FrontendLayout>
    );
}
