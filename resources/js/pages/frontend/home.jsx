import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Home({ sliders, collections, productSections }) {
    return (
        <FrontendLayout>
            <Head title="হোম" />
            <HeroSlider sliders={sliders} />
            <CollectionTags collections={collections} />
            {productSections.map((section) => (
                <ProductSectionBlock key={section.id} section={section} />
            ))}
        </FrontendLayout>
    );
}

function HeroSlider({ sliders }) {
    const [current, setCurrent] = useState(0);

    if (!sliders?.length) {
        return (
            <div className="flex h-64 items-center justify-center bg-gray-100 sm:h-96">
                <p className="text-gray-400">স্লাইডার নেই</p>
            </div>
        );
    }

    const prev = () => setCurrent((c) => (c === 0 ? sliders.length - 1 : c - 1));
    const next = () => setCurrent((c) => (c === sliders.length - 1 ? 0 : c + 1));

    return (
        <div className="relative overflow-hidden bg-gray-100">
            <div
                className="flex transition-transform duration-500 ease-out"
                style={{ transform: `translateX(-${current * 100}%)` }}
            >
                {sliders.map((slider) => (
                    <div key={slider.id} className="relative min-w-full">
                        <img
                            src={slider.image}
                            alt={slider.name}
                            className="h-64 w-full object-cover sm:h-96 lg:h-[500px]"
                        />
                    </div>
                ))}
            </div>

            {sliders.length > 1 && (
                <>
                    <button
                        onClick={prev}
                        className="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow hover:bg-white"
                        aria-label="Previous"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <button
                        onClick={next}
                        className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow hover:bg-white"
                        aria-label="Next"
                    >
                        <ChevronRight className="size-5" />
                    </button>
                    <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                        {sliders.map((_, i) => (
                            <button
                                key={i}
                                onClick={() => setCurrent(i)}
                                className={`size-2 rounded-full transition-colors ${i === current ? 'bg-white' : 'bg-white/50'}`}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function CollectionTags({ collections }) {
    if (!collections?.length) return null;

    return (
        <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">কালেকশন</h2>
            <div className="flex flex-wrap gap-3">
                {collections.map((col) => (
                    <Link
                        key={col.id}
                        href={`/collection/${encodeURIComponent(col.name)}`}
                        className="flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-black hover:text-black"
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

function ProductSectionBlock({ section }) {
    if (section.block_type === 1) {
        return <ImageSection section={section} />;
    }

    return <ProductGrid section={section} />;
}

function ProductGrid({ section }) {
    if (!section.products?.length) return null;

    const cols = {
        2: 'grid-cols-2',
        3: 'grid-cols-2 sm:grid-cols-3',
        4: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
    }[section.block_per_line] ?? 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4';

    return (
        <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="mb-5 flex items-end justify-between">
                <div>
                    <h2 className="text-xl font-bold text-gray-900">{section.name}</h2>
                    {section.description && (
                        <p className="mt-1 text-sm text-gray-500">{section.description}</p>
                    )}
                </div>
                {section.button_text && (
                    <Link
                        href={`/`}
                        className="text-sm font-medium text-gray-600 underline hover:text-gray-900"
                    >
                        {section.button_text}
                    </Link>
                )}
            </div>
            <div className={`grid gap-4 ${cols}`}>
                {section.products.map((product) => (
                    <ProductCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}

function ImageSection({ section }) {
    if (!section.images?.length) return null;

    return (
        <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {section.name && <h2 className="mb-5 text-xl font-bold text-gray-900">{section.name}</h2>}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {section.images.map((img, i) => (
                    <div key={i} className="overflow-hidden rounded-none bg-gray-100">
                        <img src={img} alt={section.name} className="h-64 w-full object-cover" />
                    </div>
                ))}
            </div>
        </section>
    );
}

export function ProductCard({ product }) {
    const hasDiscount = product.discount_price > 0;

    return (
        <Link href={`/product/${product.slug}`} className="group block">
            <div className="relative overflow-hidden bg-gray-100">
                {product.image ? (
                    <img
                        src={product.image}
                        alt={product.name}
                        className="aspect-[3/4] w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="aspect-[3/4] w-full bg-gray-200" />
                )}
                {hasDiscount && (
                    <span className="absolute left-2 top-2 bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">
                        SALE
                    </span>
                )}
            </div>
            <div className="mt-2 space-y-0.5">
                <p className="text-sm font-medium text-gray-900 line-clamp-2">{product.name}</p>
                <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold text-gray-900">৳{product.price}</span>
                    {hasDiscount && (
                        <span className="text-xs text-gray-400 line-through">৳{product.sale_price}</span>
                    )}
                </div>
            </div>
        </Link>
    );
}
