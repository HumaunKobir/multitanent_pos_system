import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    Facebook,
    Headphones,
    Home,
    Linkedin,
    Mail,
    MapPin,
    MessageSquare,
    Phone,
    ShieldCheck,
    Truck,
    Twitter,
    Youtube,
} from 'lucide-react';
import { CustomerAuthField, CustomerAuthTextarea } from '@/components/frontend/customer-auth-field';
import { CustomerAuthSubmit } from '@/components/frontend/customer-auth-submit';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

const supportPerks = [
    { icon: Truck, text: 'Fast delivery nationwide' },
    { icon: ShieldCheck, text: 'Secure checkout & payments' },
    { icon: Headphones, text: 'Dedicated customer support' },
];

const socialPlatforms = [
    { key: 'facebook', icon: Facebook, label: 'Facebook' },
    { key: 'youtube', icon: Youtube, label: 'YouTube' },
    { key: 'twitter', icon: Twitter, label: 'Twitter' },
    { key: 'linkedin', icon: Linkedin, label: 'LinkedIn' },
];

function ContactChannel({ icon: Icon, label, value, href }) {
    const inner = (
        <div className="flex items-start gap-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                <Icon className="size-3.5 text-store-accent" strokeWidth={2.25} aria-hidden />
            </span>
            <div className="min-w-0 pt-0.5">
                <p className="text-[10px] font-semibold uppercase tracking-wider text-white/50">{label}</p>
                <p className="mt-0.5 text-sm font-medium leading-snug text-white">{value}</p>
            </div>
        </div>
    );

    if (href) {
        return (
            <a href={href} className="block rounded-xl p-2 transition-colors hover:bg-white/5">
                {inner}
            </a>
        );
    }

    return <div className="rounded-xl p-2">{inner}</div>;
}

function mapEmbedSrc(address) {
    const query = encodeURIComponent(address || 'Dhaka, Bangladesh');

    return `https://maps.google.com/maps?q=${query}&output=embed`;
}

