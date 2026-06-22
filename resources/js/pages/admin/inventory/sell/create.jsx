import { clampQuantityInput } from '@/components/inventory/inventory-form';
import {
    computeDiscountAmount,
    findBestSpecialDiscount,
    formatDiscountLabel,
} from '@/lib/pos-discount';
import { SalePaymentLines } from '@/components/inventory/sale-payment-lines';
import { SellDueAlertFields } from '@/components/inventory/sell-due-alert-fields';
import {
    buildInitialSalePayments,
    computeSplitSalePayment,
    dueSaleCustomerError,
    serializeSalePayments,
    splitPaymentValidationError,
} from '@/lib/sale-payment';
import { useAppToast } from '@/contexts/app-toast-context';
import { customerModalDefaultsFromSearch } from '@/lib/customer-modal-defaults';
import { route } from '@/lib/route';
import { hasRichTextContent } from '@/lib/pos-print';
import { cn } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
    Pause,
    Play,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
    User,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

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

function CustomerSearch({ value, onChange, error, initialCustomer, variant = 'default' }) {
    const onDarkHeader = variant === 'header';
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
        setModalData(customerModalDefaultsFromSearch(q));
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
                            'flex min-h-8 cursor-pointer items-center justify-between rounded-none border px-2 py-1 text-xs transition-colors',
                            onDarkHeader
                                ? 'border-blue-200 bg-white text-blue-950 hover:border-blue-400'
                                : 'border-border bg-muted/30 hover:border-primary/40',
                            error && 'border-destructive',
                        )}
                        onClick={() => { setOpen((p) => !p); if (results.length === 0) fetchCustomers(''); }}
                    >
                        <div className="flex min-w-0 items-center gap-2">
                            <div
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-none',
                                    onDarkHeader ? 'bg-blue-100 text-blue-700' : 'bg-primary/10 text-primary',
                                )}
                            >
                                <User className="size-3.5" />
                            </div>
                            <div className="min-w-0 truncate">
                                <span className={cn('font-medium', onDarkHeader ? 'text-blue-950' : 'text-foreground')}>{selected.name}</span>
                                {selected.phone && (
                                    <span className={cn('ml-1.5', onDarkHeader ? 'text-slate-500' : 'text-muted-foreground')}>
                                        ({selected.phone})
                                    </span>
                                )}
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); clear(); }}
                            className={cn(
                                'ml-2 shrink-0 rounded-none p-1 transition-colors',
                                onDarkHeader
                                    ? 'text-slate-500 hover:bg-slate-100 hover:text-slate-800'
                                    : 'text-muted-foreground hover:bg-background hover:text-foreground',
                            )}
                        >
                            ✕
                        </button>
                    </div>
                ) : (
                    <div className="relative">
                        <Search className={cn('absolute top-1/2 left-3 size-4 -translate-y-1/2', onDarkHeader ? 'text-slate-400' : 'text-muted-foreground')} />
                        <Input
                            value={q}
                            onChange={handleChange}
                            onFocus={handleFocus}
                            placeholder="Search customer by name or phone…"
                            className={cn(
                                'h-8 rounded-none pl-8 text-xs',
                                onDarkHeader && 'border-blue-200 bg-white text-slate-900 placeholder:text-slate-400',
                                error && 'border-destructive',
                            )}
                        />
                    </div>
                )}

                {open && (
                    <div className="absolute z-50 mt-1.5 w-full overflow-hidden rounded-none border border-border bg-popover text-popover-foreground shadow-lg">
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
                    <DialogHeader className="shrink-0 border-b border-border bg-muted/40 px-5 py-4">
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <User className="size-4 text-primary" />
                            Add Customer
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleCreate} className="space-y-4 p-5">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Name" error={modalErrors.name}>
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
        <div className="flex h-7 shrink-0 items-center justify-between border-b border-blue-200 bg-slate-50 px-1.5 lg:h-8 lg:px-2 2xl:h-9 2xl:px-3">
            <div className="flex items-center gap-1 border-l-2 border-l-blue-950 pl-1.5 lg:border-l-[3px] lg:gap-1.5 lg:pl-2 2xl:gap-2">
                {Icon && <Icon className="size-3 shrink-0 text-blue-950 lg:size-3.5" />}
                <h2 className="text-[9px] font-semibold uppercase tracking-wider text-blue-950 lg:text-[10px] 2xl:text-[11px]">{title}</h2>
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
                'flex shrink-0 flex-row items-center gap-1 border p-1 text-left font-medium transition-colors md:w-full md:flex-col md:items-center md:gap-0.5 md:text-center lg:p-1 2xl:gap-1 2xl:p-1.5',
                active
                    ? 'border-blue-900 bg-blue-950 text-white'
                    : 'border-blue-200/80 bg-white text-blue-950 hover:border-blue-400 hover:bg-blue-50',
            )}
        >
            {image ? (
                <img src={image} alt="" className="size-7 shrink-0 border border-blue-100 object-cover md:size-9 lg:size-9 2xl:size-11" />
            ) : (
                <div
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center border border-blue-100 md:size-9 lg:size-9 2xl:size-11',
                        active ? 'bg-blue-900 text-white' : 'bg-blue-50 text-blue-400',
                    )}
                >
                    {isAll ? <LayoutGrid className="size-3.5 lg:size-4 2xl:size-5" /> : <Package className="size-3.5 lg:size-4 2xl:size-5" />}
                </div>
            )}
            <span className="line-clamp-1 text-[9px] leading-tight md:line-clamp-2 md:w-full lg:text-[9px] 2xl:text-[10px]">{label}</span>
        </button>
    );
}

