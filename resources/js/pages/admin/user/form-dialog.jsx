import { FormField } from '@/components/form-field';
import { SmartSelect } from '@/components/smart-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { useEffect, useMemo } from 'react';

export default function UserFormDialog({ open, onOpenChange, item, routes, branches, roles }) {
    const isEditing = !!item?.id;
    const roleOptions = Object.entries(roles ?? {});
    const branchSelectOptions = useMemo(
        () =>
            Object.entries(branches ?? {}).map(([id, name]) => ({
                value: String(id),
                label: name,
            })),
        [branches],
    );

    const form = useForm({
        branch_id: item?.branch_id ? String(item.branch_id) : '',
        name: item?.name ?? '',
        email: item?.email ?? '',
        phone: item?.phone ?? '',
        password: '',
        password_confirmation: '',
        status: String(item?.status ?? '1'),
        role_id: item?.role_id ? String(item.role_id) : '',
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
            role_id: item?.role_id ? String(item.role_id) : '',
        });
        form.clearErrors();
    }, [item]);

    function handleSubmit(e) {
        e.preventDefault();

        form.transform((data) => {
            const next = {
                ...data,
                branch_id: data.branch_id === '' ? null : Number(data.branch_id),
                role_id: data.role_id === '' ? null : Number(data.role_id),
            };

            if (isEditing && !data.password) {
                delete next.password;
                delete next.password_confirmation;
            }

            return next;
        });

        const options = { onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            form.patch(routes.update(item.id), options);
        } else {
            form.post(routes.store, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="p-0 sm:max-w-lg" onOpenAutoFocus={(event) => event.preventDefault()}>
                <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <UserRound className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">{isEditing ? 'Edit User' : 'Create User'}</h2>
                </div>

                <form onSubmit={handleSubmit} className="max-h-[75vh] space-y-3 overflow-y-auto p-4">
                    <FormField label="Branch" name="branch_id" required error={form.errors.branch_id}>
                        <div className="mt-1">
                            <SmartSelect
                                key={open ? 'open' : 'closed'}
                                id="user-branch"
                                options={branchSelectOptions}
                                value={form.data.branch_id === '' ? '' : String(form.data.branch_id)}
                                onValueChange={(value) => form.setData('branch_id', value ?? '')}
                                placeholder={branchSelectOptions.length === 0 ? 'No available branch' : 'Search branch…'}
                                disabled={branchSelectOptions.length === 0}
                                autoComplete="one-time-code"
                                triggerClassName="rounded-md"
                            />
                        </div>
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

                    <FormField label="Email" name="email" required error={form.errors.email}>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            placeholder="Email address"
                            className="mt-1"
                            aria-invalid={!!form.errors.email}
                        />
                    </FormField>

                    <FormField label="Phone" name="phone" required error={form.errors.phone}>
                        <Input
                            id="phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            placeholder="Phone number"
                            className="mt-1"
                            aria-invalid={!!form.errors.phone}
                        />
                    </FormField>

                    <FormField label="Password" name="password" required={!isEditing} error={form.errors.password}>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            placeholder={isEditing ? 'Leave blank to keep current' : 'Password'}
                            className="mt-1"
                            aria-invalid={!!form.errors.password}
                        />
                    </FormField>

                    <FormField label="Confirm Password" name="password_confirmation" required={!isEditing} error={form.errors.password_confirmation}>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            placeholder="Confirm password"
                            className="mt-1"
                            aria-invalid={!!form.errors.password_confirmation}
                        />
                    </FormField>

                    <FormField label="Role" name="role_id" error={form.errors.role_id}>
                        <Select
                            value={form.data.role_id === '' ? '__none__' : form.data.role_id}
                            onValueChange={(value) => form.setData('role_id', value === '__none__' ? '' : value)}
                        >
                            <SelectTrigger id="role_id" className="mt-1 w-full" aria-invalid={!!form.errors.role_id}>
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">No Role</SelectItem>
                                {roleOptions.map(([id, name]) => (
                                    <SelectItem key={id} value={String(id)}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField label="Status" name="status" required error={form.errors.status}>
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
