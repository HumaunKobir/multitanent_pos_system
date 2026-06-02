import { route } from '@/lib/route';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, HandCoins, MessageSquare, Package, Plus, Save, Search, ShoppingCart, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

function Card({ title, icon: Icon, children }) {
    return (
        <div className="rounded-lg border bg-card shadow-sm">
            <div className="flex items-center gap-2.5 rounded-t-lg bg-blue-950 px-4 py-2.5">
                {Icon && (
                    <div className="flex size-6 items-center justify-center rounded bg-white/15">
                        <Icon className="size-3.5 text-white" />
                    </div>
                )}
                <h2 className="text-sm font-semibold uppercase tracking-wide text-white">{title}</h2>
            </div>
            <div className="p-2">{children}</div>
        </div>
    );
}

function Field({ label, required, error, children }) {
    return (
        <div>
            <Label className="mb-1 block text-xs font-medium">
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            {children}
            {error && <p className="mt-0.5 text-xs text-destructive">{error}</p>}
        </div>
    );
}

function CustomerSearch({ initialCustomer, value, onChange, error }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState(initialCustomer ?? null);
    const ref = useRef(null);
    const timerRef = useRef(null);
    const apiUrl = route('api.customers');

    async function fetchCustomers(search) {
        setLoading(true);
        try {
            const res = await fetch(`${apiUrl}?search=${encodeURIComponent(search)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            setResults(await res.json());
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }

    function handleFocus() {
        setOpen(true);
        if (results.length === 0) fetchCustomers('');
    }

    function handleChange(e) {
        const val = e.target.value;
        setQ(val);
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => fetchCustomers(val), 350);
    }

    useEffect(() => {
        function handleClick(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    function selectCustomer(customer) {
        setSelected(customer);
        onChange(String(customer.id));
        setOpen(false);
        setQ('');
    }

    function clear() {
        setSelected(null);
        onChange('');
    }

    return (
        <div ref={ref} className="relative">
            {selected ? (
                <div
                    className={`flex min-h-8 items-center justify-between rounded-md border bg-background px-3 py-1.5 text-xs shadow-xs ${error ? 'border-destructive' : 'border-input'}`}
                >
                    <span>
                        {selected.name}{' '}
                        {selected.phone && <span className="text-muted-foreground">({selected.phone})</span>}
                    </span>
                    <button type="button" onClick={clear} className="ml-2 text-muted-foreground hover:text-foreground">
                        ✕
                    </button>
                </div>
            ) : (
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={q}
                        onChange={handleChange}
                        onFocus={handleFocus}
                        placeholder="Search customer by name or phone…"
                        className={`h-8 pl-8 text-xs ${error ? 'border-destructive' : ''}`}
                    />
                </div>
            )}

            {open && !selected && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
                    {loading ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">Loading…</p>
                    ) : results.length === 0 ? (
                        <p className="px-3 py-2 text-xs text-muted-foreground">No customers found.</p>
                    ) : (
                        <ul className="max-h-52 overflow-auto">
                            {results.map((c) => (
                                <li
                                    key={c.id}
                                    className="cursor-pointer px-3 py-2 text-xs hover:bg-accent"
                                    onClick={() => selectCustomer(c)}
                                >
                                    {c.name}{' '}
                                    {c.phone && <span className="text-muted-foreground">{c.phone}</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

function ProductSearchBox({ onAdd }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedVariation, setSelectedVariation] = useState('');
    const timerRef = useRef(null);
    const ref = useRef(null);
    const apiUrl = route('api.products.sell');

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
        const unitPrice = variation ? parseFloat(variation.sale_price ?? 0) : parseFloat(product.sale_price ?? 0);
        const stock = variation ? parseFloat(variation.stock ?? 0) : parseFloat(product.stock ?? 0);
        onAdd({
            product_id: product.id,
            product_name: product.name,
            product_code: product.code,
            variation_id: variation?.id ?? null,
            variation_label: variation?.label ?? null,
            unit_price: unitPrice,
            quantity: 1,
            available_stock: stock,
        });
        setSelectedProduct(null);
        setSelectedVariation('');
    }

    return (
        <div ref={ref} className="relative">
            <div className="relative">
                <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={handleChange}
                    onFocus={handleFocus}
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
                        <ul className="max-h-56 overflow-auto">
                            {results.map((p) => (
                                <li
                                    key={p.id}
                                    className="cursor-pointer border-b border-border/50 px-3 py-2 text-xs hover:bg-accent last:border-0"
                                    onClick={() => selectProduct(p)}
                                >
                                    <span className="font-medium">{p.name}</span>
                                    {p.code && <span className="ml-2 text-muted-foreground">{p.code}</span>}
                                    {!p.has_variations && (
                                        <span className="ml-2 text-muted-foreground">Stock: {parseFloat(p.stock ?? 0)}</span>
                                    )}
                                    {p.has_variations && <span className="ml-2 text-blue-600">Has variations</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {selectedProduct?.has_variations && (
                <div className="mt-2 flex items-center gap-2 rounded-md border border-dashed border-border bg-muted/20 p-2">
                    <span className="text-xs font-medium">{selectedProduct.name}</span>
                    <Select value={selectedVariation} onValueChange={setSelectedVariation}>
                        <SelectTrigger className="h-7 w-48 text-xs">
                            <SelectValue placeholder="Select variation…" />
                        </SelectTrigger>
                        <SelectContent>
                            {selectedProduct.variations.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    {v.label} (Stock: {parseFloat(v.stock ?? 0)})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button
                        size="sm"
                        type="button"
                        disabled={!selectedVariation}
                        className="h-7 text-xs"
                        onClick={() => {
                            const v = selectedProduct.variations.find((x) => String(x.id) === selectedVariation);
                            if (v) addItem(selectedProduct, v);
                        }}
                    >
                        <Plus className="size-3.5" />
                        Add
                    </Button>
                    <Button size="sm" type="button" variant="ghost" className="h-7 text-xs" onClick={() => setSelectedProduct(null)}>
                        Cancel
                    </Button>
                </div>
            )}
        </div>
    );
}

export default function SellEdit({ sell }) {
    const form = useForm({
        customer_id: sell.customer_id ? String(sell.customer_id) : '',
        date: sell.date ?? '',
        discount: String(sell.discount ?? '0'),
        vat: String(sell.vat_percent ?? '0'),
        paid_amount: String(sell.paid_amount ?? '0'),
        comment: sell.comment ?? '',
        items: sell.items ?? [],
    });

    const [items, setItems] = useState(sell.items ?? []);

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
        form.transform((data) => ({ ...data, items }));
        form.put(route('inventory.sell.update', sell.id));
    }

    const inputCls = 'h-7 rounded-md border-border/60 text-xs px-2 focus:border-primary';

    return (
        <>
            <Head title={`Edit Sale — ${sell.invoice_number}`} />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <ShoppingCart className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Edit Sale</h1>
                            <p className="font-mono text-xs text-white/60">{sell.invoice_number}</p>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        asChild
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    >
                        <Link href={route('inventory.sell.show', sell.id)}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Card title="Sale Details" icon={CalendarDays}>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Customer" error={form.errors.customer_id}>
                                <CustomerSearch
                                    initialCustomer={sell.customer ?? null}
                                    value={form.data.customer_id}
                                    onChange={(v) => form.setData('customer_id', v)}
                                    error={form.errors.customer_id}
                                />
                                <p className="mt-0.5 text-[10px] text-muted-foreground">Leave blank for walk-in customer.</p>
                            </Field>
                            <Field label="Date" required error={form.errors.date}>
                                <Input
                                    type="date"
                                    value={form.data.date}
                                    onChange={(e) => form.setData('date', e.target.value)}
                                    className="h-8 text-xs"
                                />
                            </Field>
                        </div>
                    </Card>

                    <Card title="Products" icon={Package}>
                        <ProductSearchBox onAdd={addItem} />
                        {form.errors.items && <p className="mt-1 text-xs text-destructive">{form.errors.items}</p>}

                        {items.length > 0 && (
                            <div className="mt-4 overflow-x-auto rounded-md border border-border">
                                <table className="w-full table-auto text-xs">
                                    <thead className="bg-muted/40 text-xs uppercase tracking-wide">
                                        <tr>
                                            <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">#</th>
                                            <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">Product</th>
                                            <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Unit Price</th>
                                            <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Qty</th>
                                            <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Stock</th>
                                            <th className="whitespace-nowrap px-3 py-2 text-right font-semibold">Sub Total</th>
                                            <th className="px-2 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {items.map((item, i) => {
                                            const qty = parseFloat(item.quantity || 0);
                                            const stock = parseFloat(item.available_stock ?? 0);
                                            const remaining = stock - qty;
                                            const overStock = item.available_stock !== null && item.available_stock !== undefined && qty > stock;
                                            const subTotal = qty * parseFloat(item.unit_price || 0);
                                            return (
                                                <tr key={i} className="hover:bg-muted/20">
                                                    <td className="px-3 py-2 text-muted-foreground">{i + 1}</td>
                                                    <td className="px-3 py-2">
                                                        <p className="font-medium">{item.product_name}</p>
                                                        {item.variation_label && (
                                                            <p className="text-muted-foreground">{item.variation_label}</p>
                                                        )}
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={item.unit_price}
                                                            onChange={(e) => updateItem(i, 'unit_price', e.target.value)}
                                                            className={`${inputCls} w-full text-right`}
                                                        />
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            type="number"
                                                            min="1"
                                                            value={item.quantity}
                                                            onChange={(e) => updateItem(i, 'quantity', e.target.value)}
                                                            className={`${inputCls} w-full text-right ${overStock ? 'border-destructive' : ''}`}
                                                        />
                                                    </td>
                                                    <td className={`px-3 py-2 text-right font-medium ${overStock ? 'text-destructive' : 'text-muted-foreground'}`}>
                                                        {item.available_stock !== null && item.available_stock !== undefined ? remaining : '—'}
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
                                                            className="h-7 text-destructive hover:text-destructive"
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
                    </Card>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Card title="Comment" icon={MessageSquare}>
                            <Field label="Note" error={form.errors.comment}>
                                <textarea
                                    rows={5}
                                    value={form.data.comment}
                                    onChange={(e) => form.setData('comment', e.target.value)}
                                    placeholder="Optional note…"
                                    className="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs outline-none focus:border-primary focus:ring-[3px] focus:ring-ring/50"
                                />
                            </Field>
                        </Card>

                        <Card title="Summary" icon={HandCoins}>
                            <div className="space-y-3 text-xs">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Gross Amount</span>
                                    <span className="font-semibold">৳{grossAmount.toFixed(2)}</span>
                                </div>

                                <div className="flex items-center justify-between gap-4">
                                    <Label className="text-xs text-muted-foreground">Discount</Label>
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
                                    <Label className="text-xs text-muted-foreground">VAT %</Label>
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
                                    <Label className="text-xs text-muted-foreground">Paid Amount</Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.paid_amount}
                                        onChange={(e) => form.setData('paid_amount', e.target.value)}
                                        className={`${inputCls} w-28 text-right`}
                                    />
                                </div>
                                {form.errors.paid_amount && (
                                    <p className="text-xs text-destructive">{form.errors.paid_amount}</p>
                                )}

                                <div className="flex justify-between border-t border-border pt-2">
                                    <span className="font-semibold text-destructive">Due Amount</span>
                                    <span className="font-bold text-destructive">৳{dueAmount.toFixed(2)}</span>
                                </div>
                            </div>
                        </Card>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                            asChild
                        >
                            <Link href={route('inventory.sell.show', sell.id)}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing || items.length === 0}
                            className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/50"
                        >
                            <Save className="size-3.5" />
                            {form.processing ? 'Saving…' : 'Update Sale'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
