import { AnimatePresence, motion } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const SLIDE_DURATION = 5500;
const TRANSITION = { duration: 0.85, ease: [0.76, 0, 0.24, 1] };

const slideVariants = {
    enter: (direction) => ({
        x: direction > 0 ? '100%' : '-100%',
        scale: 1.08,
        filter: 'brightness(0.85)',
    }),
    center: {
        x: 0,
        scale: 1,
        filter: 'brightness(1)',
    },
    exit: (direction) => ({
        x: direction > 0 ? '-35%' : '35%',
        scale: 0.94,
        filter: 'brightness(0.7)',
        opacity: 0,
    }),
};

const titleVariants = {
    enter: {
        opacity: 0,
        y: 28,
        clipPath: 'inset(100% 0 0 0)',
    },
    center: {
        opacity: 1,
        y: 0,
        clipPath: 'inset(0% 0 0 0)',
        transition: { delay: 0.35, duration: 0.55, ease: [0.22, 1, 0.36, 1] },
    },
    exit: {
        opacity: 0,
        y: -16,
        clipPath: 'inset(0 0 100% 0)',
        transition: { duration: 0.25, ease: 'easeIn' },
    },
};

function formatSlideIndex(index, total) {
    return `${String(index + 1).padStart(2, '0')} / ${String(total).padStart(2, '0')}`;
}

