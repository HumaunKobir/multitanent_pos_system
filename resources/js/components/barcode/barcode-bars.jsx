import { useLayoutEffect, useRef } from 'react';

import { fitBarcodeToContainer, formatBarcodeForLibre128 } from '@/lib/barcode-label';

export function BarcodeBars({ code, barHeight, fontWeight = 400, fill = false }) {
    const textRef = useRef(null);
    const wrapRef = useRef(null);
    const encodedCode = formatBarcodeForLibre128(code);

    useLayoutEffect(() => {
        const measure = () => {
            if (textRef.current && wrapRef.current) {
                // #region agent log
                fetch('http://127.0.0.1:7682/ingest/b2b77a02-47d0-43f6-ab11-689e8f32c576', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Debug-Session-Id': '601285',
                    },
                    body: JSON.stringify({
                        sessionId: '601285',
                        location: 'barcode-bars.jsx:measure',
                        message: 'preview measure start',
                        data: {
                            rawCodeLen: String(code ?? '').length,
                            encodedLen: encodedCode.length,
                            barHeight,
                            fill,
                            wrapW: wrapRef.current.clientWidth,
                            wrapH: wrapRef.current.clientHeight,
                        },
                        timestamp: Date.now(),
                        hypothesisId: 'D,F',
                    }),
                }).catch(() => {});
                // #endregion

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
    }, [code, barHeight, fill, encodedCode]);

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
                {encodedCode}
            </div>
        </div>
    );
}
