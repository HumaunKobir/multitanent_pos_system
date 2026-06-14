import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { route } from '@/lib/route';

const PRINT_DPI = 96;
const PREVIEW_MAX_W = 500;
const PREVIEW_MAX_H = 220;

function getEffectivePrice(row) {
    if (row?.variation?.price != null) {
        return parseFloat(row.variation.price);
    }

    if (!row?.product) {
        return null;
    }

    const disc = parseFloat(row.product.discount_price ?? 0);
    const sale = parseFloat(row.product.sale_price ?? 0);

    return disc > 0 ? disc : sale;
}

function BarcodeBars({ code, barHeight, fontWeight }) {
    const textRef = useRef(null);
    const wrapRef = useRef(null);
    const [scaleX, setScaleX] = useState(1);

    useLayoutEffect(() => {
        const measure = () => {
            if (textRef.current && wrapRef.current) {
                const wrapW = wrapRef.current.offsetWidth;
                const textW = textRef.current.scrollWidth;
                if (textW > 0 && wrapW > 0) setScaleX(wrapW / textW);
            }
        };
        document.fonts?.ready ? document.fonts.ready.then(measure) : measure();
    }, [code, barHeight]);

    return (
        <div ref={wrapRef} style={{ width: '100%', height: `${barHeight}px`, overflow: 'hidden' }}>
            <div
                ref={textRef}
                style={{
                    fontFamily: "'Libre Barcode 128', monospace",
                    fontSize: `${barHeight}px`,
                    fontWeight,
                    lineHeight: 1,
                    whiteSpace: 'nowrap',
                    display: 'inline-block',
                    transformOrigin: '0 0',
                    transform: `scaleX(${scaleX})`,
                }}
            >
                {code}
            </div>
        </div>
    );
}

function LabelPreview({ row, settings }) {
    const pxWidth = settings.width * PRINT_DPI;
    const pxHeight = settings.height * PRINT_DPI;
    // scale to fit preview box — allow upscaling so small labels fill the area
    const scale = Math.min(PREVIEW_MAX_W / pxWidth, PREVIEW_MAX_H / pxHeight);
    const displayW = Math.round(pxWidth * scale);
    const displayH = Math.round(pxHeight * scale);

    const fw = settings.fontWeight === 'bold' ? 700 : 400;
    const barHeight = Math.max(settings.fontSize * 3, 28);
    const price = getEffectivePrice(row);

    return (
        <div style={{ width: `${displayW}px`, height: `${displayH}px`, overflow: 'hidden', flexShrink: 0 }}>
            <div
                style={{
                    width: `${pxWidth}px`,
                    height: `${pxHeight}px`,
                    border: '1px solid #d1d5db',
                    background: '#fff',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: '4px 6px',
                    boxSizing: 'border-box',
                    overflow: 'hidden',
                    transform: `scale(${scale})`,
                    transformOrigin: '0 0',
                }}
            >
                <div style={{ width: '100%' }}>
                    <div
                        style={{
                            fontSize: `${settings.fontSize}px`,
                            fontWeight: fw,
                            fontFamily: 'sans-serif',
                            textAlign: 'center',
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            lineHeight: 1.2,
                            marginBottom: '1px',
                        }}
                    >
                        {row?.name ?? 'Product Name'}
                    </div>
                    <BarcodeBars code={row?.code ?? '123456789'} barHeight={barHeight} fontWeight={fw} />
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            fontSize: `${settings.fontSize}px`,
                            fontWeight: fw,
                            fontFamily: 'monospace',
                            marginTop: '1px',
                            lineHeight: 1,
                        }}
                    >
                        <span>{price != null ? Number(price).toFixed(2) : '0.00'}</span>
                        <span>{row?.code ?? ''}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function buildPrintHtml(rows, settings) {
    const { width, height, fontSize, fontWeight, copies } = settings;
    const fw = fontWeight === 'bold' ? 700 : 400;
    const barHeight = Math.max(fontSize * 3, 28);

    const labels = rows
        .flatMap((row) => Array.from({ length: copies }, () => row))
        .map((row) => {
            const price = getEffectivePrice(row) ?? 0;
            return `
      <div class="label">
        <div class="label-inner">
          <div class="name">${row.name ?? ''}</div>
          <div class="bars-wrap"><div class="bars">${row.code}</div></div>
          <div class="footer">
            <span>${Number(price ?? 0).toFixed(2)}</span>
            <span>${row.code}</span>
          </div>
        </div>
      </div>`;
        })
        .join('');

    return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Barcode Labels</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: white; }
    .page { display: flex; flex-wrap: wrap; }
    .label { width: ${width}in; height: ${height}in; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; padding: 4px 6px; overflow: hidden; page-break-inside: avoid; }
    .label-inner { width: 100%; }
    .name { font-size: ${fontSize}px; font-weight: ${fw}; font-family: sans-serif; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; margin-bottom: 1px; }
    .bars-wrap { overflow: hidden; }
    .bars { font-family: 'Libre Barcode 128', monospace; font-weight: ${fw}; font-size: ${barHeight}px; line-height: 1; white-space: nowrap; display: inline-block; transform-origin: 0 0; }
    .footer { display: flex; justify-content: space-between; font-size: ${fontSize}px; font-weight: ${fw}; font-family: monospace; margin-top: 1px; line-height: 1; }
    @media print { @page { margin: 0; } body { margin: 0; } }
  </style>
