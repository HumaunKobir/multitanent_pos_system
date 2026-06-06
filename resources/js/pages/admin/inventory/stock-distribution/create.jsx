import {
    CommentCard,
    DateField,
    InventoryCard,
    InventoryField,
    InventoryFormActions,
    InventoryPageHeader,
    LineItemsTable,
    formatQty,
    inputCls,
} from '@/components/inventory/inventory-form';
import { ProductSearchBox } from '@/components/inventory/product-search-box';
import { SmartSelect } from '@/components/smart-select';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRightLeft, Building2, CalendarDays, Package, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function StockDistributionCreate({ today, branches = [] }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const form = useForm({ to_branch_id: '', date: today, comment: '', items: [] });
    const [items, setItems] = useState([]);
    const branchOptions = useMemo(
        () => branches.map((branch) => ({ value: String(branch.id), label: branch.name })),
        [branches],
    );

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    function addItem(item) {
        const maxStock = parseFloat(item.available_stock ?? 0);
        if (maxStock <= 0) {
            toast.error('No stock available at main branch for this product.');
            return;
        }

        const exists = items.find(
            (it) => it.product_id === item.product_id && String(it.variation_id) === String(item.variation_id),
        );

        if (exists) {
            setItems((prev) =>
                prev.map((it) => {
                    if (it.product_id !== item.product_id || String(it.variation_id) !== String(item.variation_id)) {
                        return it;
                    }

                    const nextQty = Math.min(parseFloat(it.quantity) + 1, parseFloat(it.available_stock ?? 0));

                    return { ...it, quantity: String(nextQty) };
                }),
            );
            return;
        }

        setItems((prev) => [...prev, { ...item, quantity: '1' }]);
    }

    function updateItem(index, field, value) {
        setItems((prev) => prev.map((it, i) => (i === index ? { ...it, [field]: value } : it)));
    }

    function removeItem(index) {
        setItems((prev) => prev.filter((_, i) => i !== index));
    }

    function handleSubmit(e) {
        e.preventDefault();

        if (!form.data.to_branch_id) {
            toast.error('Select a destination branch.');
            return;
        }

        const distributionItems = items
            .filter((it) => parseInt(it.quantity || 0, 10) > 0)
            .map(({ product_id, variation_id, quantity }) => ({
                product_id,
                variation_id,
                quantity,
            }));

        if (distributionItems.length === 0) {
            toast.error('Add quantity for at least one product.');
            return;
        }

        const hasOverStock = items.some(
            (item) => parseFloat(item.quantity || 0) > parseFloat(item.available_stock ?? 0),
        );

        if (hasOverStock) {
            toast.error('Quantity exceeds available main branch stock.');
            return;
        }

        form.transform((data) => ({
            ...data,
            items: distributionItems,
        }));
        form.post(route('inventory.stock-distribution.store'), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    return (
        <>
            <Head title="New Stock Distribution" />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Distribute Stock"
                    subtitle="Send stock from main branch to an operating branch."
                    icon={ArrowRightLeft}
                    backRoute="inventory.stock-distribution.index"
                />

                <form onSubmit={handleSubmit} className="space-y-4">
                    <InventoryCard title="Distribution Details" icon={CalendarDays}>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <InventoryField label="Destination Branch" required error={form.errors.to_branch_id}>
                                <SmartSelect
                                    options={branchOptions}
                                    value={form.data.to_branch_id ? String(form.data.to_branch_id) : null}
                                    onValueChange={(value) => form.setData('to_branch_id', value ?? '')}
                                    placeholder="Search branch…"
                                    triggerClassName="h-8 rounded-md text-xs"
                                />
                            </InventoryField>
                            <DateField
                                label="Date"
                                value={form.data.date}
                                onChange={(v) => form.setData('date', v)}
                                error={form.errors.date}
                            />
                        </div>
                    </InventoryCard>

                    <InventoryCard title="Products from Main Branch" icon={Package}>
                        <ProductSearchBox onAdd={addItem} apiRoute="api.products.distribution" />
                        {form.errors.items && <p className="mt-1 text-xs text-destructive">{form.errors.items}</p>}
                        {items.length > 0 && (
                            <div className="mt-4">
                                <LineItemsTable
                                    columns={[
                                        { id: 'num', header: '#' },
                                        { id: 'product', header: 'Product' },
                                        { id: 'stock', header: 'Main Stock', align: 'right' },
                                        { id: 'qty', header: 'Qty', align: 'right' },
                                        { id: 'actions', header: '' },
                                    ]}
                                >
                                    {items.map((item, i) => {
                                        const qty = parseFloat(item.quantity || 0);
                                        const stock = parseFloat(item.available_stock ?? 0);
                                        const overStock = qty > stock;

                                        return (
                                            <tr key={i} className="hover:bg-muted/20">
                                                <td className="px-3 py-2 text-muted-foreground">{i + 1}</td>
                                                <td className="px-3 py-2">
                                                    <p className="font-medium">{item.product_name}</p>
                                                    {item.variation_label && (
                                                        <p className="text-muted-foreground">{item.variation_label}</p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right text-muted-foreground">{stock}</td>
                                                <td className="px-2 py-1.5 text-right">
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(i, 'quantity', formatQty(e.target.value))}
                                                        className={`${inputCls} ml-auto w-24 text-right ${overStock ? 'border-destructive' : ''}`}
                                                    />
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
                                </LineItemsTable>
                            </div>
                        )}
                    </InventoryCard>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <CommentCard
                            value={form.data.comment}
                            onChange={(v) => form.setData('comment', v)}
                            error={form.errors.comment}
                        />
                        <InventoryCard title="Summary" icon={Building2}>
                            <p className="text-xs text-muted-foreground">
                                Stock will be deducted from main branch (id: 1) and added to the selected branch.
                                All movements are recorded in the product ledger.
                            </p>
                        </InventoryCard>
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.stock-distribution.index"
                        submitLabel="Distribute Stock"
                        processing={form.processing}
                        disabled={items.length === 0 || !form.data.to_branch_id}
                    />
                </form>
            </div>
        </>
    );
}
