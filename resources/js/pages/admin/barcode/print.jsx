import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { BarcodeBars } from '@/components/barcode/barcode-bars';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    buildPrintHtml,
    formatLabelPrice,
    getEffectivePrice,
    getEffectiveLabelFontSize,
    getLabelBarcodeBarHeight,
    getLabelBarcodeWidthIn,
    getLabelCodeLine,
    getLabelFooterFontSize,
    getLabelHeaderLines,
    getLabelLineHeight,
    getLabelPreviewDisplaySize,
    getMaxFittingLabelFontSize,
    getNameBarcodeGap,
    LABEL_CODE_PRICE_GAP_PX,
    LABEL_PAGE_MARGIN_PX,
    LIST_BARCODE_BAR_HEIGHT,
    MAX_LABEL_FONT_PX,
    MAX_LABEL_HEIGHT_IN,
    MAX_LABEL_WIDTH_IN,
    MIN_LABEL_HEIGHT_IN,
    MIN_LABEL_WIDTH_IN,
    PRINT_DPI,
    resolveLabelSettings,
    scaleLabelPreviewPx,
} from '@/lib/barcode-label';
import { route } from '@/lib/route';

const PREVIEW_MAX_W = 500;
const PREVIEW_MAX_H = 320;
const PRINT_DIMENSIONS_KEY = 'barcode-print-dimensions';
const DEFAULT_WIDTH = 1.8;
const DEFAULT_HEIGHT = 1.4;

function clampDimension(value, min, max, fallback) {
    const parsed = typeof value === 'number' ? value : parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return fallback;
    }

    return Math.min(max, Math.max(min, parsed));
}

function getStoredDimensions() {
    if (typeof window === 'undefined') {
        return { width: DEFAULT_WIDTH, height: DEFAULT_HEIGHT };
    }

    try {
        const raw = localStorage.getItem(PRINT_DIMENSIONS_KEY);

        if (!raw) {
            return { width: DEFAULT_WIDTH, height: DEFAULT_HEIGHT };
        }

        const parsed = JSON.parse(raw);

        return {
            width: clampDimension(
                parsed.width,
                MIN_LABEL_WIDTH_IN,
                MAX_LABEL_WIDTH_IN,
                DEFAULT_WIDTH,
            ),
            height: clampDimension(
                parsed.height,
                MIN_LABEL_HEIGHT_IN,
                MAX_LABEL_HEIGHT_IN,
                DEFAULT_HEIGHT,
            ),
        };
    } catch {
        return { width: DEFAULT_WIDTH, height: DEFAULT_HEIGHT };
    }
}

function storeDimensions(width, height) {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(
        PRINT_DIMENSIONS_KEY,
        JSON.stringify({ width, height }),
    );
}