</head>
<body>
  <div class="page">${labels}</div>
  <script>
    document.fonts.ready.then(function() {
      document.querySelectorAll('.bars').forEach(function(el) {
        var w = el.parentElement.offsetWidth;
        var tw = el.scrollWidth;
        if (tw > 0 && w > 0) el.style.transform = 'scaleX(' + (w / tw) + ')';
      });
      window.print();
      window.close();
    });
  <\/script>
</body>
</html>`;
}

export default function BarcodePrint({ barcodes }) {
    const [settings, setSettings] = useState({
        width: 1.5,
        height: 1,
        fontSize: 8,
        fontWeight: 'normal',
        copies: 1,
    });

    const set = (key, value) => setSettings((prev) => ({ ...prev, [key]: value }));

    const previewRow = barcodes[0] ?? null;

    const handlePrint = () => {
        const win = window.open('', '_blank', 'width=700,height=500');
        win.document.write(buildPrintHtml(barcodes, settings));
        win.document.close();
    };

    return (
        <>
            <Head title="Print Barcodes" />

            <div className="px-2 py-1">
                {/* Header */}
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('barcode.index')}
                            className="flex size-8 items-center justify-center rounded-md bg-white/15 transition-colors hover:bg-white/25"
                        >
                            <ArrowLeft className="size-4 text-white" />
                        </Link>
                        <div>
                            <h1 className="text-base font-semibold text-white">Print Barcodes</h1>
                            <p className="text-xs text-white/60">
                                {barcodes.length} label{barcodes.length !== 1 ? 's' : ''} selected
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

                {/* Print Settings — full width */}
                <div className="mb-3 rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-gray-800">Print Settings</h2>
                    </div>
                    <div className="grid grid-cols-3 divide-x">
                        {/* Dimensions */}
                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-600 uppercase">
                                Dimensions
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label className="mb-1 block text-xs text-gray-500">Width (in)</Label>
                                    <Input
                                        type="number"
                                        min={0.5}
                                        max={10}
                                        step={0.1}
                                        value={settings.width}
                                        onChange={(e) => set('width', parseFloat(e.target.value) || 1)}
                                        className="h-7 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="mb-1 block text-xs text-gray-500">Height (in)</Label>
                                    <Input
                                        type="number"
                                        min={0.3}
                                        max={10}
                                        step={0.1}
                                        value={settings.height}
                                        onChange={(e) => set('height', parseFloat(e.target.value) || 0.5)}
                                        className="h-7 text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Content */}
                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-600 uppercase">
                                Content
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label className="mb-1 block text-xs text-gray-500">Font Size (px)</Label>
                                    <Input
                                        type="number"
                                        min={6}
                                        max={24}
                                        step={1}
                                        value={settings.fontSize}
                                        onChange={(e) => set('fontSize', parseInt(e.target.value) || 8)}
                                        className="h-7 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="mb-1 block text-xs text-gray-500">Font Weight</Label>
                                    <Select
                                        value={settings.fontWeight}
                                        onValueChange={(v) => set('fontWeight', v)}
                                    >
                                        <SelectTrigger className="h-7 w-28 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="normal">Normal</SelectItem>
                                            <SelectItem value="bold">Bold</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Quantity */}
                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-600 uppercase">
                                Quantity
                            </p>
                            <div>
                                <Label className="mb-1 block text-xs text-gray-500">Copies per Label</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    max={100}
                                    step={1}
                                    value={settings.copies}
                                    onChange={(e) => set('copies', parseInt(e.target.value) || 1)}
                                    className="h-7 text-xs"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Preview — full width, fixed height container */}
                <div className="mb-3 rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-gray-800">Preview</h2>
                    </div>
                    <div
                        className="flex items-center justify-center bg-gray-100"
                        style={{ height: '280px' }}
                    >
                        {previewRow ? (
                            <LabelPreview row={previewRow} settings={settings} />
                        ) : (
                            <p className="text-xs text-muted-foreground">No barcode to preview</p>
                        )}
                    </div>
                    <div className="border-t px-4 py-2 text-center">
                        <p className="text-xs text-gray-400">
                            {settings.width}" × {settings.height}" · {settings.copies}× per label
                        </p>
                    </div>
                </div>

                {/* Barcode list */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-gray-800">
                            Barcodes
                            <span className="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                {barcodes.length}
                            </span>
                        </h2>
                    </div>
                    <div className="divide-y">
                        {barcodes.map((row) => (
                            <div key={row.id} className="flex items-center gap-3 px-4 py-2">
                                <div style={{ width: '120px', flexShrink: 0 }}>
                                    <BarcodeBars code={row.code} barHeight={28} fontWeight={400} />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-gray-800">{row.name}</p>
                                    {row.variation && (
                                        <p className="text-xs text-muted-foreground">
                                            {row.variation.variation_data?.label}
                                        </p>
                                    )}
                                </div>
                                <span className="font-mono text-xs font-semibold tracking-widest text-gray-500">
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