export default function Contact() {
    const { flash, siteName, contact = {}, social = {}, topNotice } = usePage().props;
    const brand = siteName ?? 'Coolness Point';
    const { phone, email, address } = contact;

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
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

    const socialLinks = socialPlatforms
        .map(({ key, icon, label }) => ({ href: social[key], icon, label }))
        .filter((item) => item.href);

    return (
        <FrontendLayout>
            <Head title="Contact" />

            <section className="relative overflow-hidden bg-store-primary">
                <div className="absolute inset-0 store-gradient opacity-35" aria-hidden />
                <div
                    className="absolute inset-0 bg-linear-to-br from-store-primary via-store-primary/90 to-[#16213e]"
                    aria-hidden
                />
                <div className="absolute inset-0 auth-grid-overlay opacity-20" aria-hidden />

                <div className="relative store-container py-4 sm:py-5">
                    <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1 text-white/60">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-1 text-xs transition-colors hover:text-white"
                        >
                            <Home className="size-3.5" aria-hidden />
                            Home
                        </Link>
                        <ChevronRight className="size-3 shrink-0" aria-hidden />
                        <span className="text-xs font-medium text-white" aria-current="page">
                            Contact
                        </span>
                    </nav>

                    <div className="mt-3 max-w-xl">
                        <h1 className="auth-display text-xl font-bold text-white sm:text-2xl">Contact Us</h1>
                        <p className="mt-1 text-sm text-white/75">
                            Questions or feedback — {brand} is here to help.
                        </p>
                    </div>
                </div>
            </section>

            <section className="store-container py-5 sm:py-6">
                <div className="overflow-hidden rounded-2xl shadow-xl shadow-store-primary/10 lg:flex">
                    <aside className="auth-panel-surface relative flex flex-col overflow-hidden p-4 sm:p-5 lg:w-[36%] xl:w-[34%]">
                        <div className="auth-grid-overlay pointer-events-none absolute inset-0" aria-hidden />
                        <div
                            className="pointer-events-none absolute left-0 top-0 h-full w-1 store-gradient opacity-90"
                            aria-hidden
                        />

                        <div className="relative z-10 flex flex-1 flex-col">
                            <div>
                                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/75 ring-1 ring-white/10">
                                    <span className="size-1.5 rounded-full bg-store-accent" />
                                    Reach us
                                </span>
                                <h2 className="auth-display mt-3 text-lg font-bold leading-snug text-white">
                                    We&apos;re here to help
                                </h2>
                                <div className="mt-2.5 flex items-center gap-2">
                                    <div className="h-0.5 w-8 rounded-full bg-store-accent" />
                                    <div className="h-px max-w-10 flex-1 bg-store-accent/30" />
                                </div>
                            </div>

                            <div className="mt-4 rounded-xl bg-white/5 p-3 ring-1 ring-white/10">
                                <div className="flex items-center gap-2.5 text-xs font-medium text-white/85">
                                    <Clock className="size-3.5 shrink-0 text-store-accent" aria-hidden />
                                    <span>Support: Sat–Thu, 10AM–8PM</span>
                                </div>
                                <p className="mt-1.5 text-[11px] leading-relaxed text-white/55">
                                    We typically reply to messages within 24 hours.
                                </p>
                            </div>

                            {topNotice && (
                                <p className="mt-3 rounded-xl bg-store-accent/15 px-3 py-2 text-xs font-medium text-white/90 ring-1 ring-store-accent/25">
                                    {topNotice}
                                </p>
                            )}

                            {channels.length > 0 ? (
                                <ul className="mt-4 space-y-0.5">
                                    {channels.map((channel) => (
                                        <li key={channel.label}>
                                            <ContactChannel {...channel} />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="mt-4 rounded-xl border border-dashed border-white/15 bg-white/5 p-3">
                                    <p className="text-sm font-medium text-white/85">Send us a message</p>
                                    <p className="mt-1 text-xs leading-relaxed text-white/55">
                                        Use the form on the right — our team will get back to you as soon as possible.
                                    </p>
                                </div>
                            )}

                            <ul className="mt-4 space-y-2 border-t border-white/10 pt-4">
                                {supportPerks.map(({ icon: Icon, text }) => (
                                    <li key={text} className="flex items-center gap-2.5 text-xs font-medium text-white/75">
                                        <Icon className="size-3.5 shrink-0 text-store-accent" strokeWidth={2.25} aria-hidden />
                                        {text}
                                    </li>
                                ))}
                            </ul>

                            {socialLinks.length > 0 && (
                                <div className="mt-4 border-t border-white/10 pt-4">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-white/45">
                                        Follow us
                                    </p>
                                    <div className="mt-2.5 flex flex-wrap gap-2">
                                        {socialLinks.map(({ href, icon: Icon, label }) => (
                                            <a
                                                key={label}
                                                href={href}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label={label}
                                                className="flex size-8 items-center justify-center rounded-lg bg-white/10 text-white/80 ring-1 ring-white/15 transition-colors hover:bg-white/15 hover:text-white"
                                            >
                                                <Icon className="size-3.5" aria-hidden />
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="mt-4 overflow-hidden rounded-xl ring-1 ring-white/15">
                                <iframe
                                    title="Store location"
                                    src={mapEmbedSrc(address)}
                                    className="h-28 w-full border-0 sm:h-32"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                    </aside>

                    <div className="auth-form-canvas flex flex-1 flex-col p-4 sm:p-5">
                        <div className="mx-auto w-full max-w-md">
                            <div className="mb-4 flex items-start gap-2">
                                <MessageSquare className="mt-0.5 size-4 shrink-0 text-store-accent" aria-hidden />
                                <div>
                                    <h2 className="text-base font-bold text-store-primary">Send a message</h2>
                                    <p className="mt-0.5 text-xs text-store-muted">
                                        Name and message are required. We&apos;ll get back to you soon.
                                    </p>
                                </div>
                            </div>

                            {flash?.success && (
                                <div
                                    role="status"
                                    className="mb-4 flex items-center gap-2 rounded-xl border border-green-200/80 bg-green-50 px-3 py-2.5 text-sm text-green-800"
                                >
                                    <CheckCircle2 className="size-4 shrink-0 text-green-600" aria-hidden />
                                    <span>{flash.success}</span>
                                </div>
                            )}

                            <div className="auth-card-frame rounded-xl p-px shadow-lg shadow-store-primary/10">
                                <form onSubmit={submit} className="space-y-3.5 rounded-[11px] bg-white p-4 sm:p-5">
                                    <CustomerAuthField
                                        label="Full name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        error={errors.name}
                                        autoComplete="name"
                                        required
                                    />

                                    <div className="grid gap-3.5 sm:grid-cols-2">
                                        <CustomerAuthField
                                            label="Email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            error={errors.email}
                                            autoComplete="email"
                                        />
                                        <CustomerAuthField
                                            label="Phone"
                                            type="tel"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            error={errors.phone}
                                            autoComplete="tel"
                                        />
                                    </div>

                                    <CustomerAuthTextarea
                                        label="Message"
                                        value={data.message}
                                        onChange={(e) => setData('message', e.target.value)}
                                        error={errors.message}
                                        rows={4}
                                        placeholder="How can we help you?"
                                        required
                                    />

                                    <CustomerAuthSubmit disabled={processing} className="py-3">
                                        {processing ? 'Sending…' : 'Send message'}
                                    </CustomerAuthSubmit>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </FrontendLayout>
    );
}
