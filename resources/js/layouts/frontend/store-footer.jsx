import { Link, usePage } from '@inertiajs/react';
import {
    Clock,
    Facebook,
    Linkedin,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
    Truck,
    Twitter,
    Youtube,
} from 'lucide-react';
import { FooterNewsletter } from '@/components/frontend/footer-newsletter';

const shopLinks = [
    { href: '/about', label: 'About Us' },
    { href: '/contact', label: 'Contact' },
    { href: '/faq', label: 'FAQ' },
    { href: '/customer/login', label: 'Login' },
    { href: '/customer/orders', label: 'My Orders' },
];

const legalLinks = [
    { href: '/refund-policy', label: 'Refund Policy' },
    { href: '/cancellation-policy', label: 'Cancellation Policy' },
    { href: '/privacy-policy', label: 'Privacy Policy' },
    { href: '/terms-policy', label: 'Terms of Service' },
];

const trustHighlights = [
    { icon: Truck, label: 'Fast delivery nationwide' },
    { icon: ShieldCheck, label: 'Secure checkout & payments' },
    { icon: Clock, label: 'Support: Sat–Thu, 10AM–8PM' },
];

const socialPlatforms = [
    { key: 'facebook', icon: Facebook, label: 'Facebook' },
    { key: 'youtube', icon: Youtube, label: 'YouTube' },
    { key: 'twitter', icon: Twitter, label: 'Twitter' },
    { key: 'linkedin', icon: Linkedin, label: 'LinkedIn' },
];

function FooterSectionCard({ badge, title, children, className = '' }) {
    return (
        <div className={`rounded-2xl bg-white/5 p-5 ring-1 ring-white/10 backdrop-blur-sm ${className}`}>
            <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-white/70 ring-1 ring-white/10">
                <span className="size-1.5 rounded-full bg-store-accent" />
                {badge}
            </span>
            <h3 className="auth-display mt-3 text-sm font-bold tracking-tight text-white">{title}</h3>
            <div className="mt-2 flex items-center gap-2">
                <div className="h-0.5 w-8 rounded-full bg-store-accent" />
                <div className="h-px max-w-10 flex-1 bg-store-accent/30" />
            </div>
            <div className="mt-4">{children}</div>
        </div>
    );
}

function FooterFollowUs({ socialLinks }) {
    return (
        <div className="mt-6 max-w-xs border-t border-white/10 pt-6 lg:text-left">
            <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/50">Follow us</p>
            <div className="mt-3 flex flex-wrap gap-2">
                {socialLinks.map(({ href, icon: Icon, label }) =>
                    href ? (
                        <a
                            key={label}
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex size-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15 transition-all hover:bg-store-accent hover:ring-store-accent/40"
                            aria-label={label}
                        >
                            <Icon className="size-4" />
                        </a>
                    ) : (
                        <span
                            key={label}
                            className="flex size-10 cursor-default items-center justify-center rounded-xl bg-white/5 text-white/25 ring-1 ring-white/10"
                            aria-label={`${label} (not configured)`}
                            title={`${label} — link not set`}
                        >
                            <Icon className="size-4" />
                        </span>
                    ),
                )}
            </div>
        </div>
    );
}

