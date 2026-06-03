import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { HeroSlider } from '@/components/frontend/hero-slider';
import { SectionBlock } from '@/components/frontend/section-block';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Home({ sliders, collections, productSections }) {
    const hasContent =
        (sliders?.length ?? 0) > 0 ||
        (collections?.length ?? 0) > 0 ||
        (productSections?.length ?? 0) > 0;

    return (
        <FrontendLayout isHome>
            <Head title="Home" />
            <HeroSlider sliders={sliders} />
            <CollectionTags collections={collections} />
            {productSections.map((section, i) => (
                <motion.div
                    key={section.id}
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: '-50px' }}
                    transition={{ delay: i * 0.05 }}
                >
                    <SectionBlock section={section} />
                </motion.div>
            ))}
            {!hasContent && (
                <div className="store-container flex flex-col items-center justify-center py-20 text-center">
                    <p className="text-lg font-medium text-store-primary">New products coming soon</p>
                    <p className="mt-2 text-sm text-store-muted">No products have been added yet</p>
                </div>
            )}

        </FrontendLayout>
    );
}

function CollectionTags({ collections }) {
    if (!collections?.length) {
        return null;
    }

    return (
        <section className="store-container py-6">
            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-store-muted">Collections</h2>
            <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-none">
                {collections.map((col) => (
                    <Link
                        key={col.id}
                        href={`/collection/${encodeURIComponent(col.name)}`}
                        className="flex shrink-0 items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-all hover:border-store-accent hover:shadow-md"
                    >
                        {col.image && (
                            <img src={col.image} alt={col.name} className="size-6 rounded-full object-cover" />
                        )}
                        {col.name}
                    </Link>
                ))}
            </div>
        </section>
    );
}
