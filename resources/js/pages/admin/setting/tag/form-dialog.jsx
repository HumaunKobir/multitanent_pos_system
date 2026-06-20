import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Tag } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

function resolveStatus(status) {
    if (status === null || status === undefined) {
        return '0';
    }

    if (typeof status === 'object') {
        return String(status.value ?? 0);
    }

    return String(status);
}

function resolveParentId(parentId) {
    if (parentId === null || parentId === undefined || parentId === '') {
        return '__none__';
    }

    return String(parentId);
}

export default function TagFormDialog({ open, onOpenChange, item, routes, parentOptions, statusOptions }) {
    const isEditing = !!item?.id;

    const form = useForm({
        parent_id: resolveParentId(item?.parent_id),
        name: item?.name ?? '',
        image: null,
        status: resolveStatus(item?.status),
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            parent_id: resolveParentId(item?.parent_id),
            name: item?.name ?? '',
            image: null,
            status: resolveStatus(item?.status),
        });
        form.clearErrors();
    }, [open, item]);

    function handleSubmit(e) {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
            forceFormData: form.data.image instanceof File,
        };

        if (isEditing) {
            form.submit('patch', routes.update(item.id), options);

            return;
        }

        form.post(routes.store, options);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="p-0">
                <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <Tag className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">{isEditing ? 'Edit Tag' : 'Create Tag'}</h2>
                </div>

                <form onSubmit={handleSubmit} className="space-y-1.5 px-3 py-2">
                    <FormField label="Parent Id" name="parent_id" error={form.errors.parent_id}>
                        <Select value={form.data.parent_id} onValueChange={(value) => form.setData('parent_id', value)}>
                            <SelectTrigger id="parent_id" className="mt-1 w-full" aria-invalid={!!form.errors.parent_id}>
                                <SelectValue placeholder="--Select--" />
                            </SelectTrigger>
                            <SelectContent>
                                {parentOptions.map((option) => (
                                    <SelectItem key={option.value} value={String(option.value)}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField label="Name" name="name" required error={form.errors.name}>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Tag name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                    </FormField>

                    <FormField label="Image" name="image" error={form.errors.image}>
                        <Input
                            id="image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) => form.setData('image', e.target.files[0] ?? null)}
                            className="mt-1"
                            aria-invalid={!!form.errors.image}
                        />
                    </FormField>

                    <FormField label="Status" name="status" required error={form.errors.status}>
                        <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                            <SelectTrigger id="status" className="mt-1 w-full" aria-invalid={!!form.errors.status}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option.value} value={String(option.value)}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <div className="flex justify-end gap-3 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            size="sm"
                            className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50"
                        >
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
