import {
    CommentCard,
    DateField,
    InventoryCard,
    InventoryFormActions,
    InventoryPageHeader,
    LineItemsTable,
    ProductNameWithCode,
    formatQty,
    inputCls,
} from '@/components/inventory/inventory-form';
import { ProductSearchBox } from '@/components/inventory/product-search-box';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Package, Scale, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function StockAdjustmentCreate({ today }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const form = useForm({ date: today, type: 'decrease', comment: '', items: [] });
    const [items, setItems] = useState([]);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    function addItem(item) {
        const exists = items.find(
            (it) => it.product_id === item.product_id && String(it.variation_id) === String(item.variation_id),
        );
        if (exists) {
            setItems((prev) =>
                prev.map((it) =>
                    it.product_id === item.product_id && String(it.variation_id) === String(item.variation_id)
                        ? { ...it, quantity: String(parseFloat(it.quantity) + 1) }
                        : it,
                ),
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

        const adjustmentItems = items
            .filter((it) => parseFloat(it.quantity || 0) > 0)
            .map(({ product_id, variation_id, quantity }) => ({
                product_id,
                variation_id,
                quantity,
            }));

        if (adjustmentItems.length === 0) {
            toast.error('Add quantity for at least one product.');
            return;
        }

        form.transform((data) => ({
            ...data,
            items: adjustmentItems,
        }));
        form.post(route('inventory.stock-adjustment.store'), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(Array.isArray(first) ? first[0] : first);
            },
        });
    }

    return (
        <>
            <Head title="New Stock Adjustment" />
            <div className="px-2 py-1">
                <InventoryPageHeader
                    title="Stock Adjustment"
                    subtitle="Increase or decrease on-hand inventory."
                    icon={Scale}
                    backRoute="inventory.stock-adjustment.index"
                />

                <form
                    onSubmit={handleSubmit}
                    onKeyDown={(e) => {
                        if (e.key !== 'Enter') {
                            return;
                        }
                        if (e.target instanceof HTMLTextAreaElement) {
                            return;
                        }
                        if (e.target instanceof HTMLButtonElement && e.target.type === 'submit') {
                            return;
                        }
                        e.preventDefault();
                    }}
                    className="space-y-4"
                >
                    <InventoryCard title="Adjustment Details" icon={CalendarDays}>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <DateField
                                label="Date"
                                value={form.data.date}
                                onChange={(v) => form.setData('date', v)}
                                error={form.errors.date}
                            />
                            <div>
                                <Label className="mb-1.5 text-xs font-semibold">Type</Label>
                                <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="increase">Increase stock</SelectItem>
                                        <SelectItem value="decrease">Decrease stock</SelectItem>
                                    </SelectContent>
                                </Select>
                                {form.errors.type && <p className="mt-1 text-xs text-destructive">{form.errors.type}</p>}
                            </div>
                        </div>
                    </InventoryCard>

                    <InventoryCard title="Add Products" icon={Package}>
                        <ProductSearchBox onAdd={addItem} />
                        {form.errors.items && <p className="mt-1 text-xs text-destructive">{form.errors.items}</p>}
                        {items.length > 0 && (
                            <div className="mt-4">
                                <LineItemsTable
                                    columns={[
                                        { id: 'num', header: '#' },
                                        { id: 'product', header: 'Product' },
                                        { id: 'qty', header: 'Qty', align: 'right' },
                                        { id: 'actions', header: '' },
                                    ]}
                                >
                                    {items.map((item, i) => (
                                        <tr key={i} className="hover:bg-muted/20">
                                            <td className="px-3 py-2 text-muted-foreground">{i + 1}</td>
                                            <td className="px-3 py-2">
                                                <ProductNameWithCode name={item.product_name} code={item.product_code} />
                                                {item.variation_label && (
                                                    <p className="text-muted-foreground">{item.variation_label}</p>
                                                )}
                                            </td>
                                            <td className="px-2 py-1.5 text-right">
                                                <Input
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    value={item.quantity}
                                                    onChange={(e) => updateItem(i, 'quantity', formatQty(e.target.value))}
                                                    className={`${inputCls} ml-auto w-24 text-right`}
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
                                    ))}
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
                    </div>

                    <InventoryFormActions
                        cancelRoute="inventory.stock-adjustment.index"
                        submitLabel="Save Adjustment"
                        processing={form.processing}
                        disabled={items.length === 0}
                    />
                </form>
            </div>
        </>
    );
}
