import { Head, useForm, usePage } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import { StorePageHeader } from '@/components/frontend/store-page-header';
import { StoreButton } from '@/components/frontend/store-button';
import { StoreInput } from '@/components/frontend/store-input';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Contact({ email, phone, address }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        subject: '',
        message: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/contact', { onSuccess: () => reset() });
    };

    return (
        <FrontendLayout>
            <Head title="Contact" />
            <StorePageHeader
                title="Contact Us"
                subtitle="We'd love to hear from you"
                breadcrumbs={[{ label: 'Contact Us' }]}
            />

            <div className="store-container py-8">
                <div className="grid gap-8 lg:grid-cols-5">
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                            <h2 className="mb-3 font-semibold text-store-primary">Contact information</h2>
                            <ul className="space-y-3 text-sm text-store-muted">
                                {phone && (
                                    <li className="flex items-center gap-2">
                                        <Phone className="size-4 text-store-accent" /> {phone}
                                    </li>
                                )}
                                {email && (
                                    <li className="flex items-center gap-2">
                                        <Mail className="size-4 text-store-accent" /> {email}
                                    </li>
                                )}
                                {address && (
                                    <li className="flex items-start gap-2">
                                        <MapPin className="mt-0.5 size-4 shrink-0 text-store-accent" /> {address}
                                    </li>
                                )}
                            </ul>
                        </div>
                        <div className="overflow-hidden rounded-lg">
                            <iframe
                                title="Map"
                                src="https://maps.google.com/maps?q=Dhaka,Bangladesh&output=embed"
                                className="h-48 w-full border-0"
                                loading="lazy"
                            />
                        </div>
                    </div>

                    <div className="lg:col-span-3">
                        {flash?.success && (
                            <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                                {flash.success}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-3 rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                            <StoreInput
                                label="Name *"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                error={errors.name}
                                required
                            />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <StoreInput
                                    label="Email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    error={errors.email}
                                />
                                <StoreInput
                                    label="Phone"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    error={errors.phone}
                                />
                            </div>
                            <StoreInput
                                label="Subject"
                                value={data.subject}
                                onChange={(e) => setData('subject', e.target.value)}
                                error={errors.subject}
                            />
                            <div>
                                <label className="mb-1 block text-sm font-medium text-store-primary">Message *</label>
                                <textarea
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    rows={5}
                                    className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-store-accent focus:outline-none focus:ring-2 focus:ring-store-accent/20"
                                    required
                                />
                                {errors.message && <p className="mt-1 text-xs text-store-accent">{errors.message}</p>}
                            </div>
                            <StoreButton type="submit" disabled={processing}>
                                {processing ? 'Sending...' : 'Send Message'}
                            </StoreButton>
                        </form>
                    </div>
                </div>
            </div>
        </FrontendLayout>
    );
}
