import { route } from '@/lib/route';
import { Package, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Input } from '@/components/ui/input';

export function ProductSearchBox({ onAdd, apiRoute = 'api.products.sell' }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const timerRef = useRef(null);
    const ref = useRef(null);
    const apiUrl = route(apiRoute);

    async function fetchProducts(search) {
        setLoading(true);
        try {
            const res = await fetch(`${apiUrl}?search=${encodeURIComponent(search)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
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
        if (results.length === 0) fetchProducts('');
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
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    function addItem(product, variation) {
        onAdd({
            product_id: product.id,
            product_name: product.name,
            product_code: product.code,
            variation_id: variation?.id ?? null,
            variation_label: variation?.label ?? variation?.variation_data?.label ?? null,
            unit_price: variation ? parseFloat(variation.price ?? variation.sale_price ?? 0) : parseFloat(product.sale_price ?? 0),
            quantity: '1',
            available_stock: variation ? parseFloat(variation.stock ?? 0) : parseFloat(product.stock ?? 0),
        });
        setOpen(false);
        setQuery('');
    }

    return (
        <div ref={ref} className="relative">
            <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
                value={query}
                onChange={handleChange}
                onFocus={handleFocus}
                placeholder="Search product by name or code…"
                className="h-8 pl-8 text-xs"
            />
            {open && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
                    {loading ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">Loading…</p>
                    ) : results.length === 0 ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">No products found.</p>
                    ) : (
                        <ul className="max-h-64 overflow-auto">
                            {results.map((p) => (
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
                                            {(p.variations ?? []).map((v) => (
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
