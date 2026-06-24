import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAppToast } from '@/contexts/app-toast-context';
import { useForm } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { useEffect, useMemo } from 'react';

function boolField(value) {
    return value === false || value === '0' ? '0' : '1';
}

function defaultForm(item) {
    return {
        name: item?.name ?? '',
        description: item?.description ?? '',
        scope: item?.scope ?? 'product',
        type: item?.type ?? 'percent',
        discount_value: item?.discount_value !== undefined ? String(item.discount_value) : '',
        fixed_price: item?.fixed_price !== undefined && item?.fixed_price !== null ? String(item.fixed_price) : '',
        buy_qty: item?.buy_qty !== undefined && item?.buy_qty !== null ? String(item.buy_qty) : '',
        get_qty: item?.get_qty !== undefined && item?.get_qty !== null ? String(item.get_qty) : '',
        get_discount_percent: item?.get_discount_percent !== undefined && item?.get_discount_percent !== null ? String(item.get_discount_percent) : '100',
        bundle_product_ids: item?.bundle_product_ids ?? [],
        target_ids: item?.target_ids ?? [],
        min_qty: item?.min_qty !== undefined && item?.min_qty !== null ? String(item.min_qty) : '',
        starts_at: item?.starts_at ?? '',
        ends_at: item?.ends_at ?? '',
        status: boolField(item?.status),
        priority: item?.priority !== undefined ? String(item.priority) : '0',
        stack_with_invoice_discount: boolField(item?.stack_with_invoice_discount ?? true),
        stack_with_special_discount: boolField(item?.stack_with_special_discount ?? true),
    };
}

