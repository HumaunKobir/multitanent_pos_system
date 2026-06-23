import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, router, usePage } from '@inertiajs/react';
import { Barcode, ListChecks, Printer, RotateCcw, Search } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AdminPagination } from '@/components/admin/pagination';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

function parseSerialRange(input) {
    const trimmed = String(input ?? '').trim();

    if (!trimmed) {
        return null;
    }

    const rangeMatch = trimmed.match(/^(\d+)\s*-\s*(\d+)$/);

    if (rangeMatch) {
        const from = parseInt(rangeMatch[1], 10);
        const to = parseInt(rangeMatch[2], 10);

        if (Number.isNaN(from) || Number.isNaN(to) || from < 1 || to < 1) {
            return null;
        }

        return from <= to ? { from, to } : { from: to, to: from };
    }

    const single = parseInt(trimmed, 10);

    if (!Number.isNaN(single) && single >= 1) {
        return { from: single, to: single };
    }

    return null;
}

function getRowSerial(rowIndex, barcodes) {
    return Number(barcodes.from ?? 1) + rowIndex;
}

function selectIdsForSerialRange(range, rows, barcodes) {
    return rows
        .filter((_, i) => {
            const serial = getRowSerial(i, barcodes);
            return serial >= range.from && serial <= range.to;
        })
        .map((row) => Number(row.id));
}

function isIdSelected(selectedIds, id) {
    return selectedIds.includes(Number(id));
}

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

export default function BarcodeIndex({ barcodes, filters, branches = {}, mainBranchId = null }) {
    const { flash } = usePage().props;
    const defaultBranchId = mainBranchId != null ? String(mainBranchId) : 'all';
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ?? defaultBranchId);
    const [serialRange, setSerialRange] = useState('');
    const [selectedIds, setSelectedIds] = useState([]);
    const [selectingRange, setSelectingRange] = useState(false);

    const rows = barcodes.data ?? [];
    const branchOptions = Object.entries(branches || {}).map(([value, label]) => ({ value, label }));
    const showBranchFilter = branchOptions.length > 0;

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useEffect(() => {
        setSelectedIds([]);
    }, [filters.search, filters.branch_id]);

    useDebouncedEffect(
        () => {
            router.get(
                route('barcode.index'),
                {
                    search: search || undefined,
                    ...(showBranchFilter ? { branch_id: branchId } : {}),
                },
                { preserveState: true, replace: true },
            );
        },
        [search, branchId, showBranchFilter],
        350,
        { skipFirstRun: true },
    );

    function handleReset() {
        setSearch('');
        if (showBranchFilter) {
            setBranchId(defaultBranchId);
        }
    }

    const allSelected = rows.length > 0 && rows.every((r) => isIdSelected(selectedIds, r.id));

    const toggleAll = () => setSelectedIds(allSelected ? [] : rows.map((r) => Number(r.id)));

    const toggleOne = (id) => {
        const numericId = Number(id);

        setSelectedIds((prev) =>
            prev.includes(numericId) ? prev.filter((x) => x !== numericId) : [...prev, numericId],
        );
    };

    const handlePrintLabels = () => {
        const ids = selectedIds.length > 0 ? selectedIds : rows.map((r) => r.id);
        router.visit(route('barcode.print') + (ids.length ? `?ids=${ids.join(',')}` : ''));
    };

    const handlePrintSingle = (row) => {
        router.visit(route('barcode.print') + `?ids=${row.id}`);
    };

    const handleSelectSerialRange = async () => {
        const range = parseSerialRange(serialRange);

        if (!range) {
            toast.error('Enter a serial range like 1-10');
            return;
        }

        setSelectingRange(true);

        const listStart = Number(barcodes.from ?? 1);
        const listEnd = rows.length > 0 ? listStart + rows.length - 1 : listStart;
        const activeSearch = filters.search ?? '';
        const activeBranchId = filters.branch_id ?? defaultBranchId;

        try {
            if (range.from >= listStart && range.to <= listEnd) {
                const ids = selectIdsForSerialRange(range, rows, barcodes);

                if (ids.length === 0) {
                    toast.error('No barcodes found in that serial range');
                    return;
                }

                setSelectedIds(ids);
                toast.success(`Selected ${ids.length} barcode${ids.length !== 1 ? 's' : ''}`);
                return;
            }

            const url = route('barcode.serial-range', {
                query: {
                    from: range.from,
                    to: range.to,
                    search: activeSearch || undefined,
                    ...(showBranchFilter ? { branch_id: activeBranchId } : {}),
                },
            });

            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) {
                toast.error('Could not select serial range');
                return;
            }

            const data = await res.json();
            const ids = (data.ids ?? []).map(Number);

            if (ids.length === 0) {
                toast.error('No barcodes found in that serial range');
                return;
            }

            setSelectedIds(ids);
            toast.success(`Selected ${ids.length} barcode${ids.length !== 1 ? 's' : ''}`);
        } catch {
            toast.error('Could not select serial range');
        } finally {
            setSelectingRange(false);
        }
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
                    checked={isIdSelected(selectedIds, row.id)}
                    onCheckedChange={() => toggleOne(row.id)}
                />
            ),
        },
        {
            id: 'num',
            header: '#',
            render: (_, i) => getRowSerial(i, barcodes),
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

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or code..."
                            className="pl-8"
                        />
                    </div>
                    {showBranchFilter && (
                        <Select value={branchId} onValueChange={(v) => setBranchId(v)}>
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Branch" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All branches</SelectItem>
                                {branchOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    <Button variant="outline" size="icon" onClick={handleReset} title="Reset filters">
                        <RotateCcw className="size-4" />
                    </Button>
                    <div className="flex items-center gap-1.5">
                        <div className="relative w-36">
                            <ListChecks className="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={serialRange}
                                onChange={(e) => setSerialRange(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        handleSelectSerialRange();
                                    }
                                }}
                                placeholder="SL: 1-10"
                                className="pl-8"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            onClick={handleSelectSerialRange}
                            disabled={selectingRange}
                        >
                            Select
                        </Button>
                    </div>
                    {selectedIds.length > 0 && (
                        <span className="text-xs text-muted-foreground">
                            {selectedIds.length} selected
                        </span>
                    )}
                </div>

                <DataTable columns={columns} rows={rows} rowKey="id" emptyMessage="No barcodes found." />

                <AdminPagination paginator={barcodes} />
            </div>
        </>
    );
}
