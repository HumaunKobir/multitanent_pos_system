import { route } from '@/lib/route';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

function productLines(product) {
    if (!product.has_variations) {
        return [{ product, variation: null }];
    }

    return (product.variations ?? []).map((variation) => ({ product, variation }));
}

function buildItem(product, variation) {
    const unitPrice = variation ? parseFloat(variation.purchase_price ?? 0) : parseFloat(product.purchase_price ?? 0);
    const sellPrice = variation ? parseFloat(variation.sale_price ?? 0) : parseFloat(product.sale_price ?? 0);
    const currentStock = variation ? parseFloat(variation.stock ?? 0) : parseFloat(product.stock ?? 0);

    return {
        product_id: product.id,
        product_name: product.name,
        product_code: product.code,
        variation_id: variation?.id ?? null,
        variation_label: variation?.label ?? null,
        unit_price: unitPrice,
        sell_price: sellPrice,
        current_stock: currentStock,
        quantity: 1,
        free_quantity: 0,
        distribute_quantity: 0,
        expiry_date: '',
        serial: '',
    };
}

export function PurchaseProductSearchBox({ items, onAdd, onRemove }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const timerRef = useRef(null);
    const ref = useRef(null);
    const apiUrl = route('api.products.purchase');

    function isSelected(productId, variationId) {
        return items.some(
            (item) => item.product_id === productId && String(item.variation_id) === String(variationId ?? null),
        );
    }

    async function fetchProducts(search) {
        setLoading(true);
        try {
            const res = await fetch(`${apiUrl}?search=${encodeURIComponent(search)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            setResults(Array.isArray(data) ? data : []);
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }

    function handleFocus() {
        setOpen(true);
        if (results.length === 0) {
            fetchProducts('');
        }
    }

    function handleChange(e) {
        const val = e.target.value;
        setQuery(val);
        setOpen(true);
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => fetchProducts(val), 350);
    }

    function toggleLine(product, variation, checked) {
        const productId = product.id;
        const variationId = variation?.id ?? null;

        if (checked && !isSelected(productId, variationId)) {
            onAdd(buildItem(product, variation));
            return;
        }

        if (!checked && isSelected(productId, variationId)) {
            onRemove(productId, variationId);
        }
    }

    function toggleLines(lines, checked) {
        lines.forEach(({ product, variation }) => toggleLine(product, variation, checked));
    }

    function toggleProductAll(product, checked) {
        toggleLines(productLines(product), checked);
    }

    function productAllSelected(product) {
        const lines = productLines(product);
        return lines.length > 0 && lines.every(({ product: p, variation }) => isSelected(p.id, variation?.id));
    }

    function productSomeSelected(product) {
        const lines = productLines(product);
        return lines.some(({ product: p, variation }) => isSelected(p.id, variation?.id));
    }

    const allLines = results.flatMap(productLines);
    const allSelected = allLines.length > 0 && allLines.every(({ product, variation }) => isSelected(product.id, variation?.id));
    const someSelected = allLines.some(({ product, variation }) => isSelected(product.id, variation?.id));

    async function triggerBarcodeSearch(term) {
        clearTimeout(timerRef.current);
        setLoading(true);
        try {
            const res = await fetch(`${apiUrl}?search=${encodeURIComponent(term)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const json = await res.json();
            const data = Array.isArray(json) ? json : [];
            const exact = data.find((p) => p.code === term);
            const match = exact ?? (data.length === 1 ? data[0] : null);
            if (match && !match.has_variations) {
                toggleLine(match, null, true);
            } else if (match && match.has_variations) {
                setResults([match]);
                setQuery('');
                setOpen(true);
            } else {
                setQuery(term);
                setResults(data);
                setOpen(true);
            }
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }

    async function handleKeyDown(e) {
        if (e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        const term = query.trim();
        if (term) {
            triggerBarcodeSearch(term);
        }
    }

    const triggerRef = useRef(null);
    triggerRef.current = triggerBarcodeSearch;
    const globalBufRef = useRef('');
    const globalLastKeyRef = useRef(0);

    useEffect(() => {
        function handleClick(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    useEffect(() => {
        function onGlobalKey(e) {
            const tag = document.activeElement?.tagName ?? '';
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) {
                return;
            }
            const now = Date.now();
            if (now - globalLastKeyRef.current > 100) {
                globalBufRef.current = '';
            }
            globalLastKeyRef.current = now;
            if (e.key === 'Enter') {
                const term = globalBufRef.current.trim();
                globalBufRef.current = '';
                if (term) {
                    triggerRef.current(term);
                }
            } else if (e.key.length === 1) {
                globalBufRef.current += e.key;
            }
        }
        document.addEventListener('keydown', onGlobalKey);
        return () => document.removeEventListener('keydown', onGlobalKey);
    }, []);

    return (
        <div ref={ref} className="relative">
            <div className="relative">
                <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={handleChange}
                    onFocus={handleFocus}
                    onKeyDown={handleKeyDown}
                    placeholder="Click or search product by name / code…"
                    className="h-8 pl-8 text-xs"
                />
            </div>

            {open && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
                    {loading ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">Loading…</p>
                    ) : results.length === 0 ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">No products found.</p>
                    ) : (
                        <>
                            <div className="flex items-center gap-2 border-b border-border bg-muted/20 px-3 py-2">
                                <Checkbox
                                    checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                                    onCheckedChange={(checked) => toggleLines(allLines, Boolean(checked))}
                                    aria-label="Select all products"
                                />
                                <span className="text-xs font-medium">Select All</span>
                            </div>
                            <ul className="max-h-64 overflow-auto">
                                {results.map((product) => (
                                    <li key={product.id} className="border-b border-border/50 last:border-0">
                                        {!product.has_variations ? (
                                            <div className="flex items-center justify-between px-3 py-2 text-xs hover:bg-accent">
                                                <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                                    <Checkbox
                                                        checked={isSelected(product.id, null)}
                                                        onCheckedChange={(checked) => toggleLine(product, null, Boolean(checked))}
                                                        aria-label={`Select ${product.name}`}
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="font-medium">{product.name}</span>
                                                        {product.code && (
                                                            <span className="ml-2 text-muted-foreground">{product.code}</span>
                                                        )}
                                                    </span>
                                                </label>
                                                <span className="ml-4 flex shrink-0 items-center gap-3 text-muted-foreground">
                                                    <span>Stock: {parseFloat(product.stock ?? 0)}</span>
                                                    <span>৳{parseFloat(product.purchase_price ?? 0).toFixed(2)}</span>
                                                </span>
                                            </div>
                                        ) : (
                                            <div>
                                                <div className="flex items-center gap-2 bg-muted/30 px-3 py-1.5">
                                                    <Checkbox
                                                        checked={
                                                            productAllSelected(product)
                                                                ? true
                                                                : productSomeSelected(product)
                                                                  ? 'indeterminate'
                                                                  : false
                                                        }
                                                        onCheckedChange={(checked) => toggleProductAll(product, Boolean(checked))}
                                                        aria-label={`Select all variants of ${product.name}`}
                                                    />
                                                    <span className="text-xs font-semibold">{product.name}</span>
                                                    {product.code && (
                                                        <span className="text-[10px] text-muted-foreground">{product.code}</span>
                                                    )}
                                                    <button
                                                        type="button"
                                                        className="ml-auto text-[10px] font-medium text-blue-600 hover:underline"
                                                        onClick={() => toggleProductAll(product, !productAllSelected(product))}
                                                    >
                                                        Select All ({product.variations?.length ?? 0})
                                                    </button>
                                                </div>
                                                {(product.variations ?? []).map((variation) => (
                                                    <div
                                                        key={variation.id}
                                                        className="flex items-center justify-between py-1.5 pr-3 pl-7 text-xs hover:bg-accent"
                                                    >
                                                        <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                                            <Checkbox
                                                                checked={isSelected(product.id, variation.id)}
                                                                onCheckedChange={(checked) =>
                                                                    toggleLine(product, variation, Boolean(checked))
                                                                }
                                                                aria-label={`Select ${product.name} ${variation.label}`}
                                                            />
                                                            <span className="inline-flex items-center rounded-none bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 ring-1 ring-blue-200">
                                                                {variation.label}
                                                            </span>
                                                        </label>
                                                        <span className="ml-4 flex shrink-0 items-center gap-3 text-muted-foreground">
                                                            <span>Stock: {parseFloat(variation.stock ?? 0)}</span>
                                                            <span>৳{parseFloat(variation.purchase_price ?? 0).toFixed(2)}</span>
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
