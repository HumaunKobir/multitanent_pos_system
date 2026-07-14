import { route } from '@/lib/route';
import { Barcode, Package, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Input } from '@/components/ui/input';

function resolveBarcodeMatch(data, term) {
    for (const product of data) {
        const barcodeHit = (product.barcodes ?? []).find((barcode) => String(barcode.code) === term);
        if (barcodeHit) {
            if (barcodeHit.product_variation_id) {
                const variation = (product.variations ?? []).find(
                    (item) => String(item.id) === String(barcodeHit.product_variation_id),
                );
                if (variation) {
                    return { product, variation };
                }
            }

            if (!product.has_variations) {
                return { product, variation: null };
            }
        }
    }

    const variationMatch = data
        .flatMap((product) => (product.variations ?? []).map((variation) => ({ product, variation })))
        .find(({ variation }) => String(variation.sku) === term);

    if (variationMatch) {
        return variationMatch;
    }

    const exact = data.find((product) => String(product.code) === term);
    if (exact && !exact.has_variations) {
        return { product: exact, variation: null };
    }

    if (exact?.has_variations) {
        return { product: exact, variation: null, needsVariantPick: true };
    }

    if (data.length === 1 && !data[0].has_variations) {
        return { product: data[0], variation: null };
    }

    if (data.length === 1 && data[0].has_variations) {
        return { product: data[0], variation: null, needsVariantPick: true };
    }

    return null;
}

export function ProductSearchBox({ onAdd, apiRoute = 'api.products.sell', listMaxHeightClassName = 'max-h-64' }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const timerRef = useRef(null);
    const ref = useRef(null);
    const inputRef = useRef(null);
    const skipOpenOnFocusRef = useRef(false);
    const apiUrl = route(apiRoute);

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
        if (skipOpenOnFocusRef.current) {
            skipOpenOnFocusRef.current = false;
            return;
        }
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

    useEffect(() => {
        function handleClick(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    function addItem(product, variation) {
        const availableStock = variation ? parseFloat(variation.stock ?? 0) : parseFloat(product.stock ?? 0);

        if (availableStock <= 0) {
            return false;
        }

        onAdd({
            product_id: product.id,
            product_name: product.name,
            product_code: product.code,
            variation_id: variation?.id ?? null,
            variation_label: variation?.label ?? variation?.variation_data?.label ?? null,
            unit_price: variation ? parseFloat(variation.price ?? variation.sale_price ?? 0) : parseFloat(product.sale_price ?? 0),
            quantity: '1',
            available_stock: availableStock,
        });
        setOpen(false);
        setQuery('');
        setResults([]);
        return true;
    }

    function addFromScan(product, variation) {
        const added = addItem(product, variation);
        if (!added) {
            return;
        }
        // Keep focus ready for the next scan, but do not reopen the picker.
        skipOpenOnFocusRef.current = true;
        requestAnimationFrame(() => inputRef.current?.focus());
    }

    async function triggerBarcodeSearch(term) {
        const trimmed = term.trim();
        if (!trimmed) {
            return;
        }

        clearTimeout(timerRef.current);
        setLoading(true);
        try {
            const res = await fetch(`${apiUrl}?search=${encodeURIComponent(trimmed)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const json = await res.json();
            const data = Array.isArray(json) ? json : [];
            const match = resolveBarcodeMatch(data, trimmed);

            if (match?.needsVariantPick) {
                setResults([match.product]);
                setQuery('');
                setOpen(true);
                return;
            }

            if (match) {
                addFromScan(match.product, match.variation);
                return;
            }

            setQuery(trimmed);
            setResults(data);
            setOpen(true);
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }

    function handleKeyDown(e) {
        if (e.key !== 'Enter') {
            return;
        }

        // Scanners send Enter after the code — never submit the parent form.
        e.preventDefault();
        e.stopPropagation();

        const term = (e.target.value ?? query).trim();
        if (term) {
            triggerBarcodeSearch(term);
        }
    }

    const triggerRef = useRef(null);
    triggerRef.current = triggerBarcodeSearch;
    const globalBufRef = useRef('');
    const globalLastKeyRef = useRef(0);

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
                    e.preventDefault();
                    e.stopPropagation();
                    triggerRef.current(term);
                }
            } else if (e.key.length === 1) {
                globalBufRef.current += e.key;
            }
        }
        document.addEventListener('keydown', onGlobalKey);
        return () => document.removeEventListener('keydown', onGlobalKey);
    }, []);

    function variationRows(product) {
        return (product.variations ?? []).filter((v) => parseFloat(v.stock ?? 0) > 0);
    }

    function isSelectableProduct(product) {
        if (!product.has_variations) {
            return parseFloat(product.stock ?? 0) > 0;
        }

        return variationRows(product).length > 0;
    }

    return (
        <div ref={ref} className="relative">
            <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
                ref={inputRef}
                value={query}
                onChange={handleChange}
                onFocus={handleFocus}
                onKeyDown={handleKeyDown}
                placeholder="Search or scan barcode…"
                className="h-8 pr-16 pl-8 text-xs"
                autoComplete="off"
            />
            <div className="pointer-events-none absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                <Barcode className="size-3" />
                Scan
            </div>
            {open && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
                    {loading ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">Loading…</p>
                    ) : results.filter(isSelectableProduct).length === 0 ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">No products found.</p>
                    ) : (
                        <ul className={`${listMaxHeightClassName} overflow-y-auto`}>
                            {results.filter(isSelectableProduct).map((p) => (
                                <li key={p.id} className="border-b border-border/50 last:border-0">
                                    {!p.has_variations ? (
                                        <div
                                            className="flex cursor-pointer items-center justify-between px-3 py-2 text-xs hover:bg-accent"
                                            onClick={() => addItem(p, null)}
                                        >
                                            <span className="font-medium">{p.name}</span>
                                            <span className="text-muted-foreground">Stock: {parseFloat(p.stock ?? 0)}</span>
                                        </div>
                                    ) : (
                                        <div>
                                            <div className="flex items-center gap-1.5 bg-muted/30 px-3 py-1.5">
                                                <Package className="size-3 text-muted-foreground" />
                                                <span className="text-xs font-semibold">{p.name}</span>
                                            </div>
                                            {variationRows(p).map((v) => (
                                                <div
                                                    key={v.id}
                                                    className="flex cursor-pointer items-center justify-between py-1.5 pr-3 pl-7 text-xs hover:bg-accent"
                                                    onClick={() => addItem(p, v)}
                                                >
                                                    <span>{v.label ?? v.variation_data?.label}</span>
                                                    <span className="text-muted-foreground">Stock: {parseFloat(v.stock ?? 0)}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
