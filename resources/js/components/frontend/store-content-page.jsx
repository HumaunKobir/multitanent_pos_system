import { Head, Link, usePage } from '@inertiajs/react';
import { StoreButton } from '@/components/frontend/store-button';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export function StoreContentPage({
    title,
    subtitle,
    sectionTitle,
    content,
    defaultContent,
    heroImage,
    showExtras = false,
}) {
    const { siteName } = usePage().props;
    const brand = siteName ?? 'Coolness Point';

    return (
        <FrontendLayout hideTrustStrip={!showExtras}>
            <Head title={title} />

            <section className="relative min-h-[200px] overflow-hidden bg-store-primary sm:min-h-[260px] lg:min-h-[300px]">
                {heroImage ? (
                    <img src={heroImage} alt="" className="absolute inset-0 size-full object-cover" />
                ) : (
                    <div className="absolute inset-0 store-gradient opacity-60" aria-hidden />
                )}
                <div
                    className="absolute inset-0 bg-gradient-to-r from-store-primary/95 via-store-primary/80 to-store-primary/50"
                    aria-hidden
                />
                <div className="absolute inset-0 auth-grid-overlay opacity-20" aria-hidden />

                <div className="relative store-container flex min-h-[inherit] flex-col justify-center py-10 sm:py-12">
                    <p className="text-xs font-semibold uppercase tracking-widest text-store-accent">
                        Welcome to {brand}
                    </p>
                    <h1 className="mt-2 text-3xl font-bold text-white sm:text-4xl">{title}</h1>
                    <p className="mt-3 max-w-lg text-sm leading-relaxed text-white/80 sm:text-base">{subtitle}</p>
                </div>
            </section>

            <section className="store-container py-10 sm:py-12">
                <div className="max-w-3xl">
                    <h2 className="text-lg font-bold text-store-primary sm:text-xl">{sectionTitle}</h2>
                    <div className="mt-1 h-1 w-10 rounded-full store-gradient" aria-hidden />
                    <div className="mt-5">
                        {content ? (
                            <div
                                className="prose prose-sm max-w-none text-store-primary prose-p:leading-relaxed prose-p:text-store-muted prose-headings:text-store-primary prose-a:text-store-accent"
                                dangerouslySetInnerHTML={{ __html: content }}
                            />
                        ) : (
                            <p className="text-[15px] leading-relaxed text-store-muted sm:text-base">{defaultContent}</p>
                        )}
                    </div>
                </div>
            </section>

            {showExtras && (
                <section className="relative overflow-hidden bg-store-primary py-10 sm:py-12">
                    <div className="absolute inset-0 store-gradient opacity-30" aria-hidden />
                    <div className="relative store-container text-center">
                        <h2 className="text-xl font-bold text-white sm:text-2xl">Ready to explore?</h2>
                        <p className="mx-auto mt-2 max-w-md text-sm text-white/75">
                            Discover our latest products or send us a message anytime.
                        </p>
                        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
                            <Link href="/">
                                <StoreButton variant="primary" className="rounded-full px-6">
                                    Shop Now
                                </StoreButton>
                            </Link>
                            <Link href="/contact">
                                <StoreButton
                                    variant="outline"
                                    className="rounded-full border-white/30 bg-white/10 px-6 text-white hover:border-white hover:bg-white hover:text-store-primary"
                                >
                                    Contact Us
                                </StoreButton>
                            </Link>
                        </div>
                    </div>
                </section>
            )}
        </FrontendLayout>
    );
}