function FooterLinkList({ links }) {
    return (
        <ul className="space-y-2.5">
            {links.map((link) => (
                <li key={link.href}>
                    <Link
                        href={link.href}
                        className="group inline-flex items-center gap-2 text-sm text-white/80 transition-colors hover:text-white"
                    >
                        <span className="size-1 shrink-0 rounded-full bg-store-accent/60 transition-transform group-hover:scale-125" />
                        {link.label}
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export function StoreFooter() {
    const { siteName, contact = {}, social = {}, logo, topNotice } = usePage().props;
    const brandName = siteName || 'Coolness Point';

    const socialLinks = socialPlatforms.map(({ key, icon, label }) => ({
        href: social[key],
        icon,
        label,
    }));

    return (
        <footer className="relative overflow-hidden bg-store-primary text-white">
            <div className="auth-grid-overlay pointer-events-none absolute inset-0 opacity-25" aria-hidden="true" />

            <div className="store-container relative z-10 py-12 lg:py-14">
                <div className="grid gap-8 lg:grid-cols-12 lg:gap-6 xl:gap-8">
                    <div className="lg:col-span-4">
                        <div className="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10 backdrop-blur-sm">
                            <Link
                                href="/"
                                className="inline-flex items-center rounded-2xl bg-white/10 px-3.5 py-2.5 ring-1 ring-white/15 transition-all hover:bg-white/15 hover:ring-white/25"
                            >
                                {logo ? (
                                    <img
                                        src={logo}
                                        alt={brandName}
                                        className="h-8 max-w-[180px] object-contain brightness-0 invert"
                                    />
                                ) : (
                                    <span className="auth-display text-lg font-bold tracking-tight">{brandName}</span>
                                )}
                            </Link>

                            <p className="mt-4 text-sm leading-relaxed text-white/75">
                                Your destination for fashion and apparel — quality products, reliable delivery, and a
                                shopping experience built on trust.
                            </p>

                            {topNotice && (
                                <p className="mt-3 rounded-xl bg-store-accent/15 px-3 py-2 text-xs font-medium text-white/90 ring-1 ring-store-accent/25">
                                    {topNotice}
                                </p>
                            )}

                            <ul className="mt-5 space-y-3 text-sm text-white/80">
                                {contact.phone && (
                                    <li className="flex items-start gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                            <Phone className="size-4 text-store-accent" strokeWidth={2.25} />
                                        </span>
                                        <span className="pt-1.5">{contact.phone}</span>
                                    </li>
                                )}
                                {contact.email && (
                                    <li className="flex items-start gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                            <Mail className="size-4 text-store-accent" strokeWidth={2.25} />
                                        </span>
                                        <a href={`mailto:${contact.email}`} className="pt-1.5 transition-colors hover:text-white">
                                            {contact.email}
                                        </a>
                                    </li>
                                )}
                                {contact.address && (
                                    <li className="flex items-start gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                            <MapPin className="size-4 text-store-accent" strokeWidth={2.25} />
                                        </span>
                                        <span className="pt-1.5 leading-relaxed text-white/65">{contact.address}</span>
                                    </li>
                                )}
                            </ul>

                            <ul className="mt-6 space-y-2.5 border-t border-white/10 pt-5">
                                {trustHighlights.map(({ icon: Icon, label }) => (
                                    <li key={label} className="flex items-center gap-2.5 text-xs font-medium text-white/70">
                                        <Icon className="size-3.5 shrink-0 text-store-accent" strokeWidth={2.25} />
                                        {label}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-2">
                        <FooterSectionCard badge="Explore" title="Shop">
                            <FooterLinkList links={shopLinks} />
                        </FooterSectionCard>

                        <FooterSectionCard badge="Legal" title="Policies">
                            <FooterLinkList links={legalLinks} />
                        </FooterSectionCard>
                    </div>

                    <div className="lg:col-span-3 lg:justify-self-end">
                        <FooterNewsletter />
                        <FooterFollowUs socialLinks={socialLinks} />
                    </div>
                </div>

                <div className="mt-10 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 text-xs text-white/50 sm:flex-row sm:items-center">
                    <p>
                        &copy; {new Date().getFullYear()} {brandName}. All rights reserved.
                    </p>
                    <p className="text-center sm:text-right">
                        Powered by{' '}
                        <a
                            href="https://mmitsoftltd.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-semibold text-white/70 underline decoration-white/20 underline-offset-4 transition-colors hover:text-white hover:decoration-store-accent"
                        >
                            MM IT Soft Ltd
                        </a>
                    </p>
                </div>
            </div>
        </footer>
    );
}
