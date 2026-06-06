import { useCallback, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

const ZOOM_SCALE = 2.5;
const LENS_RATIO = 100 / ZOOM_SCALE;

export function ProductImageZoom({ src, alt, className }) {
    const containerRef = useRef(null);
    const [active, setActive] = useState(false);
    const [position, setPosition] = useState({ x: 50, y: 50 });

    useEffect(() => {
        setActive(false);
        setPosition({ x: 50, y: 50 });
    }, [src]);

    const updatePosition = useCallback((clientX, clientY) => {
        const rect = containerRef.current?.getBoundingClientRect();
        if (!rect) {
            return;
        }

        const x = Math.min(100, Math.max(0, ((clientX - rect.left) / rect.width) * 100));
        const y = Math.min(100, Math.max(0, ((clientY - rect.top) / rect.height) * 100));
        setPosition({ x, y });
    }, []);

    const lensLeft = Math.min(Math.max(position.x - LENS_RATIO / 2, 0), 100 - LENS_RATIO);
    const lensTop = Math.min(Math.max(position.y - LENS_RATIO / 2, 0), 100 - LENS_RATIO);

    return (
        <div className="lg:grid lg:grid-cols-[42%_58%]">
            <div
                ref={containerRef}
                className={cn(
                    'relative overflow-hidden bg-white',
                    active ? 'cursor-crosshair' : 'cursor-zoom-in',
                    className,
                )}
                onMouseEnter={() => setActive(true)}
                onMouseLeave={() => setActive(false)}
                onMouseMove={(event) => updatePosition(event.clientX, event.clientY)}
            >
                <img src={src} alt={alt} draggable={false} className="size-full object-contain p-2" />

                {active && (
                    <div
                        className="pointer-events-none absolute border border-store-accent/60 bg-store-accent/10 shadow-[inset_0_0_0_1px_rgba(255,255,255,0.5)]"
                        style={{
                            left: `${lensLeft}%`,
                            top: `${lensTop}%`,
                            width: `${LENS_RATIO}%`,
                            height: `${LENS_RATIO}%`,
                        }}
                        aria-hidden
                    />
                )}

                <span
                    className={cn(
                        'pointer-events-none absolute bottom-2 right-2 rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm transition-opacity duration-200 lg:hidden',
                        active ? 'opacity-0' : 'opacity-100',
                    )}
                >
                    Hover to zoom
                </span>
            </div>

            <div
                className={cn(
                    'relative hidden overflow-hidden border-t border-gray-100 bg-white lg:block lg:border-t-0 lg:border-l',
                    className,
                )}
                aria-hidden={!active}
            >
                {active ? (
                    <div
                        className="size-full bg-white bg-no-repeat"
                        style={{
                            backgroundImage: `url(${src})`,
                            backgroundSize: `${ZOOM_SCALE * 100}%`,
                            backgroundPosition: `${position.x}% ${position.y}%`,
                        }}
                    />
                ) : (
                    <div className="flex size-full flex-col items-center justify-center gap-1.5 bg-gray-50/50 p-4 text-center">
                        <span className="text-[10px] font-semibold uppercase tracking-wider text-store-muted">
                            Zoom preview
                        </span>
                        <span className="text-[11px] text-store-muted">Move cursor over the image</span>
                    </div>
                )}
            </div>
        </div>
    );
}
