import { useBarcode } from 'next-barcode';
import { useEffect, useMemo, useRef } from 'react';

import {
    fitBarcodeSvgToWrapper,
    getBarcodeRenderOptions,
} from '@/lib/barcode-label';

export function BarcodeBars({
    code,
    barHeight,
    barcodeWidth = null,
    fill = false,
    wrapPaddingX = 2,
}) {
    const value = String(code ?? '').trim() || '0';
    const wrapRef = useRef(null);
    const resolvedBarHeight = Math.max(10, Math.round(barHeight ?? 28));
    const options = useMemo(
        () => getBarcodeRenderOptions(resolvedBarHeight),
        [resolvedBarHeight],
    );
    const { inputRef } = useBarcode({
        value,
        options,
    });

    // next-barcode draws in useEffect; size SVG to the measured wrapper after that paint.
    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            fitBarcodeSvgToWrapper(
                inputRef.current,
                wrapRef.current,
                resolvedBarHeight,
            );
        });

        return () => cancelAnimationFrame(frame);
    }, [inputRef, value, resolvedBarHeight, options, barcodeWidth]);

    return (
        <div
            ref={wrapRef}
            style={{
                width: barcodeWidth ?? '100%',
                maxWidth: '100%',
                minWidth: 0,
                marginLeft: barcodeWidth ? 'auto' : undefined,
                marginRight: barcodeWidth ? 'auto' : undefined,
                overflow: 'hidden',
                display: 'block',
                flex: fill ? '1 1 0' : '0 0 auto',
                minHeight: fill ? 0 : undefined,
                padding: barcodeWidth ? 0 : `0 ${wrapPaddingX}px`,
                boxSizing: 'border-box',
                lineHeight: 0,
            }}
        >
            <svg ref={inputRef} />
        </div>
    );
}
