import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useState } from 'react';

import { BarcodeBars } from '@/components/barcode/barcode-bars';
import { Button } from '@/components/ui/button';
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
    calculateBarcodeBarHeight,
    formatLabelPrice,
    getEffectivePrice,
    getLabelTitle,
    getNameBarcodeGap,
    getBarcodePriceGap,
    PRINT_DPI,
} from '@/lib/barcode-label';
import { route } from '@/lib/route';

const PREVIEW_MAX_W = 500;
const PREVIEW_MAX_H = 220;

function LabelPreview({ row, settings }) {
    const pxWidth = settings.width * PRINT_DPI;
    const pxHeight = settings.height * PRINT_DPI;
    const scale = Math.min(PREVIEW_MAX_W / pxWidth, PREVIEW_MAX_H / pxHeight);
    const displayW = Math.round(pxWidth * scale);
    const displayH = Math.round(pxHeight * scale);

    const fw = settings.fontWeight === 'bold' ? 700 : 400;
    const barHeight = calculateBarcodeBarHeight(settings);
    const nameBarcodeGap = getNameBarcodeGap(settings.fontSize);
    const barcodePriceGap = getBarcodePriceGap();
    const price = getEffectivePrice(row);

    return (
        <div
            style={{
                width: `${displayW}px`,
                height: `${displayH}px`,
                overflow: 'hidden',
                flexShrink: 0,
            }}
        >
            <div
                style={{
                    width: `${pxWidth}px`,
                    height: `${pxHeight}px`,
                    border: '1px solid #d1d5db',
                    background: '#fff',
                    display: 'flex',
                    alignItems: 'stretch',
                    justifyContent: 'center',
                    padding: '3px 5px',
                    boxSizing: 'border-box',
                    overflow: 'hidden',
                    transform: `scale(${scale})`,
                    transformOrigin: '0 0',
                }}
            >
                <div
                    style={{
                        width: '100%',
                        minHeight: 0,
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'center',
                        gap: 0,
                    }}
                >
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
                            flexShrink: 0,
                            marginBottom: `${nameBarcodeGap}px`,
                        }}
                    >
                        {getLabelTitle(row)}
                    </div>
                    <BarcodeBars
                        code={row?.code ?? '123456789'}
                        barHeight={barHeight}
                        fontWeight={fw}
                    />
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'center',
                            fontSize: `${settings.fontSize}px`,
                            fontWeight: fw,
                            fontFamily: 'monospace',
                            lineHeight: 1,
                            flexShrink: 0,
                            marginTop: `${barcodePriceGap}px`,
                        }}
                    >
                        <span>{formatLabelPrice(price)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function BarcodePrint({ barcodes }) {
    const [settings, setSettings] = useState({
        width: 2,
        height: 1.25,
        fontSize: 8,
        fontWeight: 'normal',
        copies: 1,
    });

    const set = (key, value) =>
        setSettings((prev) => ({ ...prev, [key]: value }));

    const previewRow = barcodes[0] ?? null;

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

                <div className="mb-3 rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-gray-800">
                            Print Settings
                        </h2>
                    </div>
                    <div className="grid grid-cols-3 divide-x">
                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-600 uppercase">
                                Dimensions
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label className="mb-1 block text-xs text-gray-500">
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
                                    <Label className="mb-1 block text-xs text-gray-500">
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
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-600 uppercase">
                                Content
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label className="mb-1 block text-xs text-gray-500">
                                        Font Size (px)
                                    </Label>
                                    <Input
                                        type="number"
                                        min={6}
                                        max={24}
                                        step={1}
                                        value={settings.fontSize}
                                        onChange={(e) =>
                                            set(
                                                'fontSize',
                                                parseInt(e.target.value) || 8,
                                            )
                                        }
                                        className="h-7 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="mb-1 block text-xs text-gray-500">
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
                        </div>

                        <div className="px-4 py-3">
                            <p className="mb-2 text-[10px] font-semibold tracking-widest text-blue-600 uppercase">
                                Quantity
                            </p>
                            <div>
                                <Label className="mb-1 block text-xs text-gray-500">
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

                <div className="mb-3 rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-2.5">
                        <h2 className="text-sm font-semibold text-gray-800">
                            Preview
                        </h2>
                    </div>
                    <div
                        className="flex items-center justify-center bg-gray-100"
                        style={{ height: '280px' }}
                    >
                        {previewRow ? (
                            <LabelPreview
                                row={previewRow}
                                settings={settings}
                            />
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                No barcode to preview
                            </p>
                        )}
                    </div>
                    <div className="border-t px-4 py-2 text-center">
                        <p className="text-xs text-gray-400">
                            {settings.width}" × {settings.height}" ·{' '}
                            {settings.copies}× per label
                        </p>
                    </div>
                </div>

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
                            <div
                                key={row.id}
                                className="flex items-center gap-3 px-4 py-2"
                            >
                                <div style={{ width: '120px', flexShrink: 0 }}>
                                    <BarcodeBars
                                        code={row.code}
                                        barHeight={28}
                                        fontWeight={400}
                                    />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-gray-800">
                                        {row.name}
                                    </p>
                                    {row.variation && (
                                        <p className="text-xs text-muted-foreground">
                                            {
                                                row.variation.variation_data
                                                    ?.label
                                            }
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
