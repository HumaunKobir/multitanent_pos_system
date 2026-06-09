import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { Percent } from 'lucide-react';
import { useEffect } from 'react';

export default function SpecialDiscountFormDialog({ open, onOpenChange, item, routes, discountTypes }) {
    const isEditing = !!item?.id;

    const form = useForm({
        name: item?.name ?? '',
        min_amount: item?.min_amount !== undefined ? String(item.min_amount) : '',
        max_amount: item?.max_amount !== undefined && item?.max_amount !== null ? String(item.max_amount) : '',
        discount_type: item?.discount_type ?? 'flat',
        discount_value: item?.discount_value !== undefined ? String(item.discount_value) : '',
        status: item?.status === false ? '0' : '1',
    });

    useEffect(() => {
        form.setData({
            name: item?.name ?? '',
            min_amount: item?.min_amount !== undefined ? String(item.min_amount) : '',
            max_amount: item?.max_amount !== undefined && item?.max_amount !== null ? String(item.max_amount) : '',
            discount_type: item?.discount_type ?? 'flat',
            discount_value: item?.discount_value !== undefined ? String(item.discount_value) : '',
            status: item?.status === false ? '0' : '1',
        });
        form.clearErrors();
    }, [item]);

    function handleSubmit(e) {
        e.preventDefault();

        const payload = {
            ...form.data,
            max_amount: form.data.max_amount === '' ? null : form.data.max_amount,
            status: form.data.status === '1',
        };

        form.transform(() => payload);

        const options = { onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            form.patch(routes.update(item.id), options);
        } else {
            form.post(routes.store, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="p-0">
                <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <Percent className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">
                        {isEditing ? 'Edit Special Discount' : 'Create Special Discount'}
                    </h2>
                </div>

                <form onSubmit={handleSubmit} className="space-y-1.5 px-3 py-2">
                    <div>
                        <Label htmlFor="name">
                            Name
                            <RequiredMark />
                        </Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="e.g. Weekend Offer"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label htmlFor="min_amount">
                                Min Amount (৳)
                                <RequiredMark />
                            </Label>
                            <Input
                                id="min_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.min_amount}
                                onChange={(e) => form.setData('min_amount', e.target.value)}
                                placeholder="1000"
                                className="mt-1"
                                aria-invalid={!!form.errors.min_amount}
                            />
                            {form.errors.min_amount && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.min_amount}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="max_amount">Max Amount (৳)</Label>
                            <Input
                                id="max_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.max_amount}
                                onChange={(e) => form.setData('max_amount', e.target.value)}
                                placeholder="Optional"
                                className="mt-1"
                                aria-invalid={!!form.errors.max_amount}
                            />
                            <p className="mt-1 text-[11px] text-muted-foreground">Leave empty for no upper limit.</p>
                            {form.errors.max_amount && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.max_amount}</p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label htmlFor="discount_type">
                                Discount Type
                                <RequiredMark />
                            </Label>
                            <Select
                                value={form.data.discount_type}
                                onValueChange={(value) => form.setData('discount_type', value)}
                            >
                                <SelectTrigger id="discount_type" className="mt-1 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {discountTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.discount_type && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.discount_type}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="discount_value">
                                Discount Value
                                <RequiredMark />
                            </Label>
                            <Input
                                id="discount_value"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.discount_value}
                                onChange={(e) => form.setData('discount_value', e.target.value)}
                                placeholder={form.data.discount_type === 'percent' ? '10' : '100'}
                                className="mt-1"
                                aria-invalid={!!form.errors.discount_value}
                            />
                            {form.errors.discount_value && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.discount_value}</p>
                            )}
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="status">
                            Status
                            <RequiredMark />
                        </Label>
                        <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                            <SelectTrigger id="status" className="mt-1 w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">InActive</SelectItem>
                            </SelectContent>
                        </Select>
                        {form.errors.status && <p className="mt-1 text-xs text-destructive">{form.errors.status}</p>}
                    </div>

                    <div className="flex justify-end gap-3 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="border-red-500 text-red-500"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing} size="sm" className="bg-emerald-600 text-white">
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
