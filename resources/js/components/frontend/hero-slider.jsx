import { AnimatePresence, motion } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

export function HeroSlider({ sliders }) {
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        if (!sliders?.length || sliders.length <= 1) {
            return;
        }
        const timer = setInterval(() => {
            setCurrent((c) => (c === sliders.length - 1 ? 0 : c + 1));
        }, 4000);
        return () => clearInterval(timer);
    }, [sliders]);

    if (!sliders?.length) {
        return null;
    }

    const prev = () => setCurrent((c) => (c === 0 ? sliders.length - 1 : c - 1));
    const next = () => setCurrent((c) => (c === sliders.length - 1 ? 0 : c + 1));

    return (
        <div className="relative overflow-hidden bg-store-primary">
            <AnimatePresence mode="wait">
                <motion.div
                    key={current}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.5 }}
                    className="relative"
                >
                    <img
                        src={sliders[current].image}
                        alt={sliders[current].name}
                        className="h-56 w-full object-cover sm:h-80 lg:h-[480px]"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-store-primary/60 via-transparent to-transparent" />
                    {sliders[current].name && (
                        <div className="absolute bottom-8 left-0 right-0 store-container">
                            <h2 className="text-xl font-bold text-white sm:text-3xl">{sliders[current].name}</h2>
                        </div>
                    )}
                </motion.div>
            </AnimatePresence>

            {sliders.length > 1 && (
                <>
                    <button
                        onClick={prev}
                        className="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 shadow-md hover:bg-white"
                        aria-label="Previous slide"
                    >
                        <ChevronLeft className="size-5 text-store-primary" />
                    </button>
                    <button
                        onClick={next}
                        className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 shadow-md hover:bg-white"
                        aria-label="Next slide"
                    >
                        <ChevronRight className="size-5 text-store-primary" />
                    </button>
                    <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                        {sliders.map((_, i) => (
                            <button
                                key={i}
                                onClick={() => setCurrent(i)}
                                className={`h-1.5 rounded-full transition-all ${i === current ? 'w-6 bg-store-accent' : 'w-1.5 bg-white/50'}`}
                                aria-label={`Slide ${i + 1}`}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