function PosProductPicker({ categories = [], onAdd }) {
    const [query, setQuery] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
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
        setQuery('');
    }


    return (
        <div className="flex h-full min-h-0 flex-col gap-1 md:flex-row">
            <nav
                aria-label="Product categories"
                className="flex gap-1 overflow-x-auto border-b border-blue-100 bg-slate-50/90 p-0.5 md:w-[4.5rem] md:shrink-0 md:flex-col md:overflow-y-auto md:border-b-0 md:border-r md:p-1 2xl:w-[5.5rem]"
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

            <div className="flex min-w-0 flex-1 flex-col gap-1 lg:gap-1.5 2xl:gap-2">
            <div className="relative shrink-0">
                <Search className="absolute top-1/2 left-2 size-3 -translate-y-1/2 text-blue-900/50 lg:left-2.5 lg:size-3.5" />
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Search or scan barcode…"
                    className="h-7 rounded-none border-blue-200 bg-white pl-7 pr-20 text-[11px] focus:border-blue-600 lg:h-9 lg:pl-8 lg:pr-24 lg:text-xs"
                />
                <div className="pointer-events-none absolute top-1/2 right-1.5 flex -translate-y-1/2 items-center gap-0.5 border border-blue-200 bg-blue-50 px-1 py-0.5 text-[8px] font-semibold uppercase tracking-wide text-blue-900 lg:right-2 lg:gap-1 lg:px-1.5 lg:text-[9px]">
                    <Barcode className="size-2.5 lg:size-3" />
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
                        {results.flatMap((product) =>
                            product.has_variations
                                ? (product.variations ?? []).map((variation) => ({ product, variation }))
                                : [{ product, variation: null }],
                        ).sort((a, b) => {
                            const stockA = a.variation ? parseFloat(a.variation.stock ?? 0) : parseFloat(a.product.stock ?? 0);
                            const stockB = b.variation ? parseFloat(b.variation.stock ?? 0) : parseFloat(b.product.stock ?? 0);
                            return (stockB > 0 ? 1 : 0) - (stockA > 0 ? 1 : 0);
                        }).map(({ product, variation }) => {
                            const isVariant = variation !== null;
                            const itemKey = isVariant ? `v-${variation.id}` : `p-${product.id}`;
                            const stock = isVariant ? parseFloat(variation.stock ?? 0) : parseFloat(product.stock ?? 0);
                            const price = isVariant ? parseFloat(variation.sale_price ?? 0) : parseFloat(product.sale_price ?? 0);
                            const outOfStock = stock <= 0;

                            return (
                                <button
                                    key={itemKey}
                                    type="button"
                                    disabled={outOfStock}
                                    onClick={() => addItem(product, variation)}
                                    className={cn(
                                        'flex w-full gap-1.5 bg-white p-1.5 text-left transition-colors lg:gap-2 lg:p-2',
                                        outOfStock ? 'cursor-not-allowed opacity-50' : 'hover:bg-blue-50/80',
                                    )}
                                >
                                    {product.image ? (
                                        <img
                                            src={product.image}
                                            alt=""
                                            className="size-10 shrink-0 border border-blue-100 object-cover lg:size-14"
                                        />
                                    ) : (
                                        <div className="flex size-10 shrink-0 items-center justify-center border border-blue-100 bg-slate-50 lg:size-14">
                                            <Package className="size-4 text-blue-200 lg:size-6" />
                                        </div>
                                    )}
                                    <div className="flex min-w-0 flex-1 flex-col">
                                        <p className="line-clamp-1 text-[11px] font-semibold text-blue-950 lg:line-clamp-2 lg:text-xs">{product.name}</p>
                                        {isVariant ? (
                                            <p className="mt-0.5 text-[10px] font-medium text-blue-700 lg:text-[11px]">{variation.label}</p>
                                        ) : (
                                            product.category_name && (
                                                <p className="mt-0.5 text-[9px] font-medium uppercase tracking-wide text-blue-600/80 lg:text-[10px]">
                                                    {product.category_name}
                                                </p>
                                            )
                                        )}
                                        {!isVariant && product.code && (
                                            <p className="mt-0.5 text-[10px] text-muted-foreground lg:text-[11px]">{product.code}</p>
                                        )}
                                        <div className="mt-auto flex items-end justify-between gap-1 pt-0.5 lg:gap-2 lg:pt-1">
                                            <p className="text-xs font-bold tabular-nums text-emerald-700 lg:text-sm">
                                                ৳{price.toFixed(2)}
                                            </p>
                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                Stock {stock}
                                            </span>
                                        </div>
                                    </div>
                                </button>
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
        <div className="border-b border-blue-100 bg-white p-1.5 lg:p-2 last:border-b-0">
            <div className="flex items-start gap-1.5 lg:gap-2">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-[11px] font-semibold text-blue-950 lg:text-xs">{item.product_name}</p>
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
                    className="size-6 shrink-0 text-blue-400 hover:text-destructive dark:text-blue-400 dark:hover:bg-red-50 dark:hover:text-destructive lg:size-7"
                >
                    <Trash2 className="size-3 lg:size-3.5" />
                </Button>
            </div>

            <div className="mt-1.5 grid grid-cols-2 gap-x-1.5 gap-y-1 sm:grid-cols-4 sm:gap-1 sm:gap-y-0 lg:mt-2 lg:gap-1.5">
                <div>
                    <p className="mb-0.5 text-[9px] uppercase text-muted-foreground">Price</p>
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={item.unit_price ?? ''}
                        onChange={(e) => onUpdate(index, 'unit_price', e.target.value)}
                        className={cn(inputCls, 'h-5 px-0.5 text-right text-[11px] sm:h-6 sm:px-1 lg:h-7')}
                    />
                </div>
                <div>
                    <p className="mb-0.5 text-[9px] uppercase text-muted-foreground">Qty</p>
                    <div className="flex items-center gap-0.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-5 shrink-0 sm:size-6 lg:size-7 dark:bg-white dark:border-blue-200 dark:text-blue-950 dark:hover:bg-blue-50"
                            onClick={() => onAdjust(index, -1)}
                            disabled={qty <= 1}
                        >
                            <Minus className="size-2.5 lg:size-3" />
                        </Button>
                        <Input
                            type="number"
                            min="1"
                            step="1"
                            value={item.quantity ?? ''}
                            onChange={(e) =>
                                onUpdate(index, 'quantity', clampQuantityInput(e.target.value, item.available_stock))
                            }
                            className={cn(inputCls, 'h-5 w-full min-w-0 px-0.5 text-center text-[11px] sm:h-6 lg:h-7', overStock && 'border-destructive')}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-5 shrink-0 sm:size-6 lg:size-7 dark:bg-white dark:border-blue-200 dark:text-blue-950 dark:hover:bg-blue-50"
                            onClick={() => onAdjust(index, 1)}
                            disabled={qty >= stock}
                        >
                            <Plus className="size-2.5 lg:size-3" />
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
                        onBlur={(e) => {
                            if (e.target.value === '') onUpdate(index, 'discount', '0');
                        }}
                        className={cn(inputCls, 'h-5 px-0.5 text-right text-[11px] text-green-700 sm:h-6 sm:px-1 lg:h-7')}
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

function PausedSalesPanel({ pausedSales = [], currentPausedId, onResume }) {
    const [open, setOpen] = useState(false);

    if (pausedSales.length === 0) {
        return null;
    }

    return (
        <div className="relative">
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setOpen((value) => !value)}
                className="h-8 border-amber-300/60 bg-amber-500/15 px-2.5 text-xs text-amber-100 hover:bg-amber-500/25 hover:text-white"
            >
                <Pause className="size-3.5" />
                Paused ({pausedSales.length})
            </Button>

            {open && (
                <div className="absolute right-0 z-50 mt-1.5 w-80 overflow-hidden rounded-none border border-border bg-popover shadow-lg">
                    <div className="border-b border-border bg-muted/40 px-3 py-2">
                        <p className="text-xs font-semibold uppercase tracking-wide text-foreground">Paused Sales</p>
                    </div>
                    <ul className="max-h-72 overflow-auto">
                        {pausedSales.map((sale) => (
                            <li
                                key={sale.id}
                                className={cn(
                                    'flex items-start justify-between gap-2 border-b border-border px-3 py-2.5 last:border-b-0',
                                    String(currentPausedId) === String(sale.id) && 'bg-amber-50 dark:bg-amber-950/30',
                                )}
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{sale.customer_name}</p>
                                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                                        {sale.item_count} item(s) · ৳{parseFloat(sale.net_amount ?? 0).toFixed(2)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-7 px-2 text-[11px]"
                                        onClick={() => {
                                            setOpen(false);
                                            onResume(sale.id);
                                        }}
                                    >
                                        <Play className="size-3" />
                                        Resume
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-7 px-2 text-[11px] text-destructive hover:text-destructive"
                                        onClick={() => {
                                            if (!window.confirm('Remove this paused sale?')) {
                                                return;
                                            }
                                            router.delete(route('inventory.sell.destroy', sale.id), {
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <Trash2 className="size-3" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

function clampLineDiscount(value, item) {
    if (value === '' || value === null || value === undefined) {
        return '';
    }

    const parsed = parseFloat(value);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return '0';
    }

    return String(Math.min(parsed, lineGross(item)));
}

function buildInitialFormData({ today, defaultCustomer, resumedSell, paymentAccounts }) {
    if (resumedSell) {
        return {
            customer_id: resumedSell.customer_id ? String(resumedSell.customer_id) : '',
            date: resumedSell.date ?? today,
            discount_type: resumedSell.discount_type ?? 'flat',
            discount_value: resumedSell.discount_value ?? '0',
            special_discount_id: resumedSell.special_discount_id ? String(resumedSell.special_discount_id) : '',
            vat: resumedSell.vat_percent ?? '0',
            paid_amount: resumedSell.paid_amount ?? '0',
            payments: buildInitialSalePayments([], paymentAccounts),
            comment: resumedSell.comment ?? '',
            due_given_date: '',
            due_alert_action: '',
            items: [],
        };
    }

    return {
        customer_id: defaultCustomer ? String(defaultCustomer.id) : '',
        date: today,
        discount_type: 'flat',
        discount_value: '0',
        special_discount_id: '',
        vat: '0',
        paid_amount: '0',
        payments: buildInitialSalePayments([], paymentAccounts),
        comment: '',
        due_given_date: '',
        due_alert_action: '',
        items: [],
    };
}

export default function SellCreate({
    today,
    defaultCustomer,
    paymentAccounts = [],
    categories = [],
    specialDiscounts = [],
    discountTypes = [],
    pausedSales = [],
    resumedSell = null,
    posTerms = null,
}) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const initialCustomer = resumedSell?.customer ?? defaultCustomer;

    const form = useForm(buildInitialFormData({ today, defaultCustomer, resumedSell, paymentAccounts }));

    const [pausedSellId, setPausedSellId] = useState(resumedSell?.id ?? null);
    const [items, setItems] = useState(resumedSell?.items ?? []);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    const grossAmount = items.reduce((sum, it) => sum + lineGross(it), 0);
    const lineDiscountTotal = items.reduce((sum, it) => sum + parseFloat(it.discount || 0), 0);
    const taxableAmount = Math.max(0, grossAmount - lineDiscountTotal);
    const matchedSpecialDiscount = useMemo(
        () => findBestSpecialDiscount(specialDiscounts, taxableAmount),
        [specialDiscounts, taxableAmount],
    );
    const vatAmount = taxableAmount * (parseFloat(form.data.vat || 0) / 100);
    const invoiceDiscountAmount = computeDiscountAmount(
        form.data.discount_type,
        form.data.discount_value,
        taxableAmount,
    );
    const specialDiscountAmount = matchedSpecialDiscount
        ? computeDiscountAmount(
              matchedSpecialDiscount.discount_type,
              matchedSpecialDiscount.discount_value,
              taxableAmount,
          )
        : 0;
    const netAmount = taxableAmount + vatAmount - invoiceDiscountAmount - specialDiscountAmount;

    const { totalPaid, dueAmount, changeAmount } = computeSplitSalePayment(form.data.payments, netAmount);
    const dueCustomerError = dueSaleCustomerError(form.data.customer_id, defaultCustomer?.id ?? null, dueAmount);
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

        if (dueCustomerError) {
            toast.error(dueCustomerError);
            return;
        }

        const paymentError = splitPaymentValidationError(form.data.payments);
        if (paymentError) {
            toast.error(paymentError);
            return;
        }

        const serializedPayments = serializeSalePayments(form.data.payments);

        form.transform((data) => ({
            ...data,
            items,
            paused_sell_id: pausedSellId ?? '',
            paid_amount: String(totalPaid),
            special_discount_id: matchedSpecialDiscount ? String(matchedSpecialDiscount.id) : '',
            payments: serializedPayments.length > 0 ? serializedPayments : undefined,
        }));
        form.post(route('inventory.sell.store'));
    }

    function handlePause(e) {
        e.preventDefault();
        router.post(
            route('inventory.sell.pause'),
            {
                ...form.data,
                items,
                paused_sell_id: pausedSellId ?? '',
                paid_amount: '0',
                special_discount_id: matchedSpecialDiscount ? String(matchedSpecialDiscount.id) : '',
            },
            {
                onSuccess: () => {
                    setItems([]);
                    setPausedSellId(null);
                    form.reset();
                },
            },
        );
    }

    function resumePausedSale(saleId) {
        router.get(route('inventory.sell.create'), { paused: saleId });
    }

    const inputCls = 'h-7 rounded-none border-blue-200 bg-white text-[11px] tabular-nums text-blue-950 focus:border-blue-600 lg:h-8 lg:text-xs';

    return (
        <>
            <Head title="Point of Sale" />

            <div className="flex min-h-screen flex-col bg-slate-100 md:h-dvh">
                <header className="relative z-10 shrink-0 border-b-2 border-blue-800 bg-blue-950 px-2 py-1 lg:px-3 lg:py-2 2xl:px-3 2xl:py-2 shadow-md">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 lg:gap-x-3 2xl:gap-y-2">
                        <div className="flex min-w-0 items-center gap-1.5 lg:gap-2">
                            <Button
                                size="sm"
                                asChild
                                className="h-7 shrink-0 border border-white/25 bg-white/10 px-1.5 text-white hover:bg-white/20 lg:h-8 lg:px-2"
                            >
                                <Link href={route('inventory.sell.index')}>
                                    <ArrowLeft className="size-3.5" />
                                    <span className="hidden lg:inline">Back</span>
                                </Link>
                            </Button>

                            <div className="flex size-7 shrink-0 items-center justify-center bg-white/15 lg:size-8">
                                <ShoppingCart className="size-3.5 text-white lg:size-4" />
                            </div>

                            <div className="min-w-0">
                                <h1 className="text-xs font-semibold leading-tight text-white lg:text-sm">Point of Sale</h1>
                                <p className="text-[9px] leading-tight text-white/55 lg:text-[10px]">
                                    {itemCount} {itemCount === 1 ? 'item' : 'items'} in cart
                                    {pausedSellId ? ' · Resuming paused sale' : ''}
                                </p>
                            </div>
                        </div>

                        <div className="flex w-full flex-wrap items-end gap-1.5 sm:ml-auto sm:w-auto lg:gap-2">
                            <PausedSalesPanel
                                pausedSales={pausedSales}
                                currentPausedId={pausedSellId}
                                onResume={resumePausedSale}
                            />
                            <div className="min-w-0 min-w-[120px] flex-1 sm:min-w-0 sm:w-36 lg:w-44 2xl:w-48">
                                <span className="mb-0.5 block text-[9px] font-medium text-white/60 lg:text-[10px]">Customer</span>
                                <CustomerSearch
                                    value={form.data.customer_id}
                                    onChange={(v) => form.setData('customer_id', v)}
                                    error={form.errors.customer_id}
                                    initialCustomer={initialCustomer}
                                    variant="header"
                                />
                            </div>
                            <div className="shrink-0">
                                <span className="mb-0.5 block text-[9px] font-medium text-white/60 lg:text-[10px]">Date</span>
                                <div className="relative">
                                    <CalendarDays className="pointer-events-none absolute top-1/2 left-2 size-3 -translate-y-1/2 text-muted-foreground lg:size-3.5" />
                                    <Input
                                        type="date"
                                        value={form.data.date}
                                        onChange={(e) => form.setData('date', e.target.value)}
                                        className={cn(
                                            'h-7 w-[7rem] rounded-none border-blue-200 bg-white pl-6 text-[11px] text-blue-950 lg:h-8 lg:w-[8.75rem] lg:pl-7 lg:text-xs',
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

                <form onSubmit={handleSubmit} className="grid min-h-0 flex-1 grid-cols-1 gap-1 bg-slate-100 p-1 md:grid-cols-5 md:grid-rows-[1fr_auto] md:gap-1.5 md:p-1.5 lg:grid-cols-11 lg:grid-rows-1 lg:gap-1.5 lg:p-1.5 2xl:grid-cols-12 2xl:gap-2 2xl:p-2">
                    {/* Left — Products */}
                    <section className="pos-panel flex h-[50vh] flex-col overflow-hidden border border-blue-200 bg-white text-blue-950 shadow-sm md:h-auto md:min-h-0 md:col-span-2 md:row-span-2 lg:col-span-3 lg:row-span-1 2xl:col-span-3">
                        <PosPanelHeader title="Products" icon={Grid3x3} />
                        <div className="flex min-h-0 flex-1 flex-col p-1 lg:p-1.5 2xl:p-2">
                            <PosProductPicker categories={categories} onAdd={addItem} />
                            {form.errors.items && <p className="mt-1 text-[11px] text-destructive">{form.errors.items}</p>}
                        </div>
                    </section>

                    {/* Middle — Cart */}
                    <section className="pos-panel flex flex-col border border-blue-200 bg-white text-blue-950 shadow-sm md:min-h-0 md:overflow-hidden md:col-span-3 lg:col-span-4 2xl:col-span-5">
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
                    <aside className="pos-panel flex flex-col border border-blue-200 bg-white text-blue-950 shadow-sm md:min-h-0 md:overflow-hidden md:col-span-3 md:col-start-3 lg:col-span-4 lg:col-start-auto 2xl:col-span-4">
                        <PosPanelHeader title="Checkout" icon={HandCoins} />

                        <div className="border-b border-blue-200 bg-blue-950 px-2 py-2.5 lg:px-3 lg:py-3">
                            <div className="grid grid-cols-3 divide-x divide-white/15">
                                <div className="px-1.5 text-center lg:px-2">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-white/60 lg:text-xs">
                                        Net Payable
                                    </p>
                                    <p className="mt-1 text-base font-bold tabular-nums text-white lg:text-lg 2xl:text-xl">
                                        ৳{netAmount.toFixed(2)}
                                    </p>
                                </div>
                                <div className="px-1.5 text-center lg:px-2">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-white/60 lg:text-xs">
                                        Amount
                                    </p>
                                    <p className="mt-1 text-base font-bold tabular-nums text-emerald-300 lg:text-lg 2xl:text-xl">
                                        ৳{totalPaid.toFixed(2)}
                                    </p>
                                </div>
                                <div className="px-1.5 text-center lg:px-2">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-white/60 lg:text-xs">
                                        {changeAmount > 0 ? 'Change' : 'Due Amount'}
                                    </p>
                                    <p
                                        className={cn(
                                            'mt-1 text-base font-bold tabular-nums lg:text-lg 2xl:text-xl',
                                            changeAmount > 0
                                                ? 'text-amber-300'
                                                : dueAmount > 0
                                                  ? 'text-red-300'
                                                  : 'text-white/50',
                                        )}
                                    >
                                        ৳{(changeAmount > 0 ? changeAmount : dueAmount).toFixed(2)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto p-1.5 lg:p-2 2xl:p-2.5">
                            <div className="space-y-1.5 text-[11px] lg:space-y-2 lg:text-xs">
                                <div className="flex items-center justify-between gap-1 border border-blue-100 bg-blue-50/50 px-1.5 py-1.5 lg:gap-2 lg:px-2 lg:py-2">
                                    <span className="text-sm font-medium text-muted-foreground lg:text-base">Gross</span>
                                    <span className="text-sm font-semibold tabular-nums lg:text-base">৳{grossAmount.toFixed(2)}</span>
                                </div>

                                {lineDiscountTotal > 0 && (
                                    <div className="flex items-center justify-between gap-1 border border-green-100 bg-green-50/50 px-1.5 py-1 lg:gap-2 lg:px-2 lg:py-1.5">
                                        <span className="text-green-800">Line Disc.</span>
                                        <span className="font-medium tabular-nums text-green-700">-৳{lineDiscountTotal.toFixed(2)}</span>
                                    </div>
                                )}

                                {matchedSpecialDiscount && specialDiscountAmount > 0 && (
                                    <div className="border border-amber-200 bg-amber-50/80 px-1.5 py-1 lg:px-2 lg:py-1.5">
                                        <div className="flex items-center justify-between gap-1 lg:gap-2">
                                            <span className="text-amber-900">Special: {matchedSpecialDiscount.name}</span>
                                            <span className="font-medium tabular-nums text-amber-800">
                                                -৳{specialDiscountAmount.toFixed(2)}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-[10px] text-amber-700/80">
                                            {formatDiscountLabel(
                                                matchedSpecialDiscount.discount_type,
                                                matchedSpecialDiscount.discount_value,
                                            )}{' '}
                                            applied automatically
                                        </p>
                                    </div>
                                )}

                                <div className="grid grid-cols-3 gap-1 lg:gap-2">
                                    <div>
                                        <Label className="mb-0.5 block text-[9px] text-muted-foreground lg:text-[10px]">Inv. Disc. Type</Label>
                                        <select
                                            className="h-7 w-full rounded-none border border-blue-200 bg-white px-1 text-[11px] text-blue-950 outline-none focus:border-blue-600 lg:h-8 lg:px-2 lg:text-xs"
                                            value={form.data.discount_type}
                                            onChange={(e) => form.setData('discount_type', e.target.value)}
                                        >
                                            {discountTypes.map((type) => (
                                                <option key={type.value} value={type.value}>
                                                    {type.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <Label className="mb-0.5 block text-[9px] text-muted-foreground lg:text-[10px]">Inv. Discount</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.data.discount_value}
                                            onChange={(e) => form.setData('discount_value', e.target.value)}
                                            className={cn(inputCls, 'text-right')}
                                        />
                                    </div>
                                    <div>
                                        <Label className="mb-0.5 block text-[9px] text-muted-foreground lg:text-[10px]">VAT %</Label>
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

                                {invoiceDiscountAmount > 0 && (
                                    <div className="flex items-center justify-between gap-2 px-1">
                                        <span className="text-muted-foreground">Inv. Disc. Amount</span>
                                        <span className="font-medium tabular-nums text-green-700">
                                            -৳{invoiceDiscountAmount.toFixed(2)}
                                        </span>
                                    </div>
                                )}

                                {parseFloat(form.data.vat || 0) > 0 && (
                                    <div className="flex items-center justify-between gap-2 px-1">
                                        <span className="text-muted-foreground">VAT Amount</span>
                                        <span className="font-medium tabular-nums">৳{vatAmount.toFixed(2)}</span>
                                    </div>
                                )}

                                <SalePaymentLines
                                    payments={form.data.payments}
                                    paymentAccounts={paymentAccounts}
                                    netAmount={netAmount}
                                    onChange={(payments) => form.setData('payments', payments)}
                                    errors={form.errors}
                                    inputClassName={inputCls}
                                    compact
                                    hideSummary
                                />

                                <SellDueAlertFields
                                    customerId={form.data.customer_id}
                                    walkInCustomerId={defaultCustomer?.id ?? null}
                                    dueAmount={dueAmount}
                                    form={form}
                                />

                                <div className="sm:hidden">
                                    <Label className="mb-0.5 block text-[9px] text-muted-foreground lg:text-[10px]">Customer</Label>
                                    <CustomerSearch
                                        value={form.data.customer_id}
                                        onChange={(v) => form.setData('customer_id', v)}
                                        error={form.errors.customer_id}
                                        initialCustomer={initialCustomer}
                                    />
                                </div>

                                <div>
                                    <Label className="mb-0.5 block text-[9px] text-muted-foreground lg:text-[10px]">Note</Label>
                                    <Textarea
                                        rows={2}
                                        value={form.data.comment}
                                        onChange={(e) => form.setData('comment', e.target.value)}
                                        placeholder="Optional note…"
                                        className="min-h-0 resize-none text-[11px] lg:text-xs"
                                    />
                                </div>

                                {hasRichTextContent(posTerms) && (
                                    <div className="border border-blue-200 bg-white px-2 py-1.5">
                                        <p className="mb-1 text-[9px] font-semibold uppercase tracking-wide text-blue-950 lg:text-[10px]">
                                            Terms & Conditions
                                        </p>
                                        <div
                                            className="prose prose-sm max-h-28 max-w-none overflow-y-auto text-[10px] leading-snug text-muted-foreground prose-p:my-1 prose-ul:my-1 prose-ol:my-1 lg:max-h-36 lg:text-[11px]"
                                            dangerouslySetInnerHTML={{ __html: posTerms }}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="shrink-0 border-t border-blue-200 bg-slate-50 p-1.5 lg:p-2 2xl:p-2.5">
                            {hasOverStock && (
                                <p className="mb-1 border border-destructive/30 bg-destructive/5 px-1.5 py-0.5 text-[9px] text-destructive lg:mb-2 lg:px-2 lg:py-1 lg:text-[10px]">
                                    Stock exceeded — adjust quantities.
                                </p>
                            )}

                            <div className="grid grid-cols-3 gap-1 lg:gap-1.5">
                                <Button type="button" variant="outline" size="sm" className="h-7 border-red-400 text-[11px] text-red-600 hover:bg-red-50 hover:text-red-600 dark:bg-white dark:hover:bg-red-50 dark:hover:text-red-600 lg:h-8 lg:text-xs" asChild>
                                    <Link href={route('inventory.sell.index')}>Cancel</Link>
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={form.processing || items.length === 0 || hasOverStock}
                                    onClick={handlePause}
                                    className="h-7 border-amber-400 text-[11px] text-amber-700 hover:bg-amber-50 hover:text-amber-700 dark:bg-white dark:hover:bg-amber-50 dark:hover:text-amber-700 lg:h-8 lg:text-xs"
                                >
                                    Pause
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={form.processing || items.length === 0 || hasOverStock}
                                    className="h-7 bg-emerald-600 text-[11px] text-white hover:bg-emerald-600/90 lg:h-8 lg:text-xs"
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
