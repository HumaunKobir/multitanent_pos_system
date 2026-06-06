import { Head } from '@inertiajs/react';
import { FileText, MessageSquare } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ProductDetailHero } from '@/components/frontend/product-detail-hero';
import { ProductImageZoom } from '@/components/frontend/product-image-zoom';
import {
    formatPrice,
    getPricingMeta,
    ProductPricingPanel,
} from '@/components/frontend/product-pricing-panel';
import { ProductReviews } from '@/components/frontend/product-reviews';
import { useAddToCart } from '@/hooks/use-add-to-cart';
import FrontendLayout from '@/layouts/frontend/frontend-layout';
import { cn } from '@/lib/utils';
import { StoreButton } from '@/components/frontend/store-button';

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
    const [buyingNow, setBuyingNow] = useState(false);
    const [activeTab, setActiveTab] = useState('description');
    const { addToCart } = useAddToCart();

    const photos = [product.image, ...(product.photos || [])].filter(Boolean);

    const { effectivePrice, hasDiscount, saved, hasVariations, hasPriceRange, priceMin, priceMax, allVariantsOutOfStock, selectedOutOfStock } =
        getPricingMeta({
            product,
            selectedVariation,
            tailorService,
        });

    const needsVariation = hasVariations && !selectedVariation;

    const buildCartPayload = () => ({
        product_id: product.id,
        quantity: 1,
        variation_id: selectedVariation?.id ?? null,
        tailor_service: product.tailor_option === 'yes' ? tailorService : null,
        tailor_price: product.tailor_option === 'yes' ? product.tailor_price : null,
    });

    useEffect(() => {
        if (!product.variations?.length) {
            return;
        }

        const firstAvailable =
            product.variations.find((variation) => variation.stock > 0) ?? product.variations[0];

        setSelectedVariation(firstAvailable ?? null);
    }, [product.id, product.variations]);

    const handleAddToCart = async () => {
        if (needsVariation) {
            return;
        }
        setAdding(true);
        try {
            await addToCart(buildCartPayload());
        } finally {
            setAdding(false);
        }
    };

    const handleBuyNow = async () => {
        if (needsVariation) {
            return;
        }
        setBuyingNow(true);
        try {
            await addToCart(buildCartPayload(), {
                openDrawerOnAdd: false,
                redirectTo: '/checkout',
            });
        } finally {
            setBuyingNow(false);
        }
    };

    const scrollToReviews = () => {
        setActiveTab('reviews');
        document.getElementById('product-tabs')?.scrollIntoView({ behavior: 'smooth' });
    };

    const tabs = [
        { id: 'description', label: 'Description', icon: FileText },
        { id: 'reviews', label: 'Reviews', icon: MessageSquare },
    ];

    return (
        <>
            <ProductDetailHero
                product={product}
                reviewSummary={reviewSummary}
                onReviewsClick={scrollToReviews}
            />

            <section className="bg-store-surface/60">
                <div className="store-container py-4 pb-24 sm:py-5 lg:pb-5">
                    <div className="grid gap-3 lg:grid-cols-2 lg:items-start lg:gap-4">
                        <div className="self-start overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
                            <ProductImageZoom
                                src={photos[selectedPhoto]}
                                alt={product.name}
                                className="h-52 sm:h-60 lg:h-72"
                            />
                            {photos.length > 1 && (
                                <div className="flex gap-1.5 overflow-x-auto p-2 scrollbar-none">
                                    {photos.map((photo, i) => (
                                        <button
                                            key={i}
                                            type="button"
                                            onClick={() => setSelectedPhoto(i)}
                                            className={cn(
                                                'shrink-0 overflow-hidden rounded-lg ring-2 transition-all',
                                                i === selectedPhoto
                                                    ? 'ring-store-accent'
                                                    : 'ring-transparent hover:ring-store-accent/30',
                                            )}
                                        >
                                            <img src={photo} alt="" className="size-12 object-cover sm:size-14" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="space-y-3">
                            <ProductPricingPanel
                                product={product}
                                selectedVariation={selectedVariation}
                                tailorService={tailorService}
                                onTailorChange={setTailorService}
                                onVariationSelect={setSelectedVariation}
                                needsVariation={needsVariation}
                                adding={adding}
                                buyingNow={buyingNow}
                                onAddToCart={handleAddToCart}
                                onBuyNow={handleBuyNow}
                            />
                        </div>
                    </div>

                    <div id="product-tabs" className="mt-3">
                        <div className="rounded-2xl bg-white p-2 ring-1 ring-gray-200 sm:p-2.5">
                            <div
                                role="tablist"
                                className="inline-flex w-full gap-0.5 rounded-full bg-store-surface p-0.5 ring-1 ring-gray-200 sm:w-auto"
                            >
                                {tabs.map((tab) => {
                                    const Icon = tab.icon;
                                    const selected = activeTab === tab.id;

                                    return (
                                        <button
                                            key={tab.id}
                                            type="button"
                                            role="tab"
                                            aria-selected={selected}
                                            onClick={() => setActiveTab(tab.id)}
                                            className={cn(
                                                'inline-flex flex-1 items-center justify-center gap-1 rounded-full px-2.5 py-1.5 text-[11px] font-semibold transition-all sm:flex-none sm:px-3',
                                                selected
                                                    ? 'bg-store-primary text-white shadow-sm'
                                                    : 'text-store-muted hover:text-store-primary',
                                            )}
                                        >
                                            <Icon className="size-3 shrink-0" aria-hidden />
                                            {tab.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="mt-3 rounded-2xl bg-white p-3 ring-1 ring-gray-200 sm:p-4">
                            {activeTab === 'description' && (
                                <div>
                                    {product.description ? (
                                        <div
                                            className="prose prose-sm max-w-none text-store-primary prose-p:text-xs prose-p:leading-relaxed prose-p:text-store-muted prose-headings:text-sm prose-headings:text-store-primary prose-a:text-store-accent prose-li:text-xs prose-li:text-store-muted"
                                            dangerouslySetInnerHTML={{ __html: product.description }}
                                        />
                                    ) : (
                                        <p className="text-xs text-store-muted">No description available.</p>
                                    )}

                                    {product.youtube_link && (
                                        <div className="mt-4 border-t border-gray-100 pt-4">
                                            <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-store-muted">
                                                Product Video
                                            </p>
                                            <div className="aspect-video overflow-hidden rounded-xl bg-gray-50 ring-1 ring-gray-100">
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
            </section>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-gray-100 bg-white/95 p-2 shadow-lg backdrop-blur-sm lg:hidden">
                <div className="flex items-center gap-2">
                    <div className="shrink-0 pl-1">
                        {hasVariations && !selectedVariation ? (
                            <>
                                <p className="text-sm font-bold leading-none text-store-accent">
                                    {hasPriceRange
                                        ? `৳${formatPrice(priceMin)} – ৳${formatPrice(priceMax)}`
                                        : `From ৳${formatPrice(priceMin ?? effectivePrice)}`}
                                </p>
                                <p className="mt-0.5 text-[10px] text-store-muted">Select option</p>
                            </>
                        ) : (
                            <>
                                <p className="text-base font-bold leading-none text-store-accent">
                                    ৳{formatPrice(effectivePrice)}
                                </p>
                                {hasDiscount && (
                                    <>
                                        <p className="mt-0.5 text-[10px] font-semibold text-emerald-600">
                                            Save ৳{formatPrice(saved)}
                                        </p>
                                        <p className="text-[10px] text-gray-400 line-through">
                                            ৳{formatPrice(product.sale_price)}
                                        </p>
                                    </>
                                )}
                            </>
                        )}
                    </div>
                    <div className="flex min-w-0 flex-1 gap-1.5">
                        <StoreButton
                            variant="outline"
                            className="flex-1 gap-1 px-2 py-2 text-[11px] font-bold"
                            onClick={handleAddToCart}
                            disabled={adding || buyingNow || needsVariation || allVariantsOutOfStock || selectedOutOfStock}
                        >
                            {adding ? 'Adding...' : 'Add to Cart'}
                        </StoreButton>
                        <StoreButton
                            variant="ghost"
                            className="flex-1 gap-1 bg-emerald-600 px-2 py-2 text-[11px] font-bold text-white hover:bg-emerald-700 hover:text-white"
                            onClick={handleBuyNow}
                            disabled={adding || buyingNow || needsVariation || allVariantsOutOfStock || selectedOutOfStock}
                        >
                            {buyingNow ? 'Checkout...' : 'Buy Now'}
                        </StoreButton>
                    </div>
                </div>
            </div>
        </>
    );
}
