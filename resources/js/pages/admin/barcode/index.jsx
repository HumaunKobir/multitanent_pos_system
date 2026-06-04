import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, router, usePage } from '@inertiajs/react';
import { Barcode, Printer, Search } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

const BARCODE_WIDTH = 220;

function BarcodeLabel({ code }) {
    const textRef = useRef(null);
    const [scaleX, setScaleX] = useState(1);

    useLayoutEffect(() => {
        const measure = () => {
            if (textRef.current) {
                const w = textRef.current.scrollWidth;
                if (w > 0) setScaleX(BARCODE_WIDTH / w);
            }
        };
        document.fonts?.ready ? document.fonts.ready.then(measure) : measure();
    }, [code]);

    return (
        <div style={{ width: `${BARCODE_WIDTH}px`, height: '40px', overflow: 'hidden' }}>
            <div
                ref={textRef}
                style={{
                    fontFamily: "'Libre Barcode 128', monospace",
                    fontSize: '40px',
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

function PrintWindow({ row }) {
    const handlePrint = () => {
        const win = window.open('', '_blank', 'width=500,height=400');
        win.document.write(`<!DOCTYPE html>
<html>
<head>
  <title>Barcode - ${row.code}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
  <style>
    body { margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: white; }
    .label { display: inline-flex; flex-direction: column; align-items: center; border: 1px solid #ccc; padding: 10px 16px; }
    .name { font-size: 11px; font-family: sans-serif; margin-bottom: 4px; text-align: center; max-width: 200px; }
    .bars-wrap { width: 200px; overflow: hidden; height: 60px; }
    .bars { font-family: 'Libre Barcode 128', monospace; font-size: 60px; line-height: 1; white-space: nowrap; display: inline-block; transform-origin: 0 0; }
    .code { font-size: 11px; font-family: monospace; letter-spacing: 3px; margin-top: 4px; }
  </style>
</head>
<body>
  <div class="label">
    <div class="name">${row.name}</div>
    <div class="bars-wrap">
      <div class="bars" id="bars">${row.code}</div>
    </div>
    <div class="code">${row.code}</div>
  </div>
  <script>
    document.fonts.ready.then(() => {
      var el = document.getElementById('bars');
      var w = el.scrollWidth;
      if (w > 0) el.style.transform = 'scaleX(' + (200 / w) + ')';
      window.print();
      window.close();
    });
  <\/script>
</body>
</html>`);
        win.document.close();
    };

    return (
        <Button size="sm" variant="outline" onClick={handlePrint}>
            <Printer className="size-3.5" />
        </Button>
    );
}

export default function BarcodeIndex({ barcodes, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(route('barcode.index'), { search: search || undefined }, { preserveState: true, replace: true });
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (barcodes.from ?? 0) + i,
        },
        {
            id: 'barcode',
            header: 'Barcode',
            render: (row) => <BarcodeLabel code={row.code} />,
        },
        {
            id: 'product',
            header: 'Product / Variant',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.product?.name ?? row.name}</p>
                    {row.variation && (
                        <p className="text-xs text-muted-foreground">{row.variation.variation_data?.label}</p>
                    )}
                </div>
            ),
        },
        {
            id: 'code',
            header: 'Code',
            render: (row) => (
                <span className="font-mono text-sm font-semibold tracking-widest">{row.code}</span>
            ),
        },
        {
            id: 'actions',
            header: '',
            align: 'right',
            render: (row) => <PrintWindow row={row} />,
        },
    ];

    return (
        <>
            <Head title="Barcodes" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Barcode className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Barcodes</h1>
                            <p className="text-xs text-white/60">Auto-generated from product codes &amp; SKUs.</p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 flex gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or code..."
                            className="pl-8"
                        />
                    </div>
                </div>

                <DataTable columns={columns} rows={barcodes.data} rowKey="id" emptyMessage="No barcodes found." />

                {barcodes.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {barcodes.links.map((link, i) => (
                            <a
                                key={i}
                                href={link.url ?? '#'}
                                className={[
                                    'border px-3 py-1 text-sm transition-colors',
                                    link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
