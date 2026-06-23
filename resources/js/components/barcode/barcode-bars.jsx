import { useLayoutEffect, useRef } from 'react';

import { fitBarcodeToContainer } from '@/lib/barcode-label';

export function BarcodeBars({ code, barHeight, fontWeight = 400, fill = false }) {
    const textRef = useRef(null);
    const wrapRef = useRef(null);

    useLayoutEffect(() => {
        const measure = () => {
            if (textRef.current && wrapRef.current) {
                fitBarcodeToContainer(
                    textRef.current,
                    wrapRef.current,
                    barHeight,
                    { fill },
                );
            }
        };

        if (document.fonts?.ready) {
            document.fonts.ready.then(measure);
        } else {
            measure();
        }

        const wrap = wrapRef.current;

        if (!wrap) {
            return undefined;
        }

        const observer = new ResizeObserver(() => {
            measure();
        });

        observer.observe(wrap);

        return () => {
            observer.disconnect();
        };
    }, [code, barHeight, fill]);

    return (
        <div
            ref={wrapRef}
            style={{
                width: '100%',
                overflow: 'hidden',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flex: fill ? '1 1 0' : '0 0 auto',
                minHeight: fill ? 0 : undefined,
                padding: '0 6px',
                boxSizing: 'border-box',
            }}
        >
            <div
                ref={textRef}
                style={{
                    fontFamily: "'Libre Barcode 128', monospace",
                    fontSize: `${barHeight}px`,
                    fontWeight,
                    lineHeight: 1,
                    whiteSpace: 'nowrap',
                    display: 'inline-block',
                }}
            >
                {code}
            </div>
        </div>
    );
}
