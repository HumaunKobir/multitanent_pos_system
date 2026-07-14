import { formatQty } from '@/components/inventory/inventory-form';
import { PurchaseProductSearchBox } from '@/components/inventory/purchase-product-search-box';
import { useAppToast } from '@/contexts/app-toast-context';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { emptyWhenZero, normalizeOptionalNumeric } from '@/lib/form-numeric';
import { toDateInputValue } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, CalendarDays, Check, HandCoins, MessageSquare, Package, Plus, Save, Search, Trash2, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { dateInputRightIconClassName } from '@/components/ui/date-kit';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SmartSelect } from '@/components/smart-select';


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
                {required && <RequiredMark />}
            </Label>
            {children}
            {error && <p className="mt-0.5 text-xs text-destructive">{error}</p>}
        </div>
    );
}

function supplierMatchesQuery(supplier, query) {
    const needle = query.toLowerCase().trim();
    const phoneNeedle = query.replace(/\D/g, '');

    return (
        (supplier.name ?? '').toLowerCase().includes(needle)
        || (supplier.company_name ?? '').toLowerCase().includes(needle)
        || (phoneNeedle !== '' && (supplier.phone ?? '').replace(/\D/g, '').includes(phoneNeedle))
    );
}

function SupplierOptionLabel({ supplier }) {
    return (
        <span>
            {supplier.company_name ? (
                <>
                    <span className="font-semibold">{supplier.company_name}</span>
                    <span className="text-muted-foreground"> · {supplier.name}</span>
                </>
            ) : (
                supplier.name
            )}
            <span className="text-muted-foreground"> · {supplier.phone}</span>
        </span>
    );
}

