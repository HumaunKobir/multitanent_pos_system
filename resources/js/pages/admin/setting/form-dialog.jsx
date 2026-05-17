import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function SettingFormDialog({ open, onOpenChange, title, item, routes, fields }) {
    const isEditing = !!item?.id;
    const defaults = fields.reduce((values, field) => {
        const value = item?.[field.name] ?? field.defaultValue ?? '';
        values[field.name] = field.type === 'file' ? null : field.type === 'select' ? String(value) : value;
        return values;
    }, {});

    const form = useForm(defaults);

    useEffect(() => {
        form.setData(defaults);
        form.clearErrors();
    }, [item]);

    function handleSubmit(e) {
        e.preventDefault();

        const options = {
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing) {
            if (fields.some((field) => field.type === 'file')) {
                form.post(routes.update(item.id), {
                    ...options,
                    _method: 'patch',
                });
            } else {
                form.patch(routes.update(item.id), options);
            }
        } else {
            form.post(routes.store, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEditing ? `Edit ${title}` : `Create ${title}`}</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4" encType="multipart/form-data">
                    {fields.map((field) => (
                        <div key={field.name}>
                            <Label htmlFor={field.name}>{field.label}</Label>
                            {field.type === 'select' ? (
                                <Select value={form.data[field.name]} onValueChange={(value) => form.setData(field.name, value)}>
                                    <SelectTrigger id={field.name} className="mt-1 w-full" aria-invalid={!!form.errors[field.name]}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">Active</SelectItem>
                                        <SelectItem value="0">InActive</SelectItem>
                                    </SelectContent>
                                </Select>
                            ) : field.type === 'color' ? (
                                <div className="mt-1 flex gap-2">
                                    <input
                                        type="color"
                                        value={form.data[field.name] || '#000000'}
                                        onChange={(e) => form.setData(field.name, e.target.value)}
                                        className="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-1"
                                    />
                                    <Input
                                        id={field.name}
                                        value={form.data[field.name]}
                                        onChange={(e) => form.setData(field.name, e.target.value)}
                                        placeholder={field.placeholder}
                                        className="flex-1"
                                        aria-invalid={!!form.errors[field.name]}
                                    />
                                </div>
                            ) : field.type === 'file' ? (
                                <>
                                    {isEditing && item?.[field.name] && (
                                        <div className="mt-1 mb-2">
                                            <img src={`/storage/${item[field.name]}`} alt="Current" className="h-16 w-16 rounded object-cover" />
                                            <p className="mt-1 text-xs text-muted-foreground">Upload a new image to replace the current one.</p>
                                        </div>
                                    )}
                                    <Input
                                        id={field.name}
                                        type="file"
                                        accept={field.accept}
                                        onChange={(e) => form.setData(field.name, e.target.files[0] ?? null)}
                                        className="mt-1"
                                        aria-invalid={!!form.errors[field.name]}
                                    />
                                </>
                            ) : (
                                <Input
                                    id={field.name}
                                    value={form.data[field.name]}
                                    onChange={(e) => form.setData(field.name, e.target.value)}
                                    placeholder={field.placeholder}
                                    className="mt-1"
                                    aria-invalid={!!form.errors[field.name]}
                                />
                            )}
                            {form.errors[field.name] && <p className="mt-1 text-xs text-destructive">{form.errors[field.name]}</p>}
                        </div>
                    ))}

                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" size="sm" className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing} size="sm" className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50">
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