export function HeroSlider({ sliders }) {
    const [current, setCurrent] = useState(0);
    const [direction, setDirection] = useState(1);
    const [isPaused, setIsPaused] = useState(false);
    const [progress, setProgress] = useState(0);
    const rafRef = useRef(null);

    const goTo = useCallback(
        (index, dir) => {
            if (index === current) {
                return;
            }
            setDirection(dir ?? (index > current ? 1 : -1));
            setCurrent(index);
            setProgress(0);
        },
        [current],
    );

    const next = useCallback(() => {
        if (!sliders?.length) {
            return;
        }
        goTo(current === sliders.length - 1 ? 0 : current + 1, 1);
    }, [current, sliders, goTo]);

    const prev = useCallback(() => {
        if (!sliders?.length) {
            return;
        }
        goTo(current === 0 ? sliders.length - 1 : current - 1, -1);
    }, [current, sliders, goTo]);

    useEffect(() => {
        if (!sliders?.length || sliders.length <= 1 || isPaused) {
            return;
        }

        const startedAt = performance.now();

        const tick = (now) => {
            const elapsed = now - startedAt;
            setProgress(Math.min(elapsed / SLIDE_DURATION, 1));

            if (elapsed < SLIDE_DURATION) {
                rafRef.current = requestAnimationFrame(tick);
            }
        };

        rafRef.current = requestAnimationFrame(tick);
        const timer = setTimeout(next, SLIDE_DURATION);

        return () => {
            clearTimeout(timer);
            if (rafRef.current) {
                cancelAnimationFrame(rafRef.current);
            }
        };
    }, [current, sliders, isPaused, next]);

    if (!sliders?.length) {
        return null;
    }

    const activeSlide = sliders[current];
    const hasMultiple = sliders.length > 1;

    return (
        <section
            className="relative overflow-hidden bg-store-primary"
            aria-roledescription="carousel"
            aria-label="Featured promotions"
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
            onFocus={() => setIsPaused(true)}
            onBlur={() => setIsPaused(false)}
        >
            <div className="relative h-[42vw] min-h-[240px] max-h-[520px] w-full sm:min-h-[300px] lg:min-h-[380px]">
                <AnimatePresence initial={false} custom={direction} mode="popLayout">
                    <motion.div
                        key={current}
                        custom={direction}
                        variants={slideVariants}
                        initial="enter"
                        animate="center"
                        exit="exit"
                        transition={TRANSITION}
                        className="absolute inset-0 will-change-transform"
                        aria-hidden={false}
                    >
                        <motion.img
                            src={activeSlide.image}
                            alt={activeSlide.name || 'Promotion banner'}
                            className="absolute inset-0 size-full object-cover object-center"
                            initial={{ scale: 1 }}
                            animate={{ scale: 1.06 }}
                            transition={{
                                duration: SLIDE_DURATION / 1000,
                                ease: 'linear',
                            }}
                            draggable={false}
                        />
                    </motion.div>
                </AnimatePresence>

                <div
                    className="pointer-events-none absolute inset-0 bg-linear-to-r from-store-primary/80 via-store-primary/35 to-transparent"
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute inset-0 bg-linear-to-t from-store-primary/75 via-store-primary/15 to-transparent"
                    aria-hidden
                />
                <div className="pointer-events-none absolute inset-0 auth-grid-overlay opacity-15" aria-hidden />

                <div className="absolute inset-x-0 bottom-0 store-container pb-5 pt-16 sm:pb-6 lg:pb-8">
                    <div className="flex items-end justify-between gap-4">
                        <AnimatePresence mode="wait" custom={direction}>
                            {activeSlide.name && (
                                <motion.div
                                    key={`title-${current}`}
                                    variants={titleVariants}
                                    initial="enter"
                                    animate="center"
                                    exit="exit"
                                    className="min-w-0 max-w-[min(100%,36rem)]"
                                >
                                    {hasMultiple && (
                                        <p className="mb-2 flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.22em] text-white/70 sm:text-xs">
                                            <span className="inline-block h-px w-6 bg-store-accent" aria-hidden />
                                            Featured
                                            <span className="tabular-nums text-white/90">
                                                {formatSlideIndex(current, sliders.length)}
                                            </span>
                                        </p>
                                    )}
                                    <h2 className="auth-display text-xl font-bold leading-tight text-white sm:text-3xl lg:text-4xl">
                                        {activeSlide.name}
                                    </h2>
                                </motion.div>
                            )}
                        </AnimatePresence>

                        {hasMultiple && (
                            <div className="hidden shrink-0 flex-col items-end gap-2 sm:flex">
                                <span className="text-[10px] font-semibold uppercase tracking-[0.18em] text-white/50">
                                    {formatSlideIndex(current, sliders.length)}
                                </span>
                                <div className="flex gap-1">
                                    {sliders.map((slide, i) => (
                                        <button
                                            key={slide.id ?? i}
                                            type="button"
                                            onClick={() => goTo(i)}
                                            aria-label={`Go to slide ${i + 1}${slide.name ? `: ${slide.name}` : ''}`}
                                            aria-current={i === current ? 'true' : undefined}
                                            className={`h-1 overflow-hidden rounded-full transition-all duration-300 ${
                                                i === current ? 'w-10 bg-white/25' : 'w-2 bg-white/35 hover:bg-white/55'
                                            }`}
                                        >
                                            {i === current && (
                                                <span
                                                    className="block h-full origin-left rounded-full bg-store-accent transition-transform duration-75 ease-linear"
                                                    style={{ transform: `scaleX(${progress})` }}
                                                />
                                            )}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {hasMultiple && (
                    <>
                        <button
                            type="button"
                            onClick={prev}
                            className="absolute left-3 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-300 text-black transition-colors duration-300 hover:bg-white sm:left-5 sm:size-10"
                            aria-label="Previous slide"
                        >
                            <ChevronLeft className="size-4 stroke-[1.5]" />
                        </button>
                        <button
                            type="button"
                            onClick={next}
                            className="absolute right-3 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-300 text-black transition-colors duration-300 hover:bg-white sm:right-5 sm:size-10"
                            aria-label="Next slide"
                        >
                            <ChevronRight className="size-4 stroke-[1.5]" />
                        </button>

                        <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5 sm:hidden">
                            {sliders.map((slide, i) => (
                                <button
                                    key={slide.id ?? i}
                                    type="button"
                                    onClick={() => goTo(i)}
                                    aria-label={`Go to slide ${i + 1}`}
                                    aria-current={i === current ? 'true' : undefined}
                                    className={`h-1.5 rounded-full transition-all duration-300 ${
                                        i === current ? 'w-6 bg-store-accent' : 'w-1.5 bg-white/45'
                                    }`}
                                />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </section>
    );
}