export default function PromotionFormDialog({ open, onOpenChange, item, routes, promotionScopes, promotionTypes, catalogOptions }) {
    const isEditing = !!item?.id;
    const toast = useAppToast();
    const form = useForm(defaultForm(item));

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData(defaultForm(item));
        form.clearErrors();
    }, [open, item]);

    const targetOptions = useMemo(() => {
        if (form.data.scope === 'category') return catalogOptions.categories ?? [];
        if (form.data.scope === 'brand') return catalogOptions.brands ?? [];
        return catalogOptions.products ?? [];
    }, [form.data.scope, catalogOptions]);

    function toggleTarget(id) {
        const numericId = Number(id);
        const current = form.data.target_ids.map(Number);
        form.setData(
            'target_ids',
            current.includes(numericId) ? current.filter((value) => value !== numericId) : [...current, numericId],
        );
    }

    function toggleBundleProduct(id) {
        const numericId = Number(id);
        const current = form.data.bundle_product_ids.map(Number);
        form.setData(
            'bundle_product_ids',
            current.includes(numericId) ? current.filter((value) => value !== numericId) : [...current, numericId],
        );
    }

    function validateClient() {
        if (!form.data.name.trim()) {
            toast.error('Promotion name is required.');
            return false;
        }

        if (form.data.target_ids.length === 0) {
            toast.error('Select at least one target (category, brand, or product).');
            return false;
        }

        if (['percent', 'flat', 'bundle'].includes(form.data.type) && form.data.discount_value === '') {
            toast.error('Discount value is required.');
            return false;
        }

        if (form.data.type === 'fixed_price' && form.data.fixed_price === '') {
            toast.error('Fixed price is required.');
            return false;
        }

        if (form.data.type === 'buy_x_get_y') {
            if (form.data.buy_qty === '' || Number(form.data.buy_qty) < 1) {
                toast.error('Buy quantity must be at least 1.');
                return false;
            }
            if (form.data.get_qty === '' || Number(form.data.get_qty) < 1) {
                toast.error('Get quantity must be at least 1.');
                return false;
            }
        }

        if (form.data.type === 'bundle' && form.data.bundle_product_ids.length === 0) {
            toast.error('Select at least one bundle product.');
            return false;
        }

        return true;
    }

    function handleSubmit(e) {
        e.preventDefault();

        if (!validateClient()) {
            return;
        }

        const payload = {
            ...form.data,
            discount_value: form.data.discount_value === '' ? null : form.data.discount_value,
            fixed_price: form.data.fixed_price === '' ? null : form.data.fixed_price,
            buy_qty: form.data.buy_qty === '' ? null : form.data.buy_qty,
            get_qty: form.data.get_qty === '' ? null : form.data.get_qty,
            get_discount_percent: form.data.get_discount_percent === '' ? null : form.data.get_discount_percent,
            min_qty: form.data.min_qty === '' ? null : form.data.min_qty,
            starts_at: form.data.starts_at === '' ? null : form.data.starts_at,
            ends_at: form.data.ends_at === '' ? null : form.data.ends_at,
            status: form.data.status === '1',
            priority: Number(form.data.priority || 0),
            stack_with_product_discount: true,
            stack_with_manual_line_discount: true,
            stack_with_invoice_discount: form.data.stack_with_invoice_discount === '1',
            stack_with_special_discount: form.data.stack_with_special_discount === '1',
            exclusive: false,
            target_ids: form.data.target_ids.map(Number),
        };

        if (form.data.type === 'bundle') {
            payload.bundle_product_ids = form.data.bundle_product_ids.map(Number);
        }

        if (['buy_x_get_y', 'bundle'].includes(form.data.type)) {
            payload.min_qty = null;
        }

        form.transform(() => payload);

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) {
                    toast.error(Array.isArray(first) ? first[0] : first);
                } else {
                    toast.error('Could not save promotion. Please check the form and try again.');
                }
            },
        };

        if (isEditing) {
            form.patch(routes.update(item.id), options);
        } else {
            form.post(routes.store, options);
        }
    }

    const showDiscountValue = ['percent', 'flat', 'bundle'].includes(form.data.type);
    const showFixedPrice = form.data.type === 'fixed_price' || form.data.type === 'bundle';
    const showBogo = form.data.type === 'buy_x_get_y';
    const showBundleProducts = form.data.type === 'bundle';
    const showMinQty = !['buy_x_get_y', 'bundle'].includes(form.data.type);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex flex-col overflow-hidden p-0 sm:max-w-2xl">
                <div className="flex shrink-0 items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <Megaphone className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">
                        {isEditing ? 'Edit Promotion' : 'Create Promotion'}
                    </h2>
                </div>

                <form onSubmit={handleSubmit} className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-3">
                    {Object.keys(form.errors).length > 0 && (
                        <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            Please fix the highlighted errors below.
                        </div>
                    )}

                    <div>
                        <Label htmlFor="name">Name<RequiredMark /></Label>
                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="mt-1" />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>

                    <div>
                        <Label htmlFor="description">Description</Label>
                        <Textarea id="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="mt-1" rows={2} />
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label>Scope<RequiredMark /></Label>
                            <Select value={form.data.scope} onValueChange={(value) => form.setData({ ...form.data, scope: value, target_ids: [] })}>
                                <SelectTrigger className="mt-1 w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {promotionScopes.map((scope) => (
                                        <SelectItem key={scope.value} value={scope.value}>{scope.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Type<RequiredMark /></Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(value) => form.setData({
                                    ...form.data,
                                    type: value,
                                    min_qty: ['buy_x_get_y', 'bundle'].includes(value) ? '' : form.data.min_qty,
                                })}
                            >
                                <SelectTrigger className="mt-1 w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {promotionTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>{type.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div>
                        <Label>Targets<RequiredMark /></Label>
                        <div className="mt-1 max-h-36 overflow-y-auto rounded-md border p-2">
                            {targetOptions.length === 0 && <p className="text-xs text-muted-foreground">No options available.</p>}
                            {targetOptions.map((option) => (
                                <label key={option.id} className="flex items-center gap-2 py-1 text-sm">
                                    <Checkbox
                                        checked={form.data.target_ids.map(Number).includes(Number(option.id))}
                                        onCheckedChange={() => toggleTarget(option.id)}
                                    />
                                    <span>{option.name}{option.code ? ` (${option.code})` : ''}</span>
                                </label>
                            ))}
                        </div>
                        {form.errors.target_ids && <p className="mt-1 text-xs text-destructive">{form.errors.target_ids}</p>}
                    </div>

                    {showDiscountValue && (
                        <div>
                            <Label htmlFor="discount_value">Discount Value<RequiredMark /></Label>
                            <Input id="discount_value" type="number" min="0" step="0.01" value={form.data.discount_value} onChange={(e) => form.setData('discount_value', e.target.value)} className="mt-1" />
                            {form.errors.discount_value && <p className="mt-1 text-xs text-destructive">{form.errors.discount_value}</p>}
                        </div>
                    )}

                    {showFixedPrice && (
                        <div>
                            <Label htmlFor="fixed_price">Fixed Price (৳){form.data.type === 'fixed_price' && <RequiredMark />}</Label>
                            <Input id="fixed_price" type="number" min="0" step="0.01" value={form.data.fixed_price} onChange={(e) => form.setData('fixed_price', e.target.value)} className="mt-1" />
                            {form.errors.fixed_price && <p className="mt-1 text-xs text-destructive">{form.errors.fixed_price}</p>}
                        </div>
                    )}

                    {showBogo && (
                        <div className="grid grid-cols-3 gap-2">
                            <div>
                                <Label>Buy Qty<RequiredMark /></Label>
                                <Input type="number" min="1" value={form.data.buy_qty} onChange={(e) => form.setData('buy_qty', e.target.value)} className="mt-1" />
                            </div>
                            <div>
                                <Label>Get Qty<RequiredMark /></Label>
                                <Input type="number" min="1" value={form.data.get_qty} onChange={(e) => form.setData('get_qty', e.target.value)} className="mt-1" />
                            </div>
                            <div>
                                <Label>Get Discount %</Label>
                                <Input type="number" min="0" max="100" value={form.data.get_discount_percent} onChange={(e) => form.setData('get_discount_percent', e.target.value)} className="mt-1" />
                            </div>
                        </div>
                    )}

                    {showBundleProducts && (
                        <div>
                            <Label>Bundle Products<RequiredMark /></Label>
                            <div className="mt-1 max-h-36 overflow-y-auto rounded-md border p-2">
                                {(catalogOptions.products ?? []).map((option) => (
                                    <label key={option.id} className="flex items-center gap-2 py-1 text-sm">
                                        <Checkbox
                                            checked={form.data.bundle_product_ids.map(Number).includes(Number(option.id))}
                                            onCheckedChange={() => toggleBundleProduct(option.id)}
                                        />
                                        <span>{option.name}{option.code ? ` (${option.code})` : ''}</span>
                                    </label>
                                ))}
                            </div>
                            {form.errors.bundle_product_ids && <p className="mt-1 text-xs text-destructive">{form.errors.bundle_product_ids}</p>}
                        </div>
                    )}

                    {showMinQty && (
                        <div>
                            <Label>Min Qty <span className="text-xs font-normal text-muted-foreground">(optional)</span></Label>
                            <Input
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={form.data.min_qty}
                                onChange={(e) => form.setData('min_qty', e.target.value)}
                                placeholder="Any quantity"
                                className="mt-1"
                            />
                            {form.errors.min_qty && <p className="mt-1 text-xs text-destructive">{form.errors.min_qty}</p>}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label>Starts At</Label>
                            <Input type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Ends At</Label>
                            <Input type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} className="mt-1" />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label>Priority</Label>
                            <Input type="number" min="0" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                                <SelectTrigger className="mt-1 w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">InActive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="rounded-md border p-3">
                        <p className="mb-2 text-sm font-medium">Stacking Rules</p>
                        <div className="grid grid-cols-2 gap-2 text-sm">
                            {[
                                ['stack_with_invoice_discount', 'Invoice discount'],
                                ['stack_with_special_discount', 'Special discount'],
                            ].map(([field, label]) => (
                                <label key={field} className="flex items-center gap-2">
                                    <Checkbox
                                        checked={form.data[field] === '1'}
                                        onCheckedChange={(checked) => form.setData(field, checked ? '1' : '0')}
                                    />
                                    <span>{label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 border-t pt-4">
                        <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button type="submit" disabled={form.processing} size="sm" className="bg-emerald-600 text-white">
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
