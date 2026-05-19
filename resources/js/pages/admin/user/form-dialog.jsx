import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { useEffect } from 'react';

function FormField({ label, name, error, children }) {
    return (
        <div>
            <Label htmlFor={name}>{label}</Label>
            {children}
            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
        </div>
    );
}

export default function UserFormDialog({ open, onOpenChange, item, routes, branches }) {
    const isEditing = !!item?.id;
    const branchOptions = Object.entries(branches ?? {});

    const form = useForm({
        branch_id: item?.branch_id ? String(item.branch_id) : '',
        name: item?.name ?? '',
        email: item?.email ?? '',
        phone: item?.phone ?? '',
        password: '',
        password_confirmation: '',
        status: String(item?.status ?? '1'),
    });

    useEffect(() => {
        form.setData({
            branch_id: item?.branch_id ? String(item.branch_id) : '',
            name: item?.name ?? '',
            email: item?.email ?? '',
            phone: item?.phone ?? '',
            password: '',
            password_confirmation: '',
            status: String(item?.status ?? '1'),
        });
        form.clearErrors();
    }, [item]);

    function handleSubmit(e) {
        e.preventDefault();

        form.transform((data) => {
            const next = {
                ...data,
                branch_id: data.branch_id === '' ? null : Number(data.branch_id),
            };

            if (isEditing && !data.password) {
                delete next.password;
                delete next.password_confirmation;
            }

            return next;
        });

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
            <DialogContent className="p-0 sm:max-w-lg">
                <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <UserRound className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">{isEditing ? 'Edit User' : 'Create User'}</h2>
                </div>

                <form onSubmit={handleSubmit} className="max-h-[75vh] space-y-4 overflow-y-auto p-5">
                    <FormField label="Branch" name="branch_id" error={form.errors.branch_id}>
                        <Select
                            value={form.data.branch_id === '' ? '__all__' : form.data.branch_id}
                            onValueChange={(value) => form.setData('branch_id', value === '__all__' ? '' : value)}
                        >
                            <SelectTrigger id="branch_id" className="mt-1 w-full" aria-invalid={!!form.errors.branch_id}>
                                <SelectValue placeholder="Select branch" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">All Branches</SelectItem>
                                {branchOptions.map(([id, name]) => (
                                    <SelectItem key={id} value={String(id)}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField label="Name" name="name" error={form.errors.name}>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Full name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                    </FormField>

                    <FormField label="Email" name="email" error={form.errors.email}>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            placeholder="Email address"
                            className="mt-1"
                            aria-invalid={!!form.errors.email}
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

                    <FormField label={isEditing ? 'Password (optional)' : 'Password'} name="password" error={form.errors.password}>
                        <Input
                            id="password"
                            type="password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            placeholder={isEditing ? 'Leave blank to keep current' : 'Password'}
                            className="mt-1"
                            aria-invalid={!!form.errors.password}
                        />
                    </FormField>

                    <FormField label="Confirm Password" name="password_confirmation" error={form.errors.password_confirmation}>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            placeholder="Confirm password"
                            className="mt-1"
                            aria-invalid={!!form.errors.password_confirmation}
                        />
                    </FormField>

                    <FormField label="Status" name="status" error={form.errors.status}>
                        <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                            <SelectTrigger id="status" className="mt-1 w-full" aria-invalid={!!form.errors.status}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">InActive</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

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
