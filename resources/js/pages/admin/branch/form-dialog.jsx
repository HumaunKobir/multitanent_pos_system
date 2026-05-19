import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useEffect } from 'react';

function resolveStatus(status) {
    if (status === null || status === undefined) {
        return 1;
    }
    if (typeof status === 'object') {
        return status.value ?? 1;
    }
    return status;
}

function FormField({ label, name, error, children }) {
    return (
        <div>
            <Label htmlFor={name}>{label}</Label>
            {children}
            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
        </div>
    );
}

export default function BranchFormDialog({ open, onOpenChange, item, routes }) {
    const isEditing = !!item?.id;

    const form = useForm({
        name: item?.name ?? '',
        phone: item?.phone ?? '',
        address: item?.address ?? '',
        status: String(resolveStatus(item?.status)),
    });

    useEffect(() => {
        form.setData({
            name: item?.name ?? '',
            phone: item?.phone ?? '',
            address: item?.address ?? '',
            status: String(resolveStatus(item?.status)),
        });
        form.clearErrors();
    }, [item]);

    function handleSubmit(e) {
        e.preventDefault();

        const options = {
            onSuccess: () => onOpenChange(false),
        };

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
                        <Building2 className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">{isEditing ? 'Edit Branch' : 'Create Branch'}</h2>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 p-5">
                    <FormField label="Name" name="name" error={form.errors.name}>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Branch name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                    </FormField>

                    <FormField label="Phone" name="phone" error={form.errors.phone}>
                        <Input
                            id="phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            placeholder="Phone number"
                            className="mt-1"
                            aria-invalid={!!form.errors.phone}
                        />
                    </FormField>

                    <FormField label="Address" name="address" error={form.errors.address}>
                        <textarea
                            id="address"
                            value={form.data.address}
                            onChange={(e) => form.setData('address', e.target.value)}
                            placeholder="Branch address"
                            rows={3}
                            className="mt-1 flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            aria-invalid={!!form.errors.address}
                        />
                    </FormField>

                    {isEditing && (
                        <FormField label="Status" name="status" error={form.errors.status}>
                            <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                                <SelectTrigger id="status" className="mt-1 w-full" aria-invalid={!!form.errors.status}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="2">InActive</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    )}

                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