function SupplierSearch({ suppliers, value, onChange, onCreated, error }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [modalData, setModalData] = useState({ name: '', phone: '', company_name: '', address: '', opening_balance: '' });
    const [modalErrors, setModalErrors] = useState({});
    const [modalProcessing, setModalProcessing] = useState(false);
    const ref = useRef(null);
    const selected = suppliers.find((s) => String(s.id) === String(value));

    const filtered = q ? suppliers.filter((s) => supplierMatchesQuery(s, q)) : suppliers;

    useEffect(() => {
        function handleClick(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    function openModal() {
        setModalData({ name: '', phone: '', company_name: q.trim(), address: '', opening_balance: '' });
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
            const res = await fetch(route('api.suppliers.store'), {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
                body: JSON.stringify(modalData),
            });
            const json = await res.json();
            if (!res.ok) { setModalErrors(json.errors ?? {}); return; }
            onCreated(json);
            onChange(String(json.id));
            setModalOpen(false);
            setQ('');
        } finally {
            setModalProcessing(false);
        }
    }

    function clear() {
        onChange('');
        setQ('');
    }

    return (
        <>
            <div ref={ref} className="relative">
                {selected ? (
                    <div
                        className={`flex min-h-8 cursor-pointer items-center justify-between rounded-md border bg-background px-3 py-1.5 text-xs shadow-xs transition-colors hover:border-primary/60 ${error ? 'border-destructive' : 'border-input'}`}
                        onClick={() => setOpen((p) => !p)}
                    >
                        <SupplierOptionLabel supplier={selected} />
                        <button type="button" onClick={(e) => { e.stopPropagation(); clear(); }} className="ml-2 text-muted-foreground hover:text-foreground">✕</button>
                    </div>
                ) : (
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => { setQ(e.target.value); setOpen(true); }}
                            onFocus={() => setOpen(true)}
                            placeholder="Search by name, company or phone…"
                            className={`h-8 pl-8 text-xs ${error ? 'border-destructive' : ''}`}
                        />
                    </div>
                )}
                {open && (
                    <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
                        {selected && (
                            <div className="p-2">
                                <Input autoFocus placeholder="Search by name, company or phone…" value={q} onChange={(e) => setQ(e.target.value)} className="h-7 text-xs" />
                            </div>
                        )}
                        <ul className="max-h-52 overflow-auto">
                            {filtered.map((s) => (
                                <li
                                    key={s.id}
                                    className="flex cursor-pointer items-center justify-between px-3 py-2 text-xs hover:bg-accent"
                                    onClick={() => { onChange(String(s.id)); setOpen(false); setQ(''); }}
                                >
                                    <SupplierOptionLabel supplier={s} />
                                    {String(s.id) === String(value) && <Check className="size-3.5 shrink-0 text-primary" />}
                                </li>
                            ))}
                            {q.trim() && (
                                <li
                                    className="flex cursor-pointer items-center gap-1.5 border-t border-border px-3 py-2 text-xs font-medium text-primary hover:bg-accent"
                                    onClick={openModal}
                                >
                                    <Plus className="size-3.5" />
                                    Add &ldquo;{q.trim()}&rdquo;
                                </li>
                            )}
                            {!q.trim() && filtered.length === 0 && (
                                <li className="px-3 py-2 text-xs text-muted-foreground">No suppliers found.</li>
                            )}
                        </ul>
                    </div>
                )}
            </div>

            <Dialog open={modalOpen} onOpenChange={(o) => !o && setModalOpen(false)}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <User className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Add Supplier</h2>
                    </div>
                    <form onSubmit={handleCreate} className="space-y-1.5 px-3 py-2">
                        <Field label="Company Name" required error={modalErrors.company_name}>
                            <Input value={modalData.company_name} onChange={(e) => setField('company_name', e.target.value)} placeholder="Company name" className="mt-1" />
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Name" required error={modalErrors.name}>
                                <Input value={modalData.name} onChange={(e) => setField('name', e.target.value)} placeholder="Supplier name" className="mt-1" />
                            </Field>
                            <Field label="Phone" required error={modalErrors.phone}>
                                <Input value={modalData.phone} onChange={(e) => setField('phone', e.target.value)} placeholder="01XXXXXXXXX" className="mt-1" />
                            </Field>
                        </div>
                        <Field label="Address" error={modalErrors.address}>
                            <Input value={modalData.address} onChange={(e) => setField('address', e.target.value)} placeholder="Address" className="mt-1" />
                        </Field>
                        <Field label="Opening Balance" error={modalErrors.opening_balance}>
                            <Input type="number" min="0" step="0.01" value={modalData.opening_balance} onChange={(e) => setField('opening_balance', e.target.value)} placeholder="0" className="mt-1" />
                        </Field>
                        <div className="flex justify-end gap-3 border-t pt-4">
                            <Button type="button" variant="outline" size="sm" className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30" onClick={() => setModalOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" size="sm" disabled={modalProcessing} className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/50">
                                {modalProcessing ? 'Saving…' : 'Create'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function PurchaseEdit({
    purchase,
    suppliers: initialSuppliers,
    paymentAccounts = [],
    canDistribute = false,
    branches = [],
}) {
    const toast = useAppToast();
    useFlashToast();
    const form = useForm({
        supplier_id: purchase.supplier_id ? String(purchase.supplier_id) : '',
        date: toDateInputValue(purchase.date),
        discount: emptyWhenZero(purchase.discount),
        vat: emptyWhenZero(purchase.vat_percent),
        additional_payment: '',
        payment_account_id: purchase.payment_account_id ? String(purchase.payment_account_id) : '',
        comment: purchase.comment ?? '',
        distribute_to_branch_id: purchase.distribute_to_branch_id ? String(purchase.distribute_to_branch_id) : '',
        items: purchase.items ?? [],
    });

    const [items, setItems] = useState(
        (purchase.items ?? []).map((item) => ({
            ...item,
            distribute_quantity: item.distribute_quantity ?? 0,
        })),
    );
    const [suppliers, setSuppliers] = useState(initialSuppliers);
    const branchOptions = branches.map((branch) => ({ value: String(branch.id), label: branch.name }));

    const grossAmount = items.reduce((sum, it) => sum + parseFloat(it.quantity || 0) * parseFloat(it.unit_price || 0), 0);
    const discountAmount = parseFloat(form.data.discount || 0);
    const taxableAmount = Math.max(0, grossAmount - discountAmount);
    const vatAmount = taxableAmount * (parseFloat(form.data.vat || 0) / 100);
    const netAmount = taxableAmount + vatAmount;
    const supplierPaymentAllocations = purchase.supplier_payment_allocations ?? [];
    const allocationTotal = supplierPaymentAllocations.reduce(
        (sum, line) => sum + (parseFloat(line.amount) || 0),
        0,
    );
    const basePaidAmount = parseFloat(purchase.paid_amount ?? 0);
    const additionalPayment = parseFloat(form.data.additional_payment || 0) || 0;
    const maxAdditionalPayment = Math.max(0, netAmount - basePaidAmount);
    const totalPaidAmount = Math.min(netAmount, basePaidAmount + additionalPayment);
    const dueAmount = Math.max(0, netAmount - totalPaidAmount);
    const existingDirectPaid = Math.max(0, basePaidAmount - allocationTotal);
    const productLinesLocked = Boolean(purchase.product_lines_locked);

    function handleAdditionalPaymentChange(value) {
        if (value === '' || value === null) {
            form.setData('additional_payment', '');
            return;
        }

        const parsed = parseFloat(value);

        if (!Number.isFinite(parsed) || parsed < 0) {
            return;
        }

        form.setData('additional_payment', String(Math.min(parsed, maxAdditionalPayment)));
    }

    function addItem(item) {
        if (productLinesLocked) return;

        const duplicate = items.find((it) => it.product_id === item.product_id && String(it.variation_id) === String(item.variation_id));
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
        if (productLinesLocked) return;

        setItems((prev) => prev.map((it, i) => (i === index ? { ...it, [field]: value } : it)));
    }

    function removeItem(index) {
        if (productLinesLocked) return;

        setItems((prev) => prev.filter((_, i) => i !== index));
    }

    function removeItemByKey(productId, variationId) {
        if (productLinesLocked) return;

        setItems((prev) =>
            prev.filter(
                (item) => !(item.product_id === productId && String(item.variation_id) === String(variationId ?? null)),
            ),
        );
    }

    function handleSubmit(e) {
        e.preventDefault();

        const lineItems = items.filter((it) => parseFloat(it.quantity || 0) >= 1);
        if (lineItems.length === 0) {
            toast.error('Add at least one line with quantity 1 or more.');
            return;
        }

        if (canDistribute && form.data.distribute_to_branch_id) {
            const hasDistribution = lineItems.some((item) => parseInt(item.distribute_quantity || 0, 10) > 0);
            if (!hasDistribution) {
                toast.error('Add distribute quantity for at least one product.');
                return;
            }

            const hasInvalidDistribution = lineItems.some((item) => {
                const distributeQty = parseInt(item.distribute_quantity || 0, 10);
                const maxQty = parseInt(item.quantity || 0, 10) + parseInt(item.free_quantity || 0, 10);
                return distributeQty > maxQty;
            });

            if (hasInvalidDistribution) {
                toast.error('Distribute quantity cannot exceed purchased quantity.');
                return;
            }
        }

        form.setData('items', lineItems);

        const additional = parseFloat(form.data.additional_payment || 0) || 0;
        const totalPaid = Math.min(netAmount, basePaidAmount + additional);

        if (additional > 0 && !form.data.payment_account_id) {
            toast.error('Select a payment account for the new payment.');
            return;
        }

        if (additional === 0 && existingDirectPaid > 0 && !form.data.payment_account_id) {
            toast.error('Select a payment account for the paid amount.');
            return;
        }

        form.transform((data) => ({
            ...data,
            items: lineItems,
            discount: normalizeOptionalNumeric(data.discount),
            vat: normalizeOptionalNumeric(data.vat),
            paid_amount: String(totalPaid),
            payment_account_id:
                additional > 0 || existingDirectPaid > 0 ? data.payment_account_id : '',
        }));
        form.put(route('inventory.purchase.update', purchase.id), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    const inputCls = 'h-7 rounded-md border-border/60 text-xs px-2 focus:border-primary';

    return (
        <>
            <Head title={`Edit Purchase — ${purchase.invoice_number ?? ''}`} />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <HandCoins className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Edit Purchase</h1>
                            <p className="font-mono text-xs text-white/60">{purchase.invoice_number}</p>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        asChild
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    >
                        <Link href={route('inventory.purchase.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Card title="Purchase Details" icon={CalendarDays}>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Supplier" required error={form.errors.supplier_id}>
                                <SupplierSearch
                                    suppliers={suppliers}
                                    value={form.data.supplier_id}
                                    onChange={(v) => form.setData('supplier_id', v)}
                                    onCreated={(s) => setSuppliers((prev) => [...prev, s])}
                                    error={form.errors.supplier_id}
                                />
                            </Field>
                            <Field label="Date" required error={form.errors.date}>
                                <Input
                                    type="date"
                                    value={toDateInputValue(form.data.date)}
                                    onChange={(e) => form.setData('date', e.target.value)}
                                    className={`h-8 text-xs ${dateInputRightIconClassName}`}
                                />
                            </Field>
                        </div>
                    </Card>

                    <Card title="Add Products" icon={Package}>
                        {!productLinesLocked && <PurchaseProductSearchBox items={items} onAdd={addItem} onRemove={removeItemByKey} />}
                        {productLinesLocked && (
                            <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                Product lines are locked because this purchase is partially paid.
                            </p>
                        )}
                        {form.errors.items && <p className="mt-1 text-xs text-destructive">{form.errors.items}</p>}

                        {items.length > 0 && (
                            <div className="mt-4 overflow-x-auto rounded-md border border-border">
                                <table className="w-full table-auto text-xs">
                                    <thead className="bg-muted/40 text-xs uppercase tracking-wide">
                                        <tr>
                                            <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">#</th>
                                            <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">Product</th>
                                            <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Unit Price</th>
                                            <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Sell Price</th>
                                            <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Qty</th>
                                            <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Free Qty</th>
                                            {canDistribute && form.data.distribute_to_branch_id && (
                                                <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Distribute</th>
                                            )}
                                            <th className="whitespace-nowrap px-2 py-2 text-left font-semibold">Expiry Date</th>
                                            <th className="whitespace-nowrap px-3 py-2 text-right font-semibold">Sub Total</th>
                                            <th className="px-2 py-2"></th>
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
                                                        {item.variation_label && <p className="text-muted-foreground">{item.variation_label}</p>}
                                                        {item.current_stock !== null && item.current_stock !== undefined && (
                                                            <p className="text-[10px] text-muted-foreground">Stock: {parseFloat(item.current_stock)}</p>
                                                        )}
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={item.unit_price}
                                                            onChange={(e) => updateItem(i, 'unit_price', e.target.value)}
                                                            disabled={productLinesLocked}
                                                            className={`${inputCls} w-full text-right`}
                                                        />
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={item.sell_price}
                                                            onChange={(e) => updateItem(i, 'sell_price', e.target.value)}
                                                            disabled={productLinesLocked}
                                                            className={`${inputCls} w-full text-right`}
                                                        />
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            type="number"
                                                            min="1"
                                                            step="1"
                                                            value={item.quantity}
                                                            onChange={(e) => updateItem(i, 'quantity', formatQty(e.target.value))}
                                                            disabled={productLinesLocked}
                                                            className={`${inputCls} w-full text-right`}
                                                        />
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="1"
                                                            value={item.free_quantity}
                                                            onChange={(e) => updateItem(i, 'free_quantity', formatQty(e.target.value))}
                                                            disabled={productLinesLocked}
                                                            className={`${inputCls} w-full text-right`}
                                                        />
                                                    </td>
                                                    {canDistribute && form.data.distribute_to_branch_id && (
                                                        <td className="px-2 py-1.5">
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                step="1"
                                                                max={parseInt(item.quantity || 0, 10) + parseInt(item.free_quantity || 0, 10)}
                                                                value={item.distribute_quantity ?? 0}
                                                                onChange={(e) => updateItem(i, 'distribute_quantity', formatQty(e.target.value))}
                                                                disabled={productLinesLocked}
                                                                className={`${inputCls} w-full text-right`}
                                                            />
                                                        </td>
                                                    )}
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            type="date"
                                                            value={item.expiry_date}
                                                            onChange={(e) => updateItem(i, 'expiry_date', e.target.value)}
                                                            disabled={productLinesLocked}
                                                            className={`${inputCls} w-full`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-semibold">৳{subTotal.toFixed(2)}</td>
                                                    <td className="px-2 py-1.5 text-right">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => removeItem(i)}
                                                            disabled={productLinesLocked}
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

                    {canDistribute && (
                        <Card title="Distribute to Branch" icon={ArrowRightLeft}>
                            <Field label="Destination Branch" error={form.errors.distribute_to_branch_id}>
                                <SmartSelect
                                    options={[{ value: '', label: 'Keep all stock at main branch' }, ...branchOptions]}
                                    value={form.data.distribute_to_branch_id ? String(form.data.distribute_to_branch_id) : null}
                                    onValueChange={(value) => form.setData('distribute_to_branch_id', value ?? '')}
                                    placeholder="Select branch to distribute"
                                    triggerClassName="h-8 rounded-md text-xs"
                                />
                            </Field>
                            {form.data.distribute_to_branch_id && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Enter distribute quantity per product in the table above. Stock will stay pending until the branch receives it.
                                </p>
                            )}
                        </Card>
                    )}

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
                                        placeholder="0"
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
                                        placeholder="0"
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
                                        type="text"
                                        readOnly
                                        value={basePaidAmount.toFixed(2)}
                                        className={`${inputCls} w-28 bg-muted text-right`}
                                    />
                                </div>
                                {allocationTotal > 0 && (
                                    <p className="text-[10px] text-muted-foreground">
                                        Includes ৳{allocationTotal.toFixed(2)} from supplier payment that cannot be changed here.
                                    </p>
                                )}

                                {dueAmount > 0 && (
                                    <div className="flex items-center justify-between gap-4">
                                        <Label className="text-xs text-muted-foreground">Pay Now</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            max={maxAdditionalPayment}
                                            step="0.01"
                                            placeholder="0"
                                            value={form.data.additional_payment}
                                            onChange={(e) => handleAdditionalPaymentChange(e.target.value)}
                                            className={`${inputCls} w-28 text-right`}
                                        />
                                    </div>
                                )}
                                {additionalPayment > 0 && (
                                    <div className="flex justify-between text-xs text-muted-foreground">
                                        <span>Total after payment</span>
                                        <span className="font-medium tabular-nums text-foreground">
                                            ৳{totalPaidAmount.toFixed(2)}
                                        </span>
                                    </div>
                                )}
                                {form.errors.paid_amount && <p className="text-xs text-destructive">{form.errors.paid_amount}</p>}

                                {(dueAmount > 0 || additionalPayment > 0 || existingDirectPaid > 0) && (
                                    <div>
                                        <Label className="mb-1 block text-xs text-muted-foreground">Payment Account</Label>
                                        <select
                                            className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
                                            value={form.data.payment_account_id}
                                            onChange={(e) => form.setData('payment_account_id', e.target.value)}
                                        >
                                            <option value="">Select cash / bank account</option>
                                            {paymentAccounts.map((acc) => (
                                                <option key={acc.id} value={String(acc.id)}>
                                                    {acc.label}
                                                </option>
                                            ))}
                                        </select>
                                        {dueAmount > 0 && (
                                            <p className="mt-1 text-[10px] text-muted-foreground">
                                                Enter pay now amount to pay remaining due from this account.
                                            </p>
                                        )}
                                        {form.errors.payment_account_id && (
                                            <p className="mt-1 text-xs text-destructive">{form.errors.payment_account_id}</p>
                                        )}
                                    </div>
                                )}

                                {supplierPaymentAllocations.length > 0 && (
                                    <div className="space-y-1 rounded-md border border-dashed px-2 py-2">
                                        <p className="text-[10px] font-medium text-muted-foreground">Supplier Payment Allocations</p>
                                        {supplierPaymentAllocations.map((line, index) => {
                                            const account = paymentAccounts.find(
                                                (acc) => String(acc.id) === String(line.payment_account_id),
                                            );

                                            return (
                                                <div
                                                    key={`${line.payment_account_id}-${index}`}
                                                    className="flex items-center justify-between gap-2 text-[11px]"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {account?.label ?? 'Payment account'}
                                                    </span>
                                                    <span className="font-medium tabular-nums">
                                                        ৳{parseFloat(line.amount).toFixed(2)}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
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
                            <Link href={route('inventory.purchase.index')}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing || items.length === 0 || !form.data.supplier_id}
                            className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/50"
                        >
                            <Save className="size-3.5" />
                            {form.processing ? 'Saving…' : 'Update Purchase'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

