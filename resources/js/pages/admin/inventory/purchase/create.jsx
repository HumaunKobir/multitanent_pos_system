import { route } from '@/lib/route';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, HandCoins, Plus, Search, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

function useDebouncedSearch(url, paramName = 'search', delay = 350) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const timerRef = useRef(null);

    useEffect(() => {
        if (!query.trim()) { setResults([]); return; }
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(async () => {
            setLoading(true);
            try {
                const res = await fetch(`${url}?${paramName}=${encodeURIComponent(query)}`);
                setResults(await res.json());
            } finally {
                setLoading(false);
            }
        }, delay);
        return () => clearTimeout(timerRef.current);
    }, [query, url, paramName, delay]);

    return { query, setQuery, results, loading };
}

function SupplierSearch({ suppliers, value, onChange }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const ref = useRef(null);
    const selected = suppliers.find((s) => String(s.id) === String(value));

    const filtered = q
        ? suppliers.filter((s) =>
              s.name.toLowerCase().includes(q.toLowerCase()) ||
              s.phone.includes(q),
          )
        : suppliers;

    useEffect(() => {
        function handleClick(e) { if (ref.current && !ref.current.contains(e.target)) setOpen(false); }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    return (
        <div ref={ref} className="relative">
            <div
                className="flex min-h-9 cursor-pointer items-center justify-between border border-input bg-background px-3 py-1.5 text-sm shadow-xs"
                onClick={() => setOpen((p) => !p)}
            >
                {selected ? (
                    <span>{selected.name} <span className="text-xs text-muted-foreground">({selected.phone})</span></span>
                ) : (
                    <span className="text-muted-foreground">Select supplier…</span>
                )}
            </div>
            {open && (
                <div className="absolute z-50 mt-1 w-full border border-border bg-popover shadow-md">
                    <div className="p-2">
                        <Input
                            autoFocus
                            placeholder="Search supplier…"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            className="h-8 text-sm"
                        />
                    </div>
                    <ul className="max-h-52 overflow-auto">
                        {filtered.length === 0 ? (
                            <li className="px-3 py-2 text-sm text-muted-foreground">No suppliers found.</li>
                        ) : (
                            filtered.map((s) => (
                                <li
                                    key={s.id}
                                    className="cursor-pointer px-3 py-2 text-sm hover:bg-accent"
                                    onClick={() => { onChange(String(s.id)); setOpen(false); setQ(''); }}
                                >
                                    {s.name} <span className="text-xs text-muted-foreground">{s.phone}</span>
                                </li>
                            ))
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

function ProductSearchBox({ onAdd }) {
    const { query, setQuery, results, loading } = useDebouncedSearch(route('api.products.purchase'));
    const [open, setOpen] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedVariation, setSelectedVariation] = useState('');
    const ref = useRef(null);

    useEffect(() => {
        setOpen(results.length > 0);
    }, [results]);

    useEffect(() => {
        function handleClick(e) { if (ref.current && !ref.current.contains(e.target)) setOpen(false); }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    function selectProduct(product) {
        setSelectedProduct(product);
        setOpen(false);
        setQuery('');
        setSelectedVariation('');
        if (!product.has_variations) {
            addItem(product, null);
        }
    }

    function addItem(product, variation) {
        const unitPrice = variation ? parseFloat(variation.purchase_price ?? 0) : parseFloat(product.purchase_price ?? 0);
        onAdd({
            product_id: product.id,
            product_name: product.name,
            product_code: product.code,
            variation_id: variation?.id ?? null,
            variation_label: variation?.label ?? null,
            unit_price: unitPrice,
            quantity: 1,
            free_quantity: 0,
            expiry_date: '',
            serial: '',
        });
        setSelectedProduct(null);
        setSelectedVariation('');
    }

    return (
        <div ref={ref} className="relative">
            <div className="flex gap-2">
                <div className="relative flex-1">
                    <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search product by name or code…"
                        className="pl-8 text-sm"
                    />
                </div>
            </div>

            {/* Product dropdown */}
            {open && (
                <div className="absolute z-50 mt-1 w-full border border-border bg-popover shadow-md">
                    {loading ? (
                        <p className="px-3 py-2 text-sm text-muted-foreground">Searching…</p>
                    ) : (
                        <ul className="max-h-56 overflow-auto">
                            {results.map((p) => (
                                <li
                                    key={p.id}
                                    className="cursor-pointer border-b border-border/50 px-3 py-2 text-sm hover:bg-accent last:border-0"
                                    onClick={() => selectProduct(p)}
                                >
                                    <span className="font-medium">{p.name}</span>
                                    {p.code && <span className="ml-2 text-xs text-muted-foreground">{p.code}</span>}
                                    {p.has_variations && <span className="ml-2 text-xs text-blue-600">Has variations</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {/* Variation picker */}
            {selectedProduct?.has_variations && (
                <div className="mt-2 flex items-center gap-2 border border-dashed border-border p-2">
                    <span className="text-sm font-medium">{selectedProduct.name}</span>
                    <Select value={selectedVariation} onValueChange={setSelectedVariation}>
                        <SelectTrigger className="h-8 w-48 text-xs">
                            <SelectValue placeholder="Select variation…" />
                        </SelectTrigger>
                        <SelectContent>
                            {selectedProduct.variations.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    {v.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button
                        size="sm"
                        type="button"
                        disabled={!selectedVariation}
                        onClick={() => {
                            const v = selectedProduct.variations.find((x) => String(x.id) === selectedVariation);
                            if (v) addItem(selectedProduct, v);
                        }}
                    >
                        <Plus className="size-3.5" />
                        Add
                    </Button>
                    <Button size="sm" type="button" variant="ghost" onClick={() => setSelectedProduct(null)}>
                        Cancel
                    </Button>
                </div>
            )}
        </div>
    );
}

export default function PurchaseCreate({ suppliers, today }) {
    const form = useForm({
        supplier_id: '',
        date: today,
        discount: '0',
        vat: '0',
        paid_amount: '0',
        comment: '',
        items: [],
    });

    const [items, setItems] = useState([]);

    const grossAmount = items.reduce((sum, it) => sum + parseFloat(it.quantity || 0) * parseFloat(it.unit_price || 0), 0);
    const vatAmount = grossAmount * (parseFloat(form.data.vat || 0) / 100);
    const netAmount = grossAmount + vatAmount - parseFloat(form.data.discount || 0);
    const dueAmount = Math.max(0, netAmount - parseFloat(form.data.paid_amount || 0));

    function addItem(item) {
        const duplicate = items.find(
            (it) => it.product_id === item.product_id && String(it.variation_id) === String(item.variation_id),
        );
        if (duplicate) {
            setItems((prev) =>
                prev.map((it) =>
                    it.product_id === item.product_id && String(it.variation_id) === String(item.variation_id)
                        ? { ...it, quantity: parseFloat(it.quantity) + 1 }
                        : it,
                ),
            );
            return;
        }
        setItems((prev) => [...prev, item]);
    }

    function updateItem(index, field, value) {
        setItems((prev) => prev.map((it, i) => (i === index ? { ...it, [field]: value } : it)));
    }

    function removeItem(index) {
        setItems((prev) => prev.filter((_, i) => i !== index));
    }

    function handleSubmit(e) {
        e.preventDefault();
        form.setData('items', items);
        form.transform((data) => ({ ...data, items }));
        form.post(route('inventory.purchase.store'));
    }

    const inputCls = 'h-8 rounded-none border-border/60 text-sm px-2 focus:border-primary';

    return (
        <>
            <Head title="New Purchase" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <HandCoins className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">New Purchase</h1>
                            <p className="text-xs text-white/60">Create a new purchase entry.</p>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        variant="ghost"
                        asChild
                        className="border border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                    >
                        <Link href={route('inventory.purchase.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit}>
                    {/* Header */}
                    <div className="mb-4 grid grid-cols-1 gap-4 border border-border p-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Supplier *</Label>
                            <SupplierSearch
                                suppliers={suppliers}
                                value={form.data.supplier_id}
                                onChange={(v) => form.setData('supplier_id', v)}
                            />
                            {form.errors.supplier_id && <p className="text-xs text-destructive">{form.errors.supplier_id}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="date">Date *</Label>
                            <Input
                                id="date"
                                type="date"
                                value={form.data.date}
                                onChange={(e) => form.setData('date', e.target.value)}
                            />
                            {form.errors.date && <p className="text-xs text-destructive">{form.errors.date}</p>}
                        </div>
                    </div>

                    {/* Product Search */}
                    <div className="mb-3 border border-border p-4">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Add Products</p>
                        <ProductSearchBox onAdd={addItem} />
                        {form.errors.items && <p className="mt-1 text-xs text-destructive">{form.errors.items}</p>}
                    </div>

                    {/* Line Items Table */}
                    {items.length > 0 && (
                        <div className="mb-4 overflow-x-auto border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">Product</th>
                                        <th className="px-3 py-2 text-right font-semibold">Unit Price</th>
                                        <th className="px-3 py-2 text-right font-semibold">Qty</th>
                                        <th className="px-3 py-2 text-right font-semibold">Free Qty</th>
                                        <th className="px-3 py-2 text-center font-semibold">Expiry</th>
                                        <th className="px-3 py-2 text-right font-semibold">Sub Total</th>
                                        <th className="px-3 py-2 text-right font-semibold"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {items.map((item, i) => {
                                        const subTotal = parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0);
                                        return (
                                            <tr key={i} className="hover:bg-muted/20">
                                                <td className="px-3 py-2 text-muted-foreground">{i + 1}</td>
                                                <td className="px-3 py-2">
                                                    <p className="font-medium">{item.product_name}</p>
                                                    {item.variation_label && (
                                                        <p className="text-xs text-muted-foreground">{item.variation_label}</p>
                                                    )}
                                                </td>
                                                <td className="px-2 py-1.5">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={item.unit_price}
                                                        onChange={(e) => updateItem(i, 'unit_price', e.target.value)}
                                                        className={`${inputCls} w-24 text-right`}
                                                    />
                                                </td>
                                                <td className="px-2 py-1.5">
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(i, 'quantity', e.target.value)}
                                                        className={`${inputCls} w-20 text-right`}
                                                    />
                                                </td>
                                                <td className="px-2 py-1.5">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        value={item.free_quantity}
                                                        onChange={(e) => updateItem(i, 'free_quantity', e.target.value)}
                                                        className={`${inputCls} w-20 text-right`}
                                                    />
                                                </td>
                                                <td className="px-2 py-1.5">
                                                    <Input
                                                        type="date"
                                                        value={item.expiry_date}
                                                        onChange={(e) => updateItem(i, 'expiry_date', e.target.value)}
                                                        className={`${inputCls} w-36`}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold">
                                                    ৳{subTotal.toFixed(2)}
                                                </td>
                                                <td className="px-2 py-1.5 text-right">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => removeItem(i)}
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Summary + Payment */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {/* Comment */}
                        <div className="space-y-1.5 border border-border p-4">
                            <Label htmlFor="comment">Comment</Label>
                            <textarea
                                id="comment"
                                rows={4}
                                value={form.data.comment}
                                onChange={(e) => form.setData('comment', e.target.value)}
                                placeholder="Optional note…"
                                className="w-full resize-none border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                            />
                        </div>

                        {/* Totals */}
                        <div className="border border-border p-4">
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Gross Amount</span>
                                    <span className="font-medium">৳{grossAmount.toFixed(2)}</span>
                                </div>

                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">Discount</span>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.discount}
                                        onChange={(e) => form.setData('discount', e.target.value)}
                                        className={`${inputCls} w-28 text-right`}
                                    />
                                </div>

                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">VAT %</span>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.vat}
                                        onChange={(e) => form.setData('vat', e.target.value)}
                                        className={`${inputCls} w-28 text-right`}
                                    />
                                </div>

                                <div className="flex justify-between border-t border-border pt-2">
                                    <span className="font-semibold">Net Amount</span>
                                    <span className="font-bold text-primary">৳{netAmount.toFixed(2)}</span>
                                </div>

                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">Paid Amount</span>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.paid_amount}
                                        onChange={(e) => form.setData('paid_amount', e.target.value)}
                                        className={`${inputCls} w-28 text-right`}
                                    />
                                </div>

                                <div className="flex justify-between border-t border-border pt-2">
                                    <span className="font-semibold text-destructive">Due Amount</span>
                                    <span className="font-bold text-destructive">৳{dueAmount.toFixed(2)}</span>
                                </div>
                            </div>

                            {form.errors.paid_amount && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.paid_amount}</p>
                            )}
                        </div>
                    </div>

                    <div className="mt-4 flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={route('inventory.purchase.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing || items.length === 0 || !form.data.supplier_id}>
                            {form.processing ? 'Saving…' : 'Create Purchase'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
