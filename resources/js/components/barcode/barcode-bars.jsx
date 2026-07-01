import { useLayoutEffect, useRef } from 'react';

import { renderBarcodeSvg } from '@/lib/barcode-label';

export function BarcodeBars({ code, barHeight, fill = false }) {
    const svgRef = useRef(null);
    const wrapRef = useRef(null);

    useLayoutEffect(() => {
        const measure = () => {
            if (svgRef.current && wrapRef.current) {
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
