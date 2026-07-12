import { useLayoutEffect, useRef } from 'react';

import { renderBarcodeSvg } from '@/lib/barcode-label';

export function BarcodeBars({ code, barHeight, fill = false, wrapPaddingX = 2 }) {
    const svgRef = useRef(null);
    const wrapRef = useRef(null);

    useLayoutEffect(() => {
        let frame = 0;

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

        const scheduleMeasure = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(measure);
        };

        scheduleMeasure();

        const wrap = wrapRef.current;

        if (!wrap) {
            return undefined;
        }

        const observer = new ResizeObserver(scheduleMeasure);

        observer.observe(wrap);

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, [code, barHeight, fill, wrapPaddingX]);

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
                padding: `0 ${wrapPaddingX}px`,
                boxSizing: 'border-box',
            }}
        >
            <svg ref={svgRef} />
        </div>
    );
}
