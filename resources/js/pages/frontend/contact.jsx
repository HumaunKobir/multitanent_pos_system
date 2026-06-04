import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChevronRight, Home, Mail, MapPin, MessageSquare, Phone, Send } from 'lucide-react';
import { StoreButton } from '@/components/frontend/store-button';
import { StoreInput } from '@/components/frontend/store-input';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

function ContactChannel({ icon: Icon, label, value, href }) {
    const content = (
        <div className="flex items-center gap-3 rounded-xl border border-gray-100/80 bg-white px-3.5 py-2.5 shadow-sm transition-colors hover:border-store-accent/25">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg store-gradient text-white shadow-sm">
                <Icon className="size-4" aria-hidden />
            </span>
            <div className="min-w-0">
                <p className="text-[10px] font-semibold uppercase tracking-wider text-store-muted">{label}</p>
                <p className="truncate text-sm font-medium text-store-primary">{value}</p>
            </div>
        </div>
    );

    if (href) {
        return (
            <a href={href} className="block">
                {content}
            </a>
        );
    }

    return content;
}

export default function Contact({ email, phone, address }) {
    const { flash, siteName } = usePage().props;
    const brand = siteName ?? 'Coolness Point';
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

    const channels = [
        phone && { icon: Phone, label: 'Phone', value: phone, href: `tel:${phone.replace(/\s/g, '')}` },
        email && { icon: Mail, label: 'Email', value: email, href: `mailto:${email}` },
        address && { icon: MapPin, label: 'Address', value: address },
    ].filter(Boolean);

    return (
        <FrontendLayout>
            <Head title="Contact" />

            <section className="relative overflow-hidden bg-store-primary">
                <div className="absolute inset-0 store-gradient opacity-40" aria-hidden />
                <div
                    className="absolute inset-0 bg-linear-to-br from-store-primary via-store-primary/90 to-[#16213e]"
                    aria-hidden
                />
                <div className="absolute inset-0 auth-grid-overlay opacity-25" aria-hidden />

                <div className="relative store-container py-6 sm:py-8">
                    <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1 text-white/60">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-1 text-xs transition-colors hover:text-white sm:text-sm"
                        >
                            <Home className="size-3.5" aria-hidden />
                            Home
                        </Link>
                        <ChevronRight className="size-3 shrink-0" aria-hidden />
                        <span className="text-xs font-medium text-white sm:text-sm" aria-current="page">
                            Contact
                        </span>
                    </nav>

                    <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-store-accent">
                                Get in touch
                            </p>
                            <h1 className="mt-1 text-2xl font-bold text-white sm:text-3xl">Contact Us</h1>
                            <p className="mt-2 max-w-md text-sm leading-relaxed text-white/75">
                                Questions, feedback, or partnership — {brand} is here to help.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs text-white/80 backdrop-blur-sm">
                            <MessageSquare className="size-3.5 text-store-accent" aria-hidden />
                            <span>We typically reply within 24 hours</span>
                        </div>
                    </div>
                </div>
            </section>

            <section className="store-container -mt-5 pb-10 sm:-mt-6 sm:pb-12">
                <div className="rounded-2xl p-px auth-card-frame shadow-lg shadow-store-primary/10">
                    <div className="overflow-hidden rounded-[15px] bg-white">
                        <div className="grid lg:grid-cols-5">
                            <aside className="border-b border-gray-100 bg-store-surface/60 p-5 sm:p-6 lg:col-span-2 lg:border-b-0 lg:border-r">
                                <h2 className="text-sm font-bold text-store-primary">Reach us directly</h2>
                                <div className="mt-1 h-0.5 w-8 rounded-full store-gradient" aria-hidden />

                                {channels.length > 0 ? (
                                    <ul className="mt-4 space-y-2.5">
                                        {channels.map((channel) => (
                                            <li key={channel.label}>
                                                <ContactChannel {...channel} />
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-4 text-sm text-store-muted">
                                        Contact details will appear here once configured.
                                    </p>
                                )}

                                <div className="mt-5 overflow-hidden rounded-xl border border-gray-100 shadow-sm">
                                    <iframe
                                        title="Store location"
                                        src="https://maps.google.com/maps?q=Dhaka,Bangladesh&output=embed"
                                        className="h-36 w-full border-0 sm:h-40"
                                        loading="lazy"
                                    />
                                </div>
                            </aside>

                            <div className="auth-form-canvas p-5 sm:p-6 lg:col-span-3">
                                {flash?.success && (
                                    <div
                                        role="status"
                                        className="mb-4 flex items-center gap-2 rounded-lg border border-green-200/80 bg-green-50 px-3.5 py-2.5 text-sm text-green-800"
                                    >
                                        <span className="size-1.5 shrink-0 rounded-full bg-green-500" aria-hidden />
                                        {flash.success}
                                    </div>
                                )}

                                <div className="mb-4">
                                    <h2 className="text-sm font-bold text-store-primary">Send a message</h2>
                                    <p className="mt-0.5 text-xs text-store-muted">
                                        Fill in the form below and we&apos;ll get back to you soon.
                                    </p>
                                </div>

                                <form onSubmit={submit} className="space-y-3">
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
                                        <label
                                            htmlFor="contact-message"
                                            className="mb-1 block text-sm font-medium text-store-primary"
                                        >
                                            Message *
                                        </label>
                                        <textarea
                                            id="contact-message"
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            rows={4}
                                            className="w-full resize-y rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-store-primary placeholder:text-gray-400 focus:border-store-accent focus:outline-none focus:ring-2 focus:ring-store-accent/20"
                                            required
                                        />
                                        {errors.message && (
                                            <p className="mt-1 text-xs text-store-accent">{errors.message}</p>
                                        )}
                                    </div>
                                    <StoreButton
                                        type="submit"
                                        disabled={processing}
                                        className="auth-btn-shine w-full rounded-lg sm:w-auto sm:min-w-[160px]"
                                    >
                                        <Send className="size-4" aria-hidden />
                                        {processing ? 'Sending…' : 'Send Message'}
                                    </StoreButton>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </FrontendLayout>
    );
}
