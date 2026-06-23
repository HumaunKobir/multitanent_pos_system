import { useLayoutEffect, useRef } from 'react';

import { fitBarcodeToContainer } from '@/lib/barcode-label';

export function BarcodeBars({ code, barHeight, fontWeight = 400 }) {
    const textRef = useRef(null);
    const wrapRef = useRef(null);

    useLayoutEffect(() => {
        const measure = () => {
            if (textRef.current && wrapRef.current) {
                const fittedHeight = fitBarcodeToContainer(
                    textRef.current,
                    wrapRef.current,
                    barHeight,
                );

                wrapRef.current.style.height = `${Math.max(fittedHeight, 12)}px`;
            }
        };

        if (document.fonts?.ready) {
            document.fonts.ready.then(measure);
        } else {
            measure();
        }
    }, [code, barHeight]);

    return (
        <div
            ref={wrapRef}
            style={{
                width: '100%',
                overflow: 'hidden',
                display: 'flex',
                alignItems: 'flex-end',
                justifyContent: 'center',
                flexShrink: 0,
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
                    maxWidth: '100%',
                }}
            >
                {code}
            </div>
        </div>
    );
}
