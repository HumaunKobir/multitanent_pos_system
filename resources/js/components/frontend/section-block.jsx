import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ProductCard } from '@/components/frontend/product-card';

export function SectionBlock({ section }) {
    if (section.block_type === 1) {
        return <ImageSection section={section} />;
    }

    return <ProductGrid section={section} />;
}

function ProductGrid({ section }) {
    if (!section.products?.length) {
        return null;
    }

    const cols = {
        2: 'grid-cols-2',
        3: 'grid-cols-2 sm:grid-cols-3',
        4: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
    }[section.block_per_line] ?? 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4';

    return (
        <section className="store-container py-6">
            <SectionHeading section={section} />
            <div className={`grid gap-3 sm:gap-4 ${cols}`}>
                {section.products.map((product) => (
                    <ProductCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}

function ImageSection({ section }) {
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        if (!section.images?.length || section.images.length <= 1) {
            return;
        }
        const timer = setInterval(() => {
            setCurrent((c) => (c === section.images.length - 1 ? 0 : c + 1));
        }, 5000);
        return () => clearInterval(timer);
    }, [section.images]);

    if (!section.images?.length) {
        return null;
    }

    return (
        <section className="store-container py-6">
            <SectionHeading section={section} />
            <div className="overflow-hidden rounded-lg">
                <img
                    src={section.images[current]}
                    alt={section.name}
                    className="h-48 w-full object-cover sm:h-64 lg:h-80"
                />
            </div>
        </section>
    );
}

function SectionHeading({ section }) {
    return (
        <div className="mb-4 flex items-end justify-between gap-4">
            <div>
                <div className="flex items-center gap-3">
                    <div className="h-px w-8 bg-store-accent" />
                    <h2 className="text-lg font-bold text-store-primary sm:text-xl">{section.name}</h2>
                    <div className="h-px flex-1 max-w-16 bg-store-accent/30" />
                </div>
                {section.description && (
                    <p className="mt-1 text-sm text-store-muted">{section.description}</p>
                )}
            </div>
            {section.button_text && (
                <Link
                    href={`/section/${section.id}/products`}
                    className="shrink-0 text-xs font-semibold text-store-accent hover:underline sm:text-sm"
                >
                    {section.button_text}
                </Link>
            )}
        </div>
    );
}
