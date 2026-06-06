import { useForm, usePage } from '@inertiajs/react';
import { Camera, Trash2, UserRound } from 'lucide-react';
import { useRef, useState } from 'react';

import { RequiredMark } from '@/components/form-field';
import { CustomerPanelLayout } from '@/layouts/frontend/customer-panel-layout';

export default function CustomerProfile({ customer }) {
    const { flash } = usePage().props;
    const fileInputRef = useRef(null);
    const [previewUrl, setPreviewUrl] = useState(customer.image_url ?? null);

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        name: customer.name ?? '',
        email: customer.email ?? '',
        phone: customer.phone ?? '',
        address: customer.address ?? '',
        password: '',
        password_confirmation: '',
        image: null,
        remove_image: false,
    });

    const handleImageSelect = (e) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }

        setData('image', file);
        setData('remove_image', false);
        setPreviewUrl(URL.createObjectURL(file));
    };

    const handleRemoveImage = () => {
        setData('image', null);
        setData('remove_image', true);
        setPreviewUrl(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const submit = (e) => {
        e.preventDefault();
        patch('/customer/settings', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setData('password', '');
                setData('password_confirmation', '');
                setData('image', null);
                setData('remove_image', false);
            },
        });
    };

    return (
        <CustomerPanelLayout title="Profile">
            {flash?.success && (
                <div className="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                    {flash.success}
                </div>
            )}

            <form
                onSubmit={submit}
                className="max-w-xl space-y-4 rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm"
            >
                <div className="flex flex-col items-center border-b border-gray-100 pb-4">
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="flex size-20 items-center justify-center overflow-hidden rounded-full border-2 border-gray-200 bg-store-surface transition hover:border-store-accent"
                            aria-label="Change profile photo"
                        >
                            {previewUrl ? (
                                <img src={previewUrl} alt={data.name} className="size-full object-cover" />
                            ) : (
                                <UserRound className="size-10 text-store-muted" strokeWidth={1.5} />
                            )}
                        </button>
                        <span
                            className="pointer-events-none absolute -bottom-0.5 -right-0.5 flex size-8 items-center justify-center rounded-full bg-store-accent text-white shadow-sm"
                            aria-hidden
                        >
                            <Camera className="size-4" />
                        </span>
                        {(previewUrl || customer.image_url) && (
                            <button
                                type="button"
                                onClick={handleRemoveImage}
                                className="absolute -left-1 top-0 flex size-8 items-center justify-center rounded-full border border-red-200 bg-white text-red-600 shadow-sm transition hover:bg-red-50"
                                aria-label="Remove profile photo"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        )}
                    </div>
                    {errors.image && <p className="mt-3 text-xs text-red-600">{errors.image}</p>}
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                        className="sr-only"
                        onChange={handleImageSelect}
                    />
                </div>

                <div>
                    <label htmlFor="name" className="text-sm font-semibold text-store-primary">
                        Full name
                        <RequiredMark />
                    </label>
                    <input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1 w-full rounded-lg border border-gray-200 bg-store-surface/50 px-3 py-2 text-sm text-store-primary outline-none transition focus:border-store-accent focus:ring-2 focus:ring-store-accent/20"
                        required
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label htmlFor="phone" className="text-sm font-semibold text-store-primary">
                            Phone
                            <RequiredMark />
                        </label>
                        <input
                            id="phone"
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-gray-200 bg-store-surface/50 px-3 py-2 text-sm text-store-primary outline-none transition focus:border-store-accent focus:ring-2 focus:ring-store-accent/20"
                            required
                        />
                        {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                    </div>
                    <div>
                        <label htmlFor="email" className="text-sm font-semibold text-store-primary">
                            Email
                        </label>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-gray-200 bg-store-surface/50 px-3 py-2 text-sm text-store-primary outline-none transition focus:border-store-accent focus:ring-2 focus:ring-store-accent/20"
                        />
                        {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                    </div>
                </div>

                <div>
                    <label htmlFor="address" className="text-sm font-semibold text-store-primary">
                        Address
                    </label>
                    <textarea
                        id="address"
                        rows={3}
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        className="mt-1 w-full resize-none rounded-lg border border-gray-200 bg-store-surface/50 px-3 py-2 text-sm text-store-primary outline-none transition focus:border-store-accent focus:ring-2 focus:ring-store-accent/20"
                    />
                    {errors.address && <p className="mt-1 text-xs text-red-600">{errors.address}</p>}
                </div>

                <div className="border-t border-gray-100 pt-5">
                    <p className="text-sm font-semibold text-store-primary">Change password</p>
                    <p className="mt-0.5 text-xs text-store-muted">Leave blank to keep your current password</p>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="password" className="text-xs font-medium text-store-muted">
                                New password
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-gray-200 bg-store-surface/50 px-3 py-2 text-sm outline-none transition focus:border-store-accent focus:ring-2 focus:ring-store-accent/20"
                                autoComplete="new-password"
                            />
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                        </div>
                        <div>
                            <label htmlFor="password_confirmation" className="text-xs font-medium text-store-muted">
                                Confirm password
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-gray-200 bg-store-surface/50 px-3 py-2 text-sm outline-none transition focus:border-store-accent focus:ring-2 focus:ring-store-accent/20"
                                autoComplete="new-password"
                            />
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-store-accent px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:opacity-95 disabled:opacity-60 sm:w-auto sm:min-w-[140px]"
                >
                    {processing ? 'Saving…' : recentlySuccessful ? 'Saved!' : 'Save changes'}
                </button>
            </form>
        </CustomerPanelLayout>
    );
}
