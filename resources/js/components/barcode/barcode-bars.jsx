import { useBarcode } from 'next-barcode';
import { useEffect, useMemo } from 'react';

import {
    finalizeBarcodeSvg,
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
    const resolvedBarHeight = Math.max(10, Math.round(barHeight ?? 28));
    const options = useMemo(
        () => getBarcodeRenderOptions(resolvedBarHeight),
        [resolvedBarHeight],
    );
    const { inputRef } = useBarcode({
        value,
        options,
    });

    // next-barcode draws in useEffect; apply CSS size after that paint.
    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            finalizeBarcodeSvg(inputRef.current, {
                width: '100%',
                height: resolvedBarHeight,
            });
        });

        return () => cancelAnimationFrame(frame);
    }, [inputRef, value, resolvedBarHeight, options, barcodeWidth]);

    return (
        <div
            style={{
                width: barcodeWidth ?? '100%',
                maxWidth: '100%',
                marginLeft: barcodeWidth ? 'auto' : undefined,
                marginRight: barcodeWidth ? 'auto' : undefined,
                overflow: 'hidden',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flex: fill ? '1 1 0' : '0 0 auto',
                minHeight: fill ? 0 : undefined,
                padding: barcodeWidth ? 0 : `0 ${wrapPaddingX}px`,
                boxSizing: 'border-box',
            }}
        >
            <svg ref={inputRef} />
        </div>
    );
}
