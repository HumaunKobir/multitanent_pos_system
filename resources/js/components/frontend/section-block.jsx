import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ProductCard } from '@/components/frontend/product-card';

const LAYOUT_SLIDER = 2;
const BLOCK_IMAGE = 1;

export function SectionBlock({ section }) {
    if (section.block_type === BLOCK_IMAGE) {
        return section.layout_type === LAYOUT_SLIDER ? (
            <ImageSliderSection section={section} />
        ) : (
            <ImageBlockSection section={section} />
        );
    }

    if (section.layout_type === LAYOUT_SLIDER) {
        return <ProductSliderSection section={section} />;
    }

    return <ProductGridSection section={section} />;
}

function SectionHeading({ section, showViewAll = true }) {
    return (
        <div className="mb-4 flex items-end justify-between gap-4">
            <div className="min-w-0">
                <div className="flex items-center gap-3">
                    <div className="h-px w-8 shrink-0 bg-store-accent" />
                    <h2 className="truncate text-lg font-bold text-store-primary sm:text-xl">{section.name}</h2>
                    <div className="h-px max-w-16 flex-1 bg-store-accent/30" />
                </div>
                {section.description && (
                    <p className="mt-1 text-sm text-store-muted">{section.description}</p>
                )}
            </div>
            {showViewAll && section.button_text && (
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

function ProductGridSection({ section }) {
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

function ProductSliderSection({ section }) {
    const trackRef = useRef(null);
    const [canScrollLeft, setCanScrollLeft] = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(false);

    const slideWidth = {
        2: 'min-w-[calc(50%-0.375rem)]',
        3: 'min-w-[calc(50%-0.375rem)] sm:min-w-[calc(33.333%-0.55rem)]',
        4: 'min-w-[calc(50%-0.375rem)] sm:min-w-[calc(33.333%-0.55rem)] lg:min-w-[calc(25%-0.75rem)]',
    }[section.block_per_line] ?? 'min-w-[calc(50%-0.375rem)] sm:min-w-[calc(33.333%-0.55rem)] lg:min-w-[calc(25%-0.75rem)]';

    const updateScrollState = useCallback(() => {
        const track = trackRef.current;
        if (!track) {
            return;
        }

        setCanScrollLeft(track.scrollLeft > 4);
        setCanScrollRight(track.scrollLeft + track.clientWidth < track.scrollWidth - 4);
    }, []);

    useEffect(() => {
        updateScrollState();
        const track = trackRef.current;
        if (!track) {
            return;
        }

        track.addEventListener('scroll', updateScrollState, { passive: true });
        window.addEventListener('resize', updateScrollState);

        return () => {
            track.removeEventListener('scroll', updateScrollState);
            window.removeEventListener('resize', updateScrollState);
        };
    }, [section.products, updateScrollState]);

    const scroll = (direction) => {
        const track = trackRef.current;
        if (!track) {
            return;
        }

        track.scrollBy({
            left: direction * track.clientWidth * 0.85,
            behavior: 'smooth',
        });
    };

    if (!section.products?.length) {
        return null;
    }

    return (
        <section className="store-container py-6">
            <SectionHeading section={section} />
            <div className="relative">
                {canScrollLeft && (
                    <button
                        type="button"
                        onClick={() => scroll(-1)}
                        className="absolute -left-2 top-1/2 z-10 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-300 text-black transition-colors duration-300 hover:bg-white sm:flex lg:-left-4"
                        aria-label="Scroll products left"
                    >
                        <ChevronLeft className="size-4 stroke-[1.5]" />
                    </button>
                )}
                {canScrollRight && (
                    <button
                        type="button"
                        onClick={() => scroll(1)}
                        className="absolute -right-2 top-1/2 z-10 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-300 text-black transition-colors duration-300 hover:bg-white sm:flex lg:-right-4"
                        aria-label="Scroll products right"
                    >
                        <ChevronRight className="size-4 stroke-[1.5]" />
                    </button>
                )}

                <div
                    ref={trackRef}
                    className="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1 scrollbar-none sm:gap-4"
                >
                    {section.products.map((product) => (
                        <div key={product.id} className={`${slideWidth} shrink-0 snap-start`}>
                            <ProductCard product={product} />
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function ImageBlockSection({ section }) {
    if (!section.images?.length) {
        return null;
    }

    const cols = section.images.length === 1
        ? 'grid-cols-1'
        : section.images.length === 2
            ? 'grid-cols-1 sm:grid-cols-2'
            : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3';

    return (
        <section className="store-container py-6">
            <SectionHeading section={section} showViewAll={false} />
            <div className={`grid gap-3 sm:gap-4 ${cols}`}>
                {section.images.map((block, index) => (
                    <ImageBanner key={`${block.image}-${index}`} block={block} tall={section.images.length === 1} />
                ))}
            </div>
        </section>
    );
}

function ImageSliderSection({ section }) {
    const [current, setCurrent] = useState(0);
    const [direction, setDirection] = useState(1);

    useEffect(() => {
        if (!section.images?.length || section.images.length <= 1) {
            return;
        }

        const timer = setInterval(() => {
            setDirection(1);
            setCurrent((value) => (value === section.images.length - 1 ? 0 : value + 1));
        }, 5500);

        return () => clearInterval(timer);
    }, [section.images]);

    if (!section.images?.length) {
        return null;
    }

    const goTo = (index, dir) => {
        setDirection(dir ?? (index > current ? 1 : -1));
        setCurrent(index);
    };

    const active = section.images[current];
    const hasMultiple = section.images.length > 1;

    return (
        <section className="store-container py-6">
            <SectionHeading section={section} showViewAll={false} />
            <div className="relative overflow-hidden rounded-2xl bg-store-primary">
                <div className="relative h-[42vw] min-h-[200px] max-h-[420px] w-full sm:min-h-[260px] lg:min-h-[320px]">
                    <AnimatePresence initial={false} custom={direction} mode="popLayout">
                        <motion.div
                            key={current}
                            custom={direction}
                            initial={{ x: direction > 0 ? '100%' : '-100%', opacity: 0.6 }}
                            animate={{ x: 0, opacity: 1 }}
                            exit={{ x: direction > 0 ? '-35%' : '35%', opacity: 0 }}
                            transition={{ duration: 0.7, ease: [0.76, 0, 0.24, 1] }}
                            className="absolute inset-0"
                        >
                            <img
                                src={active.image}
                                alt={active.image_name || section.name}
                                className="size-full object-cover"
                                draggable={false}
                            />
                        </motion.div>
                    </AnimatePresence>

                    <div className="pointer-events-none absolute inset-0 bg-linear-to-t from-store-primary/80 via-store-primary/20 to-transparent" />

                    {(active.image_name || active.description || active.button_text) && (
                        <div className="absolute inset-x-0 bottom-0 p-4 sm:p-6">
                            {active.image_name && (
                                <h3 className="auth-display text-lg font-bold text-white sm:text-2xl">{active.image_name}</h3>
                            )}
                            {active.description && (
                                <p className="mt-1 max-w-xl text-sm text-white/80">{active.description}</p>
                            )}
                            {active.button_text && active.link && (
                                <Link
                                    href={active.link}
                                    className="mt-3 inline-flex rounded-full bg-white px-4 py-2 text-xs font-semibold text-store-primary transition hover:bg-store-accent hover:text-white sm:text-sm"
                                >
                                    {active.button_text}
                                </Link>
                            )}
                        </div>
                    )}

                    {hasMultiple && (
                        <>
                            <button
                                type="button"
                                onClick={() => goTo(current === 0 ? section.images.length - 1 : current - 1, -1)}
                                className="absolute left-3 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-300 text-black transition-colors duration-300 hover:bg-white"
                                aria-label="Previous banner"
                            >
                                <ChevronLeft className="size-4 stroke-[1.5]" />
                            </button>
                            <button
                                type="button"
                                onClick={() => goTo(current === section.images.length - 1 ? 0 : current + 1, 1)}
                                className="absolute right-3 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-300 text-black transition-colors duration-300 hover:bg-white"
                                aria-label="Next banner"
                            >
                                <ChevronRight className="size-4 stroke-[1.5]" />
                            </button>
                            <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                                {section.images.map((block, index) => (
                                    <button
                                        key={`${block.image}-${index}`}
                                        type="button"
                                        onClick={() => goTo(index)}
                                        aria-label={`Go to banner ${index + 1}`}
                                        aria-current={index === current ? 'true' : undefined}
                                        className={`h-1.5 rounded-full transition-all duration-300 ${
                                            index === current ? 'w-6 bg-store-accent' : 'w-1.5 bg-white/45'
                                        }`}
                                    />
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </section>
    );
}

function ImageBanner({ block, tall = false }) {
    const content = (
        <>
            <img
                src={block.image}
                alt={block.image_name || 'Promotion'}
                className={`w-full object-cover ${tall ? 'h-56 sm:h-72 lg:h-80' : 'h-40 sm:h-48'}`}
            />
            {(block.image_name || block.description) && (
                <div className="absolute inset-0 flex flex-col justify-end bg-linear-to-t from-store-primary/75 via-store-primary/15 to-transparent p-4">
                    {block.image_name && (
                        <p className="text-sm font-bold text-white sm:text-base">{block.image_name}</p>
                    )}
                    {block.description && (
                        <p className="mt-0.5 line-clamp-2 text-xs text-white/80">{block.description}</p>
                    )}
                    {block.button_text && (
                        <span className="mt-2 inline-flex w-fit rounded-full bg-white/95 px-3 py-1 text-[11px] font-semibold text-store-primary">
                            {block.button_text}
                        </span>
                    )}
                </div>
            )}
        </>
    );

    if (block.link) {
        return (
            <Link href={block.link} className="group relative block overflow-hidden rounded-2xl">
                {content}
            </Link>
        );
    }

    return <div className="relative overflow-hidden rounded-2xl">{content}</div>;
}
