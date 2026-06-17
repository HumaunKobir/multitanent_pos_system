import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { route } from '@/lib/route';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Shield } from 'lucide-react';

function toPermissionList(value) {
    if (Array.isArray(value)) {
        return [...value];
    }

    if (value && typeof value === 'object') {
        return Object.values(value);
    }

    return [];
}

function permissionCheckboxId(name) {
    return `perm-${name.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
}

function permissionErrors(errors) {
    if (errors.permissions) {
        return errors.permissions;
    }

    return Object.entries(errors)
        .filter(([key]) => key.startsWith('permissions.'))
        .map(([, message]) => message)
        .join(' ');
}

function Card({ title, icon: Icon, children, action }) {
    return (
        <div className="border bg-card shadow-sm">
            <div className="flex items-center justify-between bg-blue-950 px-4 py-2.5">
                <div className="flex items-center gap-2.5">
                    {Icon && (
                        <div className="flex size-6 items-center justify-center rounded-md bg-white/15">
                            <Icon className="size-3.5 text-white" />
                        </div>
                    )}
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-white">{title}</h2>
                </div>
                {action}
            </div>
            <div className="p-4">{children}</div>
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

export default function RoleForm({ role = null, permissionGroups = [] }) {
    const isEditing = !!role?.id;

    const form = useForm({
        name: role?.name ?? '',
        permissions: toPermissionList(role?.permissions),
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            form.patch(route('role.update', { role: role.id }));
        } else {
            form.post(route('role.store'));
        }
    }

    function toggle(name) {
        const perms = form.data.permissions;
        form.setData('permissions', perms.includes(name) ? perms.filter((p) => p !== name) : [...perms, name]);
    }

    function toggleModule(modulePerms) {
        const names = modulePerms.map((p) => p.name);
        const allOn = names.every((n) => form.data.permissions.includes(n));
        form.setData(
            'permissions',
            allOn ? form.data.permissions.filter((p) => !names.includes(p)) : [...new Set([...form.data.permissions, ...names])],
        );
    }

    function toggleGroup(group) {
        const names = group.modules.flatMap((m) => m.permissions.map((p) => p.name));
        const allOn = names.every((n) => form.data.permissions.includes(n));
        form.setData(
            'permissions',
            allOn ? form.data.permissions.filter((p) => !names.includes(p)) : [...new Set([...form.data.permissions, ...names])],
        );
    }

    function moduleState(modulePerms) {
        const names = modulePerms.map((p) => p.name);
        const count = names.filter((n) => form.data.permissions.includes(n)).length;
        if (count === 0) return 'none';
        if (count === names.length) return 'all';
        return 'some';
    }

    function groupState(group) {
        const names = group.modules.flatMap((m) => m.permissions.map((p) => p.name));
        const count = names.filter((n) => form.data.permissions.includes(n)).length;
        if (count === 0) return 'none';
        if (count === names.length) return 'all';
        return 'some';
    }

    const permissionError = permissionErrors(form.errors);

    return (
        <div className="px-2 py-1">
            <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                <div className="flex items-center gap-3">
                    <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                        <Shield className="size-4 text-white" />
                    </div>
                    <div>
                        <h1 className="text-base font-semibold text-white">{isEditing ? 'Edit Role' : 'Create Role'}</h1>
                        <p className="text-xs text-white/60">
                            {isEditing ? `Editing "${role.name}"` : 'Add a new role.'}
                            {' · '}
                            {form.data.permissions.length} permissions selected
                        </p>
                    </div>
                </div>
                <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md">
                    <Link href={route('role.index')}>
                        <ArrowLeft className="size-3.5" />
                        Back
                    </Link>
                </Button>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="space-y-3">
                    <Card title="Role Details" icon={Shield}>
                        <Field label="Role Name" required error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="e.g. Sales Manager, Cashier, Accountant"
                                autoFocus
                                aria-invalid={!!form.errors.name}
                            />
                        </Field>
                    </Card>

                    {permissionError && (
                        <p className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                            {permissionError}
                        </p>
                    )}

                    {permissionGroups.map((group) => {
                        const gState = groupState(group);
                        const groupTotal = group.modules.flatMap((m) => m.permissions).length;
                        const groupSelected = group.modules.flatMap((m) => m.permissions).filter((p) => form.data.permissions.includes(p.name)).length;

                        return (
                            <Card
                                key={group.group}
                                title={group.group}
                                icon={KeyRound}
                                action={
                                    <label
                                        htmlFor={`group-${group.group}`}
                                        className="flex cursor-pointer items-center gap-1.5 text-xs text-white/70 transition-colors hover:text-white"
                                    >
                                        <Checkbox
                                            id={`group-${group.group}`}
                                            checked={gState === 'all' ? true : gState === 'some' ? 'indeterminate' : false}
                                            onCheckedChange={() => toggleGroup(group)}
                                            className="size-3.5 border-white/40 data-[state=checked]:border-white data-[state=checked]:bg-white data-[state=checked]:text-blue-950 data-[state=indeterminate]:border-white/40 data-[state=indeterminate]:bg-white/20"
                                        />
                                        <span>{groupSelected}/{groupTotal}</span>
                                    </label>
                                }
                            >
                                <div className="divide-y">
                                    {group.modules.map((module) => {
                                        const mState = moduleState(module.permissions);

                                        return (
                                            <div key={module.label} className="py-2.5 first:pt-0 last:pb-0">
                                                <div className="mb-2 flex items-center gap-2">
                                                    <Checkbox
                                                        id={`mod-${module.label}`}
                                                        checked={mState === 'all' ? true : mState === 'some' ? 'indeterminate' : false}
                                                        onCheckedChange={() => toggleModule(module.permissions)}
                                                        className="data-[state=indeterminate]:bg-primary data-[state=indeterminate]:opacity-60"
                                                    />
                                                    <Label htmlFor={`mod-${module.label}`} className="cursor-pointer text-sm font-medium">
                                                        {module.label}
                                                    </Label>
                                                </div>

                                                <div className="ml-6 grid grid-cols-2 gap-x-4 gap-y-1.5 sm:grid-cols-3 lg:grid-cols-4">
                                                    {module.permissions.map((perm) => {
                                                        const checkboxId = permissionCheckboxId(perm.name);

                                                        return (
                                                        <label
                                                            key={perm.name}
                                                            htmlFor={checkboxId}
                                                            className="flex cursor-pointer items-center gap-1.5"
                                                        >
                                                            <Checkbox
                                                                id={checkboxId}
                                                                checked={form.data.permissions.includes(perm.name)}
                                                                onCheckedChange={() => toggle(perm.name)}
                                                                className="shrink-0"
                                                            />
                                                            <span className="text-xs text-muted-foreground">{perm.label}</span>
                                                        </label>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </Card>
                        );
                    })}

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('role.index')}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                            className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50"
                        >
                            {isEditing ? 'Update Role' : 'Create Role'}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    );
}
