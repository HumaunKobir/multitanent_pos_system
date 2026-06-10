import { FormField } from '@/components/form-field';
import { useAppToast } from '@/contexts/app-toast-context';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Save, ShieldCheck } from 'lucide-react';
import { useEffect } from 'react';

function SettingsSection({ title, description, children }) {
    return (
        <section className="overflow-hidden rounded-lg border bg-card">
            <div className="border-b bg-muted/30 px-5 py-3">
                <h2 className="text-sm font-semibold text-foreground">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
            </div>
            <div className="grid gap-4 px-5 py-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

export default function AdminProfileIndex({ account }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    const { data, setData, put, processing, errors } = useForm({
        name: account.name ?? '',
        email: account.email ?? '',
        phone: account.phone ?? '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const submit = (event) => {
        event.preventDefault();

        put(route('setting.admin-profile.update'), {
            preserveScroll: true,
            onSuccess: () => {
                setData('password', '');
                setData('password_confirmation', '');
            },
        });
    };

    return (
        <>
            <Head title="Admin Profile" />

            <div className="px-2 py-1">
                <div className="mb-4 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <ShieldCheck className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Admin Profile</h1>
                            <p className="text-xs text-white/60">Update your admin account email and password.</p>
                        </div>
                    </div>
                    <Button type="submit" form="admin-profile-form" disabled={processing} className="bg-emerald-600 hover:bg-emerald-700">
                        <Save className="size-4" />
                        {processing ? 'Saving...' : 'Save Profile'}
                    </Button>
                </div>

                <form id="admin-profile-form" onSubmit={submit} className="space-y-4">
                    <SettingsSection title="Account Information" description="Your super admin login credentials.">
                        <FormField label="Name" name="name" required error={errors.name}>
                            <Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1" />
                        </FormField>

                        <FormField label="Phone" name="phone" required error={errors.phone}>
                            <Input id="phone" value={data.phone} onChange={(event) => setData('phone', event.target.value)} className="mt-1" />
                        </FormField>

                        <FormField label="Email" name="email" required error={errors.email}>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <div className="hidden sm:block" />

                        <FormField label="New password" name="password" error={errors.password}>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                className="mt-1"
                                autoComplete="new-password"
                                placeholder="Leave blank to keep current password"
                            />
                        </FormField>

                        <FormField label="Confirm password" name="password_confirmation" error={errors.password_confirmation}>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(event) => setData('password_confirmation', event.target.value)}
                                className="mt-1"
                                autoComplete="new-password"
                            />
                        </FormField>
                    </SettingsSection>
                </form>
            </div>
        </>
    );
}
