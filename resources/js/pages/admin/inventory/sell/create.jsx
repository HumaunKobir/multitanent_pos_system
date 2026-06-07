import { clampQuantityInput } from '@/components/inventory/inventory-form';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Barcode,
    CalendarDays,
    Check,
    Grid3x3,
    HandCoins,
    LayoutGrid,
    Minus,
    Package,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
    User,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { RequiredMark } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dateInputRightIconClassName } from '@/components/ui/date-kit';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

function Field({ label, required, error, hint, children }) {
    return (
        <div>
            <Label className="mb-1.5 block text-xs font-medium text-foreground">
                {label}
                {required && <RequiredMark />}
            </Label>
            {children}
            {hint && !error && <p className="mt-1 text-[11px] text-muted-foreground">{hint}</p>}
            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
        </div>
    );
}

function CustomerSearch({ value, onChange, error, initialCustomer }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState(initialCustomer ?? null);
    const [modalOpen, setModalOpen] = useState(false);
    const [modalData, setModalData] = useState({ name: '', phone: '', email: '', address: '' });
    const [modalErrors, setModalErrors] = useState({});
    const [modalProcessing, setModalProcessing] = useState(false);
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

    function openModal() {
        setModalData({ name: q.trim(), phone: '', email: '', address: '' });
        setModalErrors({});
        setOpen(false);
        setModalOpen(true);
    }

    function setField(field, val) {
        setModalData((prev) => ({ ...prev, [field]: val }));
        setModalErrors((prev) => ({ ...prev, [field]: undefined }));
    }

    async function handleCreate(e) {
        e.preventDefault();
        e.stopPropagation();
        setModalProcessing(true);
        setModalErrors({});
        try {
            const xsrf = decodeURIComponent(document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
            const res = await fetch(route('api.customers.store'), {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
                body: JSON.stringify(modalData),
            });
            const json = await res.json();
            if (!res.ok) { setModalErrors(json.errors ?? {}); return; }
            selectCustomer(json);
            setModalOpen(false);
        } finally {
            setModalProcessing(false);
        }
    }

    return (
        <>
            <div ref={ref} className="relative">
                {selected ? (
                    <div
                        className={cn(
                            'flex min-h-8 cursor-pointer items-center justify-between rounded-none border bg-muted/30 px-2 py-1 text-xs transition-colors hover:border-primary/40',
                            error ? 'border-destructive' : 'border-border',
                        )}
                        onClick={() => { setOpen((p) => !p); if (results.length === 0) fetchCustomers(''); }}
                    >
                        <div className="flex min-w-0 items-center gap-2">
                            <div className="flex size-7 shrink-0 items-center justify-center rounded-none bg-primary/10 text-primary">
                                <User className="size-3.5" />
                            </div>
                            <div className="min-w-0 truncate">
                                <span className="font-medium">{selected.name}</span>
                                {selected.phone && <span className="ml-1.5 text-muted-foreground">({selected.phone})</span>}
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); clear(); }}
                            className="ml-2 shrink-0 rounded-none p-1 text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                        >
                            ✕
                        </button>
                    </div>
                ) : (
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={handleChange}
                            onFocus={handleFocus}
                            placeholder="Search customer by name or phone…"
                            className={cn('h-8 rounded-none pl-8 text-xs', error && 'border-destructive')}
                        />
                    </div>
                )}

                {open && (
                    <div className="absolute z-50 mt-1.5 w-full overflow-hidden rounded-none border border-border bg-popover shadow-lg">
                        {selected && (
                            <div className="border-b border-border p-2">
                                <Input autoFocus placeholder="Search customer…" value={q} onChange={(e) => { setQ(e.target.value); handleChange(e); }} className="h-9 text-sm" />
                            </div>
                        )}
                        {loading ? (
                            <p className="px-3 py-3 text-sm text-muted-foreground">Loading…</p>
                        ) : (
                            <ul className="max-h-56 overflow-auto">
                                {results.map((c) => (
                                    <li
                                        key={c.id}
                                        className="flex cursor-pointer items-center justify-between px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                                        onClick={() => selectCustomer(c)}
                                    >
                                        <span>
                                            {c.name}{' '}
                                            {c.phone && <span className="text-muted-foreground">{c.phone}</span>}
                                        </span>
                                        {String(c.id) === String(value) && <Check className="size-4 shrink-0 text-primary" />}
                                    </li>
                                ))}
                                {q.trim() && (
                                    <li
                                        className="flex cursor-pointer items-center gap-2 border-t border-border px-3 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-accent"
                                        onClick={openModal}
                                    >
                                        <Plus className="size-4" />
                                        Add &ldquo;{q.trim()}&rdquo;
                                    </li>
                                )}
                                {!q.trim() && results.length === 0 && (
                                    <li className="px-3 py-3 text-sm text-muted-foreground">No customers found.</li>
                                )}
                            </ul>
                        )}
                    </div>
                )}
            </div>

            <Dialog open={modalOpen} onOpenChange={(o) => !o && setModalOpen(false)}>
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                    <DialogHeader className="border-b border-border bg-muted/40 px-5 py-4">
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <User className="size-4 text-primary" />
                            Add Customer
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleCreate} className="space-y-4 p-5">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Name" required error={modalErrors.name}>
                                <Input value={modalData.name} onChange={(e) => setField('name', e.target.value)} placeholder="Customer name" />
                            </Field>
                            <Field label="Phone" required error={modalErrors.phone}>
                                <Input value={modalData.phone} onChange={(e) => setField('phone', e.target.value)} placeholder="01XXXXXXXXX" />
                            </Field>
                        </div>
                        <Field label="Email" error={modalErrors.email}>
                            <Input type="email" value={modalData.email} onChange={(e) => setField('email', e.target.value)} placeholder="Email (optional)" />
                        </Field>
                        <Field label="Address" error={modalErrors.address}>
                            <Input value={modalData.address} onChange={(e) => setField('address', e.target.value)} placeholder="Address (optional)" />
                        </Field>
                        <div className="flex justify-end gap-2 border-t border-border pt-4">
                            <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={modalProcessing}>
                                {modalProcessing ? 'Saving…' : 'Create Customer'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function PosPanelHeader({ title, icon: Icon, action }) {
    return (
        <div className="flex h-9 shrink-0 items-center justify-between border-b border-blue-200 bg-slate-50 px-3">
            <div className="flex items-center gap-2 border-l-[3px] border-l-blue-950 pl-2">
                {Icon && <Icon className="size-3.5 shrink-0 text-blue-950" />}
                <h2 className="text-[11px] font-semibold uppercase tracking-wider text-blue-950">{title}</h2>
            </div>
            {action}
        </div>
    );
}

function CategoryPill({ active, label, image, onClick, isAll = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            className={cn(
                'flex w-full flex-col items-center gap-1 border p-1.5 font-medium transition-colors',
                active
                    ? 'border-blue-900 bg-blue-950 text-white'
                    : 'border-blue-200/80 bg-white text-blue-950 hover:border-blue-400 hover:bg-blue-50',
            )}
        >
            {image ? (
                <img src={image} alt="" className="size-11 shrink-0 border border-blue-100 object-cover" />
            ) : (
                <div
                    className={cn(
                        'flex size-11 shrink-0 items-center justify-center border border-blue-100',
                        active ? 'bg-blue-900 text-white' : 'bg-blue-50 text-blue-400',
                    )}
                >
                    {isAll ? <LayoutGrid className="size-5" /> : <Package className="size-5" />}
                </div>
            )}
            <span className="line-clamp-2 w-full text-center text-[10px] leading-tight">{label}</span>
        </button>
    );
}

function PosProductPicker({ categories = [], onAdd }) {
    const [query, setQuery] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [expandedProductId, setExpandedProductId] = useState(null);
    const timerRef = useRef(null);
    const apiUrl = route('api.products.sell');

    async function fetchProducts(search, category) {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            if (search.trim()) params.set('search', search.trim());
            if (category) params.set('category_id', category);

            const res = await fetch(`${apiUrl}?${params.toString()}`, {
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

    useEffect(() => {
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => fetchProducts(query, categoryId), query.trim() ? 350 : 0);

        return () => clearTimeout(timerRef.current);
    }, [query, categoryId]);

    async function triggerBarcodeSearch(term) {
        clearTimeout(timerRef.current);
        setLoading(true);
        try {
            const res = await fetch(`${apiUrl}?search=${encodeURIComponent(term)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const json = await res.json();
            const data = Array.isArray(json) ? json : [];
            const exact = data.find((p) => p.code === term);
            const match = exact ?? (data.length === 1 ? data[0] : null);
            if (match && !match.has_variations) {
                addItem(match, null);
            } else if (match && match.has_variations) {
                setResults([match]);
                setQuery('');
                setExpandedProductId(match.id);
            } else {
                setQuery(term);
                setResults(data);
            }
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }

    async function handleKeyDown(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const term = query.trim();
        if (term) triggerBarcodeSearch(term);
    }

    const triggerRef = useRef(null);
    triggerRef.current = triggerBarcodeSearch;
    const globalBufRef = useRef('');
    const globalLastKeyRef = useRef(0);

    useEffect(() => {
        function onGlobalKey(e) {
            const tag = document.activeElement?.tagName ?? '';
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;
            const now = Date.now();
            if (now - globalLastKeyRef.current > 100) globalBufRef.current = '';
            globalLastKeyRef.current = now;
            if (e.key === 'Enter') {
                const term = globalBufRef.current.trim();
                globalBufRef.current = '';
                if (term) triggerRef.current(term);
            } else if (e.key.length === 1) {
                globalBufRef.current += e.key;
            }
        }
        document.addEventListener('keydown', onGlobalKey);
        return () => document.removeEventListener('keydown', onGlobalKey);
    }, []);

    function addItem(product, variation) {
        const unitPrice = variation ? parseFloat(variation.sale_price ?? 0) : parseFloat(product.sale_price ?? 0);
        const stock = variation ? parseFloat(variation.stock ?? 0) : parseFloat(product.stock ?? 0);
        if (stock <= 0) return;

        onAdd({
            product_id: product.id,
            product_name: product.name,
            product_code: product.code,
            category_name: product.category_name ?? null,
            variation_id: variation?.id ?? null,
            variation_label: variation?.label ?? null,
            unit_price: unitPrice,
            discount: '0',
            quantity: 1,
            available_stock: stock,
        });
        setExpandedProductId(null);
        setQuery('');
    }

    function handleProductClick(product) {
        if (product.has_variations) {
            setExpandedProductId((current) => (current === product.id ? null : product.id));
            return;
        }
        addItem(product, null);
    }

    return (
        <div className="flex h-full min-h-0 gap-1">
            <nav
                aria-label="Product categories"
                className="flex w-[5.5rem] shrink-0 flex-col gap-1 overflow-y-auto border-r border-blue-100 bg-slate-50/90 p-1"
            >
                <CategoryPill isAll active={!categoryId} label="All" onClick={() => setCategoryId('')} />
                {categories.map((category) => (
                    <CategoryPill
                        key={category.id}
                        label={category.name}
                        image={category.image}
                        active={String(categoryId) === String(category.id)}
                        onClick={() => setCategoryId(String(category.id))}
                    />
                ))}
            </nav>

            <div className="flex min-w-0 flex-1 flex-col gap-2">
            <div className="relative shrink-0">
                <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-blue-900/50" />
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Search or scan barcode…"
                    className="h-9 rounded-none border-blue-200 bg-white pl-8 pr-24 text-xs focus:border-blue-600"
                />
                <div className="pointer-events-none absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1 border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-blue-900">
                    <Barcode className="size-3" />
                    Scan
                </div>
            </div>

            <div className="flex min-h-0 flex-1 flex-col border border-blue-200/80 bg-white">
                {loading ? (
                    <p className="px-3 py-6 text-center text-xs text-muted-foreground">Loading…</p>
                ) : results.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center px-4 py-6 text-center">
                        <LayoutGrid className="mb-2 size-6 text-blue-300" />
                        <p className="text-xs font-medium text-blue-950">No products</p>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">Search or pick a category</p>
                    </div>
                ) : (
                    <div className="flex flex-1 flex-col gap-px overflow-y-auto bg-blue-100/80">
                        {results.map((product) => {
                            const isExpanded = expandedProductId === product.id;
                            const outOfStock = !product.has_variations && parseFloat(product.stock ?? 0) <= 0;

                            return (
                                <div key={product.id} className="bg-white">
                                    <button
                                        type="button"
                                        disabled={outOfStock}
                                        onClick={() => handleProductClick(product)}
                                        className={cn(
                                            'flex w-full gap-2 p-2 text-left transition-colors',
                                            outOfStock
                                                ? 'cursor-not-allowed opacity-50'
                                                : 'hover:bg-blue-50/80',
                                            isExpanded && 'bg-blue-50 ring-1 ring-inset ring-blue-400',
                                        )}
                                    >
                                        {product.image ? (
                                            <img
                                                src={product.image}
                                                alt=""
                                                className="size-14 shrink-0 border border-blue-100 object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-14 shrink-0 items-center justify-center border border-blue-100 bg-slate-50">
                                                <Package className="size-6 text-blue-200" />
                                            </div>
                                        )}
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <p className="line-clamp-2 text-xs font-semibold text-blue-950">{product.name}</p>
                                            {product.category_name && (
                                                <p className="mt-0.5 text-[10px] font-medium uppercase tracking-wide text-blue-600/80">
                                                    {product.category_name}
                                                </p>
                                            )}
                                            {product.code && (
                                                <p className="mt-0.5 text-[11px] text-muted-foreground">{product.code}</p>
                                            )}
                                            <div className="mt-auto flex items-end justify-between gap-2 pt-1">
                                                <p className="text-sm font-bold tabular-nums text-emerald-700">
                                                    ৳{parseFloat(product.sale_price ?? 0).toFixed(2)}
                                                </p>
                                                {product.has_variations ? (
                                                    <Badge className="border-blue-200 bg-blue-100 text-[10px] text-blue-900 hover:bg-blue-100">
                                                        {product.variations?.length ?? 0} variants
                                                    </Badge>
                                                ) : (
                                                    <span className="text-[11px] font-medium text-muted-foreground">
                                                        Stock {parseFloat(product.stock ?? 0)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </button>

                                    {isExpanded && product.has_variations && (
                                        <div className="border-t border-blue-100 bg-blue-50/60 p-2">
                                            {(product.variations ?? []).map((variation) => {
                                                const variantStock = parseFloat(variation.stock ?? 0);
                                                return (
                                                    <button
                                                        key={variation.id}
                                                        type="button"
                                                        disabled={variantStock <= 0}
                                                        onClick={() => addItem(product, variation)}
                                                        className="mb-1 flex w-full items-center justify-between border border-blue-200 bg-white px-2 py-1.5 text-left text-xs last:mb-0 hover:border-blue-500 hover:bg-blue-50 disabled:opacity-50"
                                                    >
                                                        <span className="font-medium text-blue-950">{variation.label}</span>
                                                        <span className="tabular-nums text-emerald-700">
                                                            ৳{parseFloat(variation.sale_price ?? 0).toFixed(2)} · {variantStock}
                                                        </span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
            </div>
        </div>
    );
}

function CartEmptyState() {
    return (
        <div className="flex flex-1 flex-col items-center justify-center px-4 py-8 text-center">
            <ShoppingCart className="mb-2 size-6 text-blue-300" />
            <p className="text-xs font-medium text-blue-950">Cart is empty</p>
            <p className="mt-0.5 text-[11px] text-muted-foreground">Add products from the left panel</p>
        </div>
    );
}

function CartLineItem({ item, index, inputCls, onUpdate, onAdjust, onRemove }) {
    const qty = parseFloat(item.quantity || 0);
    const stock = parseFloat(item.available_stock ?? 0);
    const remaining = Math.max(0, stock - qty);
    const overStock = qty > stock;
    const subTotal = lineGross(item) - parseFloat(item.discount || 0);

    return (
        <div className="border-b border-blue-100 bg-white p-2 last:border-b-0">
            <div className="flex items-start gap-2">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-xs font-semibold text-blue-950">{item.product_name}</p>
                    <div className="mt-0.5 flex flex-wrap items-center gap-1">
                        {item.variation_label && (
                            <Badge variant="outline" className="border-blue-200 px-1 py-0 text-[9px] font-normal text-blue-900">
                                {item.variation_label}
                            </Badge>
                        )}
                        {item.product_code && (
                            <span className="text-[10px] text-muted-foreground">{item.product_code}</span>
                        )}
                        <span className={cn('text-[10px] tabular-nums', overStock ? 'text-destructive' : 'text-muted-foreground')}>
                            Stk {remaining}
                        </span>
                    </div>
                </div>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    onClick={() => onRemove(index)}
                    className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                >
                    <Trash2 className="size-3.5" />
                </Button>
            </div>

            <div className="mt-2 grid grid-cols-4 gap-1.5">
                <div>
                    <p className="mb-0.5 text-[9px] uppercase text-muted-foreground">Price</p>
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={item.unit_price}
                        onChange={(e) => onUpdate(index, 'unit_price', e.target.value)}
                        className={cn(inputCls, 'h-7 px-1 text-right text-xs')}
                    />
                </div>
                <div>
                    <p className="mb-0.5 text-[9px] uppercase text-muted-foreground">Qty</p>
                    <div className="flex items-center gap-0.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-7 shrink-0"
                            onClick={() => onAdjust(index, -1)}
                            disabled={qty <= 1}
                        >
                            <Minus className="size-3" />
                        </Button>
                        <Input
                            type="number"
                            min="1"
                            step="1"
                            value={item.quantity}
                            onChange={(e) =>
                                onUpdate(index, 'quantity', clampQuantityInput(e.target.value, item.available_stock))
                            }
                            className={cn(inputCls, 'h-7 w-full min-w-0 px-0.5 text-center text-xs', overStock && 'border-destructive')}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-7 shrink-0"
                            onClick={() => onAdjust(index, 1)}
                            disabled={qty >= stock}
                        >
                            <Plus className="size-3" />
                        </Button>
                    </div>
                </div>
                <div>
                    <p className="mb-0.5 text-[9px] uppercase text-muted-foreground">Disc</p>
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={item.discount ?? '0'}
                        onChange={(e) => onUpdate(index, 'discount', clampLineDiscount(e.target.value, item))}
                        className={cn(inputCls, 'h-7 px-1 text-right text-xs text-green-700')}
                    />
                </div>
                <div className="text-right">
                    <p className="mb-0.5 text-[9px] uppercase text-muted-foreground">Total</p>
                    <p className="pt-1 text-xs font-bold tabular-nums text-blue-950">৳{subTotal.toFixed(2)}</p>
                </div>
            </div>
        </div>
    );
}

function lineGross(item) {
    return parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0);
}

function clampLineDiscount(value, item) {
    if (value === '' || value === null || value === undefined) {
        return '0';
    }

    const parsed = parseFloat(value);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return '0';
    }

    return String(Math.min(parsed, lineGross(item)));
}

export default function SellCreate({ today, defaultCustomer, paymentAccounts = [], categories = [] }) {
    const form = useForm({
        customer_id: defaultCustomer ? String(defaultCustomer.id) : '',
        date: today,
        discount: '0',
        vat: '0',
        paid_amount: '0',
        payment_account_id: '',
        comment: '',
        items: [],
    });

    const [items, setItems] = useState([]);

    const grossAmount = items.reduce((sum, it) => sum + lineGross(it), 0);
    const lineDiscountTotal = items.reduce((sum, it) => sum + parseFloat(it.discount || 0), 0);
    const taxableAmount = Math.max(0, grossAmount - lineDiscountTotal);
    const vatAmount = taxableAmount * (parseFloat(form.data.vat || 0) / 100);
    const netAmount = taxableAmount + vatAmount - parseFloat(form.data.discount || 0);
    const dueAmount = Math.max(0, netAmount - parseFloat(form.data.paid_amount || 0));
    const hasOverStock = items.some((item) => parseFloat(item.quantity || 0) > parseFloat(item.available_stock ?? 0));
    const itemCount = items.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);

    function addItem(item) {
        const duplicate = items.find(
            (it) => it.product_id === item.product_id && String(it.variation_id) === String(item.variation_id),
        );
        if (duplicate) {
            setItems((prev) =>
                prev.map((it) => {
                    if (it.product_id !== item.product_id || String(it.variation_id) !== String(item.variation_id)) {
                        return it;
                    }

                    const maxStock = parseFloat(it.available_stock ?? 0);
                    const nextQty = Math.min(parseFloat(it.quantity) + 1, maxStock);

                    return { ...it, quantity: nextQty > 0 ? nextQty : it.quantity };
                }),
            );
            return;
        }
        const maxStock = parseFloat(item.available_stock ?? 0);
        if (maxStock <= 0) {
            return;
        }
        setItems((prev) => [...prev, { ...item, quantity: 1, discount: item.discount ?? '0' }]);
    }

    function updateItem(index, field, value) {
        setItems((prev) =>
            prev.map((it, i) => {
                if (i !== index) {
                    return it;
                }

                const next = { ...it, [field]: value };

                if (field === 'quantity' || field === 'unit_price' || field === 'discount') {
                    next.discount = clampLineDiscount(next.discount, next);
                }

                return next;
            }),
        );
    }

    function adjustQuantity(index, delta) {
        const item = items[index];
        if (!item) return;

        const maxStock = parseFloat(item.available_stock ?? 0);
        const current = parseFloat(item.quantity || 0);
        const next = Math.max(1, Math.min(maxStock, current + delta));

        updateItem(index, 'quantity', String(next));
    }

    function removeItem(index) {
        setItems((prev) => prev.filter((_, i) => i !== index));
    }

    function handleSubmit(e) {
        e.preventDefault();
        form.transform((data) => ({ ...data, items }));
        form.post(route('inventory.sell.store'));
    }

    const inputCls = 'h-8 rounded-none border-blue-200 bg-white text-xs tabular-nums focus:border-blue-600';

    return (
        <>
            <Head title="Point of Sale" />

            <div className="flex h-[calc(100vh-0px)] flex-col bg-slate-100">
                <header className="relative z-10 shrink-0 border-b-2 border-blue-800 bg-blue-950 px-3 py-2 shadow-md">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <div className="flex min-w-0 items-center gap-2">
                            <Button
                                size="sm"
                                asChild
                                className="h-8 shrink-0 border border-white/25 bg-white/10 px-2 text-white hover:bg-white/20"
                            >
                                <Link href={route('inventory.sell.index')}>
                                    <ArrowLeft className="size-3.5" />
                                    <span className="hidden sm:inline">Back</span>
                                </Link>
                            </Button>

                            <div className="flex size-8 shrink-0 items-center justify-center bg-white/15">
                                <ShoppingCart className="size-4 text-white" />
                            </div>

                            <div className="min-w-0">
                                <h1 className="text-sm font-semibold leading-tight text-white">Point of Sale</h1>
                                <p className="text-[10px] leading-tight text-white/55">
                                    {itemCount} {itemCount === 1 ? 'item' : 'items'} in cart
                                </p>
                            </div>
                        </div>

                        <div className="flex w-full flex-wrap items-end gap-2 sm:ml-auto sm:w-auto">
                            <div className="min-w-0 flex-1 sm:w-44 lg:w-48">
                                <span className="mb-0.5 block text-[10px] font-medium text-white/60">Customer</span>
                                <CustomerSearch
                                    value={form.data.customer_id}
                                    onChange={(v) => form.setData('customer_id', v)}
                                    error={form.errors.customer_id}
                                    initialCustomer={defaultCustomer}
                                />
                            </div>
                            <div className="shrink-0">
                                <span className="mb-0.5 block text-[10px] font-medium text-white/60">Date</span>
                                <div className="relative">
                                    <CalendarDays className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="date"
                                        value={form.data.date}
                                        onChange={(e) => form.setData('date', e.target.value)}
                                        className={cn(
                                            'h-8 w-[8.75rem] rounded-none border-blue-200 bg-white pl-7 text-xs',
                                            dateInputRightIconClassName,
                                        )}
                                    />
                                </div>
                                {form.errors.date && (
                                    <p className="mt-0.5 text-[10px] text-red-300">{form.errors.date}</p>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                <form onSubmit={handleSubmit} className="grid min-h-0 flex-1 grid-cols-1 gap-2 bg-slate-100 p-2 lg:grid-cols-12">
                    {/* Left — Products */}
                    <section className="flex min-h-0 flex-col overflow-hidden border border-blue-200 bg-white shadow-sm lg:col-span-3">
                        <PosPanelHeader title="Products" icon={Grid3x3} />
                        <div className="flex min-h-0 flex-1 flex-col p-2">
                            <PosProductPicker categories={categories} onAdd={addItem} />
                            {form.errors.items && <p className="mt-1 text-[11px] text-destructive">{form.errors.items}</p>}
                        </div>
                    </section>

                    {/* Middle — Cart */}
                    <section className="flex min-h-0 flex-col overflow-hidden border border-blue-200 bg-white shadow-sm lg:col-span-5">
                        <PosPanelHeader
                            title="Cart"
                            icon={Package}
                            action={
                                items.length > 0 ? (
                                    <span className="text-[10px] font-medium text-blue-700">{items.length} lines</span>
                                ) : null
                            }
                        />
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {items.length === 0 ? (
                                <CartEmptyState />
                            ) : (
                                items.map((item, i) => (
                                    <CartLineItem
                                        key={i}
                                        item={item}
                                        index={i}
                                        inputCls={inputCls}
                                        onUpdate={updateItem}
                                        onAdjust={adjustQuantity}
                                        onRemove={removeItem}
                                    />
                                ))
                            )}
                        </div>
                    </section>

                    {/* Right — Payment */}
                    <aside className="flex min-h-0 flex-col overflow-hidden border border-blue-200 bg-white shadow-sm lg:col-span-4">
                        <PosPanelHeader title="Checkout" icon={HandCoins} />

                        <div className="border-b border-blue-200 bg-blue-950 px-3 py-3 text-center">
                            <p className="text-[10px] font-medium uppercase tracking-wider text-white/60">Net Payable</p>
                            <p className="text-2xl font-bold tabular-nums text-white">৳{netAmount.toFixed(2)}</p>
                            {dueAmount > 0 && (
                                <p className="mt-0.5 text-xs font-medium text-red-300">Due ৳{dueAmount.toFixed(2)}</p>
                            )}
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto p-2.5">
                            <div className="space-y-2 text-xs">
                                <div className="flex items-center justify-between gap-2 border border-blue-100 bg-blue-50/50 px-2 py-1.5">
                                    <span className="text-muted-foreground">Gross</span>
                                    <span className="font-medium tabular-nums">৳{grossAmount.toFixed(2)}</span>
                                </div>

                                {lineDiscountTotal > 0 && (
                                    <div className="flex items-center justify-between gap-2 border border-green-100 bg-green-50/50 px-2 py-1.5">
                                        <span className="text-green-800">Line Disc.</span>
                                        <span className="font-medium tabular-nums text-green-700">-৳{lineDiscountTotal.toFixed(2)}</span>
                                    </div>
                                )}

                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label className="mb-0.5 block text-[10px] text-muted-foreground">Inv. Discount</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.data.discount}
                                            onChange={(e) => form.setData('discount', e.target.value)}
                                            className={cn(inputCls, 'text-right')}
                                        />
                                    </div>
                                    <div>
                                        <Label className="mb-0.5 block text-[10px] text-muted-foreground">VAT %</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.data.vat}
                                            onChange={(e) => form.setData('vat', e.target.value)}
                                            className={cn(inputCls, 'text-right')}
                                        />
                                    </div>
                                </div>

                                {parseFloat(form.data.vat || 0) > 0 && (
                                    <div className="flex items-center justify-between gap-2 px-1">
                                        <span className="text-muted-foreground">VAT Amount</span>
                                        <span className="font-medium tabular-nums">৳{vatAmount.toFixed(2)}</span>
                                    </div>
                                )}

                                <div>
                                    <Label className="mb-0.5 block text-[10px] text-muted-foreground">Paid Amount</Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.paid_amount}
                                        onChange={(e) => form.setData('paid_amount', e.target.value)}
                                        className={cn(inputCls, 'text-right font-semibold')}
                                    />
                                    {form.errors.paid_amount && (
                                        <p className="mt-0.5 text-[10px] text-destructive">{form.errors.paid_amount}</p>
                                    )}
                                </div>

                                {parseFloat(form.data.paid_amount || 0) > 0 && (
                                    <div>
                                        <Label className="mb-0.5 block text-[10px] text-muted-foreground">Payment Account</Label>
                                        <select
                                            className="h-8 w-full rounded-none border border-blue-200 bg-white px-2 text-xs outline-none focus:border-blue-600"
                                            value={form.data.payment_account_id}
                                            onChange={(e) => form.setData('payment_account_id', e.target.value)}
                                        >
                                            <option value="">Cash / bank</option>
                                            {paymentAccounts.map((acc) => (
                                                <option key={acc.id} value={String(acc.id)}>
                                                    {acc.label}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.payment_account_id && (
                                            <p className="mt-0.5 text-[10px] text-destructive">{form.errors.payment_account_id}</p>
                                        )}
                                    </div>
                                )}

                                <div className="sm:hidden">
                                    <Label className="mb-0.5 block text-[10px] text-muted-foreground">Customer</Label>
                                    <CustomerSearch
                                        value={form.data.customer_id}
                                        onChange={(v) => form.setData('customer_id', v)}
                                        error={form.errors.customer_id}
                                        initialCustomer={defaultCustomer}
                                    />
                                </div>

                                <div>
                                    <Label className="mb-0.5 block text-[10px] text-muted-foreground">Note</Label>
                                    <Textarea
                                        rows={2}
                                        value={form.data.comment}
                                        onChange={(e) => form.setData('comment', e.target.value)}
                                        placeholder="Optional note…"
                                        className="min-h-0 resize-none text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="shrink-0 border-t border-blue-200 bg-slate-50 p-2.5">
                            {hasOverStock && (
                                <p className="mb-2 border border-destructive/30 bg-destructive/5 px-2 py-1 text-[10px] text-destructive">
                                    Stock exceeded — adjust quantities.
                                </p>
                            )}

                            <div className="grid grid-cols-2 gap-1.5">
                                <Button type="button" variant="outline" size="sm" className="h-8 border-red-400 text-xs text-red-600 hover:bg-red-50" asChild>
                                    <Link href={route('inventory.sell.index')}>Cancel</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={form.processing || items.length === 0 || hasOverStock}
                                    className="h-8 bg-emerald-600 text-xs text-white hover:bg-emerald-600/90"
                                >
                                    {form.processing ? 'Saving…' : 'Complete Sale'}
                                </Button>
                            </div>
                        </div>
                    </aside>
                </form>
            </div>
        </>
    );
}
