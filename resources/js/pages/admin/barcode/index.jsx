import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, router, usePage } from '@inertiajs/react';
import { Barcode, Printer, Search } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

function BarcodeBars({ code }) {
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
    }, [code]);

    return (
        <div ref={wrapRef} style={{ width: '160px', height: '36px', overflow: 'hidden' }}>
            <div
                ref={textRef}
                style={{
                    fontFamily: "'Libre Barcode 128', monospace",
                    fontSize: '36px',
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

export default function BarcodeIndex({ barcodes, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedIds, setSelectedIds] = useState([]);

    const rows = barcodes.data ?? [];

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

    const allSelected = rows.length > 0 && rows.every((r) => selectedIds.includes(r.id));

    const toggleAll = () => setSelectedIds(allSelected ? [] : rows.map((r) => r.id));

    const toggleOne = (id) =>
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const handlePrintLabels = () => {
        const ids = selectedIds.length > 0 ? selectedIds : rows.map((r) => r.id);
        router.visit(route('barcode.print') + (ids.length ? `?ids=${ids.join(',')}` : ''));
    };

    const handlePrintSingle = (row) => {
        router.visit(route('barcode.print') + `?ids=${row.id}`);
    };

    const columns = [
        {
            id: 'select',
            header: (
                <Checkbox
                    checked={allSelected}
                    onCheckedChange={toggleAll}
                />
            ),
            render: (row) => (
                <Checkbox
                    checked={selectedIds.includes(row.id)}
                    onCheckedChange={() => toggleOne(row.id)}
                />
            ),
        },
        {
            id: 'num',
            header: '#',
            render: (_, i) => (barcodes.from ?? 0) + i,
        },
        {
            id: 'barcode',
            header: 'Barcode',
            render: (row) => <BarcodeBars code={row.code} />,
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
            render: (row) => (
                <Button size="sm" variant="outline" onClick={() => handlePrintSingle(row)}>
                    <Printer className="size-3.5" />
                </Button>
            ),
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
                    <Button
                        onClick={handlePrintLabels}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    >
                        <Printer className="size-4" />
                        {selectedIds.length > 0 ? `Print ${selectedIds.length} Selected` : 'Print Labels'}
                    </Button>
                </div>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or code..."
                            className="pl-8"
                        />
                    </div>
                    {selectedIds.length > 0 && (
                        <span className="text-xs text-muted-foreground">
                            {selectedIds.length} of {rows.length} selected
                        </span>
                    )}
                </div>

                <DataTable columns={columns} rows={rows} rowKey="id" emptyMessage="No barcodes found." />

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
