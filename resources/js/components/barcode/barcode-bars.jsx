import { useLayoutEffect, useRef } from 'react';

import { renderBarcodeSvg } from '@/lib/barcode-label';

export function BarcodeBars({ code, barHeight, fill = false }) {
    const svgRef = useRef(null);
    const wrapRef = useRef(null);

    useLayoutEffect(() => {
        const measure = () => {
            if (svgRef.current && wrapRef.current) {
                // #region agent log
                fetch('/debug/client-log', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sessionId: '601285',
                        location: 'barcode-bars.jsx:measure',
                        message: 'preview measure start',
                        data: {
                            rawCodeLen: String(code ?? '').length,
                            barHeight,
                            fill,
                            wrapW: wrapRef.current.clientWidth,
                            wrapH: wrapRef.current.clientHeight,
                            renderer: 'jsbarcode-svg',
                        },
                        timestamp: Date.now(),
                        hypothesisId: 'D',
                        runId: 'post-fix',
                    }),
                }).catch(() => {});
                // #endregion

                renderBarcodeSvg(
                    svgRef.current,
                    wrapRef.current,
                    code,
                    barHeight,
                    { fill },
                );
            }
        };

        measure();

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
            <svg ref={svgRef} style={{ display: 'block', maxWidth: '100%', height: 'auto' }} />
        </div>
    );
}