function LabelPreview({ row, settings }) {
    const { scale, width: displayW, height: displayH } =
        getLabelPreviewDisplaySize(
            settings.width,
            settings.height,
            PREVIEW_MAX_W,
            PREVIEW_MAX_H,
        );
    const effectiveFontSize = getEffectiveLabelFontSize(settings, row);
    const headerLines = getLabelHeaderLines(row);
    const textFontSize = getLabelFooterFontSize(
        effectiveFontSize,
        headerLines.length,
    );

    const fw = settings.fontWeight === 'bold' ? 700 : 400;
    const scaledFontSize = scaleLabelPreviewPx(textFontSize, scale);
    const lineHeight = scaleLabelPreviewPx(
        getLabelLineHeight(textFontSize),
        scale,
    );
    const barHeight = scaleLabelPreviewPx(
        getLabelBarcodeBarHeight(settings, row),
        scale,
    );
    const barcodeWidthIn = getLabelBarcodeWidthIn(settings);
    const barcodeWidthPx = scaleLabelPreviewPx(
        Math.min(barcodeWidthIn, settings.width) * PRINT_DPI,
        scale,
    );
    const nameBarcodeGap = scaleLabelPreviewPx(
        getNameBarcodeGap(textFontSize),
        scale,
    );
    const sectionGap = nameBarcodeGap;
    const pageMargin = scaleLabelPreviewPx(LABEL_PAGE_MARGIN_PX, scale);
    const sideMarginPx = scaleLabelPreviewPx(
        ((settings.width - Math.min(barcodeWidthIn, settings.width)) / 2) *
            PRINT_DPI,
        scale,
    );
    const textSidePadPx = Math.max(0, sideMarginPx - pageMargin);
    const price = getEffectivePrice(row);
    const codeLine = getLabelCodeLine(row);

    const lineStyle = {
        fontSize: `${scaledFontSize}px`,
        fontWeight: fw,
        fontFamily: 'sans-serif',
        textAlign: 'center',
        whiteSpace: 'nowrap',
        lineHeight: `${lineHeight}px`,
        flex: '0 0 auto',
        maxWidth: '100%',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        color: '#111827',
    };

    return (
        <div
            style={{
                width: `${displayW}px`,
                height: `${displayH}px`,
                border: '1px solid #d1d5db',
                background: '#fff',
                display: 'flex',
                flexDirection: 'column',
                padding: `${pageMargin}px`,
                boxSizing: 'border-box',
                overflow: 'hidden',
                flexShrink: 0,
            }}
        >
            <div
                style={{
                    flex: '1 1 auto',
                    width: '100%',
                    height: '100%',
                    maxWidth: '100%',
                    maxHeight: '100%',
                    minWidth: 0,
                    minHeight: 0,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: '100%',
                        height: '100%',
                        minWidth: 0,
                        minHeight: 0,
                        maxWidth: '100%',
                        maxHeight: '100%',
                        display: 'grid',
                        gridTemplateRows: '1fr auto 1fr',
                        justifyItems: 'stretch',
                        alignItems: 'stretch',
                        gap: `${sectionGap}px`,
                        overflow: 'hidden',
                    }}
                >
                    <div
                        style={{
                            alignSelf: 'end',
                            justifySelf: 'stretch',
                            textAlign: 'center',
                            width: '100%',
                            minWidth: 0,
                            maxWidth: '100%',
                            overflow: 'hidden',
                            padding: `0 ${textSidePadPx}px`,
                            lineHeight: 1,
                            margin: 0,
                        }}
                    >
                        {headerLines.map((line) => (
                            <div
                                key={line.text}
                                style={{
                                    ...lineStyle,
                                    fontWeight: line.bold ? 700 : fw,
                                    margin: 0,
                                    padding: 0,
                                }}
                            >
                                {line.text}
                            </div>
                        ))}
                    </div>
                    <div
                        style={{
                            width: `${barcodeWidthPx}px`,
                            maxWidth: '100%',
                            minWidth: 0,
                            minHeight: 0,
                            justifySelf: 'center',
                            alignSelf: 'center',
                            display: 'block',
                            overflow: 'hidden',
                            lineHeight: 0,
                            margin: 0,
                            padding: 0,
                        }}
                    >
                        <BarcodeBars
                            code={row?.code ?? '123456789'}
                            barHeight={barHeight}
                            barcodeWidth="100%"
                        />
                    </div>
                    <div
                        style={{
                            alignSelf: 'start',
                            justifySelf: 'stretch',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            width: '100%',
                            minWidth: 0,
                            maxWidth: '100%',
                            overflow: 'hidden',
                            padding: `0 ${textSidePadPx}px`,
                            lineHeight: 1,
                            margin: 0,
                        }}
                    >
                        <div
                            style={{
                                ...lineStyle,
                                margin: 0,
                                marginBottom: `${scaleLabelPreviewPx(LABEL_CODE_PRICE_GAP_PX, scale)}px`,
                                padding: 0,
                            }}
                        >
                            {codeLine}
                        </div>
                        <div
                            style={{
                                ...lineStyle,
                                fontWeight: 700,
                                margin: 0,
                                padding: 0,
                            }}
                        >
                            {formatLabelPrice(price)}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function BarcodePrint({ barcodes }) {
    const [settings, setSettings] = useState(() => {
        const { width, height } = getStoredDimensions();

        return {
            width,
            height,
            fontSize: 9,
            fontWeight: 'bold',
            copies: 1,
            autoHeight: true,
        };
    });
    const [fontSizeInput, setFontSizeInput] = useState('9');

    useEffect(() => {
        storeDimensions(settings.width, settings.height);
    }, [settings.width, settings.height]);

    const set = (key, value) =>
        setSettings((prev) => ({ ...prev, [key]: value }));

    const commitFontSize = (rawValue) => {
        const parsed = parseInt(rawValue, 10);
        const clamped = Number.isNaN(parsed)
            ? 9
            : Math.min(MAX_LABEL_FONT_PX, Math.max(6, parsed));

        setFontSizeInput(String(clamped));
        applyFontSize(clamped);
    };

    const applyFontSize = (fontSize) => {
        setSettings((prev) => ({ ...prev, fontSize }));
    };

    const previewRow = barcodes[0] ?? null;
    const resolvedSettings = useMemo(
        () => resolveLabelSettings(settings, barcodes),
        [settings, barcodes],
    );
    const effectiveFontSize = previewRow
        ? getEffectiveLabelFontSize(resolvedSettings, previewRow)
        : null;
    const maxFittingFontSize = previewRow
        ? getMaxFittingLabelFontSize(settings, previewRow)
        : null;
    const isFontSizeCapped =
        !settings.autoHeight &&
        effectiveFontSize != null &&
        maxFittingFontSize != null &&
        settings.fontSize > effectiveFontSize;
    const isHeightAutoAdjusted =
        settings.autoHeight &&
        resolvedSettings.height > settings.height;
    const isWidthAutoAdjusted =
        settings.autoHeight &&
        resolvedSettings.width > settings.width;

    const handlePrint = () => {
        const win = window.open('', '_blank', 'width=700,height=500');

        if (!win) {
            return;
        }

        win.document.write(buildPrintHtml(barcodes, settings));
        win.document.close();
    };

    return (
        <>
            <Head title="Print Barcodes" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('barcode.index')}
                            className="flex size-8 items-center justify-center rounded-md bg-white/15 transition-colors hover:bg-white/25"
                        >
                            <ArrowLeft className="size-4 text-white" />
                        </Link>
                        <div>
                            <h1 className="text-base font-semibold text-white">
                                Print Barcodes
                            </h1>
                            <p className="text-xs text-white/60">
                                {barcodes.length} label
                                {barcodes.length !== 1 ? 's' : ''} selected
                            </p>
                        </div>
                    </div>
                    <Button
                        onClick={handlePrint}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    >
                        <Printer className="size-4" />
                        Print Labels
                    </Button>
                </div>

                <div className="pos-panel mb-3 rounded-lg border border-blue-200 bg-white text-blue-950 shadow-sm">
                    <div className="border-b border-blue-200 px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-blue-950">
                            Print Settings
                        </h2>
                    </div>
                    <div className="grid grid-cols-3 divide-x divide-blue-200">
                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-800 uppercase">
                                Dimensions
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label className="mb-1 block text-xs text-slate-600">
                                        Width (in)
                                    </Label>
                                    <Input
                                        type="number"
                                        min={0.5}
                                        max={10}
                                        step={0.1}
                                        value={settings.width}
                                        onChange={(e) =>
                                            set(
                                                'width',
                                                parseFloat(e.target.value) || 1,
                                            )
                                        }
                                        className="h-7 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="mb-1 block text-xs text-slate-600">
                                        Height (in)
                                    </Label>
                                    <Input
                                        type="number"
                                        min={0.3}
                                        max={10}
                                        step={0.1}
                                        value={settings.height}
                                        onChange={(e) =>
                                            set(
                                                'height',
                                                parseFloat(e.target.value) ||
                                                    0.5,
                                            )
                                        }
                                        className="h-7 text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-800 uppercase">
                                Content
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label className="mb-1 block text-xs text-slate-600">
                                        Font Size (px)
                                    </Label>
                                    <Input
                                        type="number"
                                        min={6}
                                        max={MAX_LABEL_FONT_PX}
                                        step={1}
                                        value={fontSizeInput}
                                        onChange={(e) => {
                                            const nextValue = e.target.value;
                                            setFontSizeInput(nextValue);

                                            const parsed = parseInt(
                                                nextValue,
                                                10,
                                            );

                                            if (
                                                !Number.isNaN(parsed) &&
                                                parsed >= 6 &&
                                                parsed <= MAX_LABEL_FONT_PX
                                            ) {
                                                applyFontSize(parsed);
                                            }
                                        }}
                                        onBlur={() =>
                                            commitFontSize(fontSizeInput)
                                        }
                                        className="h-7 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="mb-1 block text-xs text-slate-600">
                                        Font Weight
                                    </Label>
                                    <Select
                                        value={settings.fontWeight}
                                        onValueChange={(v) =>
                                            set('fontWeight', v)
                                        }
                                    >
                                        <SelectTrigger className="h-7 w-28 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="normal">
                                                Normal
                                            </SelectItem>
                                            <SelectItem value="bold">
                                                Bold
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <label className="mt-2 flex items-center gap-2 text-xs text-slate-600">
                                <Checkbox
                                    checked={settings.autoHeight}
                                    onCheckedChange={(checked) => {
                                        setSettings((prev) => ({
                                            ...prev,
                                            autoHeight: checked === true,
                                        }));
                                    }}
                                />
                                Auto-adjust label size for content
                            </label>
                        </div>

                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-800 uppercase">
                                Quantity
                            </p>
                            <div>
                                <Label className="mb-1 block text-xs text-slate-600">
                                    Copies per Label
                                </Label>
                                <Input
                                    type="number"
                                    min={1}
                                    max={100}
                                    step={1}
                                    value={settings.copies}
                                    onChange={(e) =>
                                        set(
                                            'copies',
                                            parseInt(e.target.value) || 1,
                                        )
                                    }
                                    className="h-7 text-xs"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="pos-panel mb-3 rounded-lg border border-blue-200 bg-white text-blue-950 shadow-sm">
                    <div className="border-b border-blue-200 px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-blue-950">
                            Preview
                        </h2>
                    </div>
                    <div
                        className="flex items-center justify-center bg-slate-100"
                        style={{ minHeight: '320px', padding: '16px' }}
                    >
                        {previewRow ? (
                            <LabelPreview
                                row={previewRow}
                                settings={resolvedSettings}
                            />
                        ) : (
                            <p className="text-xs text-slate-500">
                                No barcode to preview
                            </p>
                        )}
                    </div>
                    <div className="border-t border-blue-200 px-4 py-2 text-center">
                        <p className="text-xs text-slate-500">
                            {resolvedSettings.width}" × {resolvedSettings.height}"
                            page · {settings.copies}× per label
                            {previewRow && effectiveFontSize != null && (
                                <>
                                    {' '}
                                    · {effectiveFontSize}px font
                                    {isFontSizeCapped && (
                                        <>
                                            {' '}
                                            (max {maxFittingFontSize}px for this
                                            label size)
                                        </>
                                    )}
                                    {isWidthAutoAdjusted && (
                                        <>
                                            {' '}
                                            · width auto-adjusted to{' '}
                                            {resolvedSettings.width}"
                                        </>
                                    )}
                                    {isHeightAutoAdjusted && (
                                        <>
                                            {' '}
                                            · height auto-adjusted to{' '}
                                            {resolvedSettings.height}"
                                        </>
                                    )}
                                </>
                            )}
                        </p>
                    </div>
                </div>

                <div className="pos-panel rounded-lg border border-blue-200 bg-white text-blue-950 shadow-sm">
                    <div className="border-b border-blue-200 px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-blue-950">
                            Barcodes
                            <span className="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                {barcodes.length}
                            </span>
                        </h2>
                    </div>
                    <div className="divide-y divide-blue-200">
                        {barcodes.map((row) => (
                            <div
                                key={row.id}
                                className="flex items-center gap-3 px-4 py-2"
                            >
                                <div className="rounded bg-white px-1 py-0.5" style={{ width: '120px', flexShrink: 0 }}>
                                    <BarcodeBars
                                        code={row.code}
                                        barHeight={LIST_BARCODE_BAR_HEIGHT}
                                    />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-blue-950">
                                        {row.name}
                                    </p>
                                    {row.variation && (
                                        <>
                                            <p className="text-xs text-slate-500">
                                                {
                                                    row.variation.variation_data
                                                        ?.label
                                                }
                                            </p>
                                            {row.variation.sku && (
                                                <p className="font-mono text-xs text-slate-500">
                                                    SKU: {row.variation.sku}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </div>
                                <span className="font-mono text-xs font-semibold tracking-widest text-slate-600">
                                    {row.code}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
