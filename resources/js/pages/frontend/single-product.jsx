import { Head, Link } from '@inertiajs/react';
import { ChevronRight, ShoppingCart, Truck } from 'lucide-react';
import { useState } from 'react';
import { ProductReviews } from '@/components/frontend/product-reviews';
import { StarRating } from '@/components/frontend/star-rating';
import { StoreButton } from '@/components/frontend/store-button';
import { useAddToCart } from '@/hooks/use-add-to-cart';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function SingleProduct({ product, reviews = [], reviewSummary = { average: 0, count: 0 } }) {
    return (
        <FrontendLayout>
            <Head title={product.name} />
            <SingleProductContent product={product} reviews={reviews} reviewSummary={reviewSummary} />
        </FrontendLayout>
    );
}

function SingleProductContent({ product, reviews, reviewSummary }) {
    const [selectedPhoto, setSelectedPhoto] = useState(0);
    const [selectedVariation, setSelectedVariation] = useState(null);
    const [tailorService, setTailorService] = useState('standard');
    const [adding, setAdding] = useState(false);
    const [activeTab, setActiveTab] = useState('description');
    const { addToCart } = useAddToCart();

    const photos = [product.image, ...(product.photos || [])].filter(Boolean);

    let effectivePrice = selectedVariation
        ? selectedVariation.price
        : product.discount_price > 0
          ? product.discount_price
          : product.sale_price;

    if (tailorService === 'tailor' && product.tailor_option === 'yes') {
        effectivePrice += product.tailor_price || 0;
    }

    const hasDiscount = product.discount_price > 0 && !selectedVariation;
    const discountPercent = hasDiscount
        ? Math.round(((product.sale_price - product.discount_price) / product.sale_price) * 100)
        : 0;

    const handleAddToCart = async () => {
        if (product.variations?.length && !selectedVariation) {
            return;
        }
        setAdding(true);
        try {
            await addToCart({
                product_id: product.id,
                quantity: 1,
                variation_id: selectedVariation?.id ?? null,
                tailor_service: product.tailor_option === 'yes' ? tailorService : null,
                tailor_price: product.tailor_option === 'yes' ? product.tailor_price : null,
            });
        } finally {
            setAdding(false);
        }
    };

    const tabs = [
        { id: 'description', label: 'Description' },
        { id: 'reviews', label: `Reviews (${reviewSummary.count})` },
    ];

    return (
        <>
            <div className="store-container py-6 pb-28 lg:pb-10">
                <nav className="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-store-muted">
                    <Link href="/" className="transition-colors hover:text-store-accent">
                        Home
                    </Link>
                    {product.category && (
                        <>
                            <ChevronRight className="size-3.5" aria-hidden />
                            {product.category_slug ? (
                                <Link
                                    href={`/category/${product.category_slug}/products`}
                                    className="transition-colors hover:text-store-accent"
                                >
                                    {product.category}
                                </Link>
                            ) : (
                                <span>{product.category}</span>
                            )}
                        </>
                    )}
                    <ChevronRight className="size-3.5" aria-hidden />
                    <span className="truncate font-medium text-store-primary">{product.name}</span>
                </nav>

                <div className="grid gap-8 lg:grid-cols-2 lg:gap-12">
                    <div className="space-y-3">
                        <div className="relative overflow-hidden rounded-2xl bg-white ring-1 ring-gray-100">
                            <img
                                src={photos[selectedPhoto]}
                                alt={product.name}
                                className="aspect-3/4 w-full object-cover"
                            />
                            {hasDiscount && (
                                <span className="absolute left-3 top-3 rounded-full bg-store-accent px-2.5 py-1 text-xs font-bold text-white">
                                    -{discountPercent}%
                                </span>
                            )}
                        </div>
                        {photos.length > 1 && (
                            <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
                                {photos.map((photo, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => setSelectedPhoto(i)}
                                        className={`shrink-0 overflow-hidden rounded-xl border-2 transition-all ${
                                            i === selectedPhoto
                                                ? 'border-store-accent ring-2 ring-store-accent/20'
                                                : 'border-transparent ring-1 ring-gray-100 hover:ring-store-accent/30'
                                        }`}
                                    >
                                        <img src={photo} alt="" className="size-16 object-cover sm:size-20" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="space-y-5">
                        <div>
                            {product.brand && (
                                <p className="text-xs font-semibold uppercase tracking-wider text-store-accent">
                                    {product.brand}
                                </p>
                            )}
                            {product.category && !product.brand && (
                                <p className="text-xs font-semibold uppercase tracking-wider text-store-muted">
                                    {product.category}
                                </p>
                            )}
                            <h1 className="mt-1 text-2xl font-bold text-store-primary sm:text-3xl">{product.name}</h1>

                            {reviewSummary.count > 0 && (
                                <div className="mt-2">
                                    <StarRating rating={reviewSummary.average} size="md" showValue />
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTab('reviews');
                                            document.getElementById('product-tabs')?.scrollIntoView({ behavior: 'smooth' });
                                        }}
                                        className="mt-1 text-xs text-store-muted underline-offset-2 hover:text-store-accent hover:underline"
                                    >
                                        {reviewSummary.count} {reviewSummary.count === 1 ? 'review' : 'reviews'}
                                    </button>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap items-baseline gap-3">
                            <span className="text-3xl font-bold text-store-accent">৳{effectivePrice.toLocaleString('en-BD')}</span>
                            {hasDiscount && (
                                <span className="text-lg text-gray-400 line-through">
                                    ৳{product.sale_price.toLocaleString('en-BD')}
                                </span>
                            )}
                        </div>

                        {product.variations?.length > 0 && (
                            <div>
                                <p className="mb-2.5 text-sm font-semibold text-store-primary">Select Option</p>
                                <div className="flex flex-wrap gap-2">
                                    {product.variations.map((v) => (
                                        <button
                                            key={v.id}
                                            type="button"
                                            onClick={() => setSelectedVariation(selectedVariation?.id === v.id ? null : v)}
                                            disabled={v.stock <= 0}
                                            className={`rounded-lg border px-4 py-2 text-sm font-medium transition-all ${
                                                selectedVariation?.id === v.id
                                                    ? 'border-store-accent bg-store-accent text-white shadow-sm'
                                                    : 'border-gray-200 bg-white hover:border-store-accent'
                                            } ${v.stock <= 0 ? 'cursor-not-allowed opacity-40' : ''}`}
                                        >
                                            {Object.values(v.variation_data || {}).join(' / ')}
                                            {v.stock <= 0 && ' (Out of stock)'}
                                        </button>
                                    ))}
                                </div>
                                {product.variations?.length > 0 && !selectedVariation && (
                                    <p className="mt-2 text-xs text-store-muted">Please select an option before adding to cart</p>
                                )}
                            </div>
                        )}

                        {product.tailor_option === 'yes' && (
                            <div>
                                <p className="mb-2.5 text-sm font-semibold text-store-primary">Service</p>
                                <div className="flex gap-2">
                                    {['standard', 'tailor'].map((opt) => (
                                        <button
                                            key={opt}
                                            type="button"
                                            onClick={() => setTailorService(opt)}
                                            className={`rounded-lg border px-4 py-2 text-sm font-medium capitalize transition-all ${
                                                tailorService === opt
                                                    ? 'border-store-accent bg-store-accent text-white'
                                                    : 'border-gray-200 bg-white hover:border-store-accent'
                                            }`}
                                        >
                                            {opt === 'standard' ? 'Standard' : `Tailor (+৳${product.tailor_price})`}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <StoreButton
                            className="hidden w-full rounded-xl py-3 lg:flex"
                            onClick={handleAddToCart}
                            disabled={adding || (product.variations?.length > 0 && !selectedVariation)}
                        >
                            <ShoppingCart className="size-4" />
                            {adding ? 'Adding...' : 'Add to Cart'}
                        </StoreButton>

                        {product.delivery_info && (
                            <div className="flex gap-3 rounded-xl bg-store-warm/60 p-4 ring-1 ring-amber-100">
                                <Truck className="mt-0.5 size-5 shrink-0 text-store-accent" aria-hidden />
                                <div>
                                    <p className="text-sm font-semibold text-store-primary">Delivery Info</p>
                                    <p className="mt-1 text-sm leading-relaxed text-store-muted">{product.delivery_info}</p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <div id="product-tabs" className="mt-12 border-t border-gray-100 pt-8">
                    <div className="flex gap-1 border-b border-gray-100">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setActiveTab(tab.id)}
                                className={`relative px-4 py-3 text-sm font-semibold transition-colors ${
                                    activeTab === tab.id
                                        ? 'text-store-accent'
                                        : 'text-store-muted hover:text-store-primary'
                                }`}
                            >
                                {tab.label}
                                {activeTab === tab.id && (
                                    <span className="absolute inset-x-0 -bottom-px h-0.5 store-gradient rounded-full" />
                                )}
                            </button>
                        ))}
                    </div>

                    <div className="py-8">
                        {activeTab === 'description' && (
                            <div className="max-w-3xl">
                                {product.description ? (
                                    <div
                                        className="prose prose-sm max-w-none text-store-primary prose-p:leading-relaxed prose-p:text-store-muted prose-headings:text-store-primary prose-a:text-store-accent prose-ul:text-store-muted"
                                        dangerouslySetInnerHTML={{ __html: product.description }}
                                    />
                                ) : (
                                    <p className="text-sm text-store-muted">No description available for this product.</p>
                                )}

                                {product.youtube_link && (
                                    <div className="mt-8">
                                        <h3 className="mb-3 text-sm font-semibold text-store-primary">Product Video</h3>
                                        <div className="aspect-video overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-gray-100">
                                            <iframe
                                                src={product.youtube_link.replace('watch?v=', 'embed/')}
                                                title={`${product.name} video`}
                                                className="size-full"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowFullScreen
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {activeTab === 'reviews' && (
                            <ProductReviews
                                productSlug={product.slug}
                                reviews={reviews}
                                reviewSummary={reviewSummary}
                            />
                        )}
                    </div>
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-gray-100 bg-white/95 p-3 shadow-lg backdrop-blur-sm lg:hidden">
                <div className="flex items-center gap-3">
                    <div className="shrink-0">
                        <p className="text-lg font-bold text-store-accent">৳{effectivePrice.toLocaleString('en-BD')}</p>
                        {hasDiscount && (
                            <p className="text-xs text-gray-400 line-through">
                                ৳{product.sale_price.toLocaleString('en-BD')}
                            </p>
                        )}
                    </div>
                    <StoreButton
                        className="flex-1 rounded-xl py-3"
                        onClick={handleAddToCart}
                        disabled={adding || (product.variations?.length > 0 && !selectedVariation)}
                    >
                        {adding ? 'Adding...' : 'Add to Cart'}
                    </StoreButton>
                </div>
            </div>
        </>
    );
}
