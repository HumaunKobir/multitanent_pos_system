import { Link, usePage } from '@inertiajs/react';
import { Facebook, Linkedin, Twitter, Youtube } from 'lucide-react';
import { FooterNewsletter } from '@/components/frontend/footer-newsletter';

export function StoreFooter() {
    const { siteName, contact = {}, social = {}, logo } = usePage().props;

    const shopLinks = [
        { href: '/about', label: 'About Us' },
        { href: '/contact', label: 'Contact' },
        { href: '/faq', label: 'FAQ' },
        { href: '/size-guide', label: 'Size Guide' },
    ];

    const legalLinks = [
        { href: '/refund-policy', label: 'Refund Policy' },
        { href: '/cancellation-policy', label: 'Cancellation Policy' },
        { href: '/privacy-policy', label: 'Privacy Policy' },
        { href: '/terms-policy', label: 'Terms of Service' },
    ];

    const socialLinks = [
        { href: social.facebook, icon: Facebook, label: 'Facebook' },
        { href: social.youtube, icon: Youtube, label: 'YouTube' },
        { href: social.twitter, icon: Twitter, label: 'Twitter' },
        { href: social.linkedin, icon: Linkedin, label: 'LinkedIn' },
    ].filter((s) => s.href);

    return (
        <footer className="bg-store-primary text-white">
            <div className="store-container grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    {logo ? (
                        <img src={logo} alt={siteName} className="mb-3 h-8 object-contain brightness-0 invert" />
                    ) : (
                        <h3 className="mb-3 text-sm font-bold">{siteName || 'Coolness Point'}</h3>
                    )}
                    <p className="text-sm text-white/70">Your destination for fashion and apparel.</p>

                    {(contact.phone || contact.email || contact.address) && (
                        <ul className="mt-4 space-y-1.5 text-sm text-white/70">
                            {contact.phone && <li>{contact.phone}</li>}
                            {contact.email && (
                                <li>
                                    <a href={`mailto:${contact.email}`} className="hover:text-white">
                                        {contact.email}
                                    </a>
                                </li>
                            )}
                            {contact.address && <li className="text-white/55">{contact.address}</li>}
                        </ul>
                    )}

                    {socialLinks.length > 0 && (
                        <div className="mt-4 flex gap-2">
                            {socialLinks.map(({ href, icon: Icon, label }) => (
                                <a
                                    key={label}
                                    href={href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex size-8 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-store-accent"
                                    aria-label={label}
                                >
                                    <Icon className="size-4" />
                                </a>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-white/50">Shop</h3>
                    <ul className="space-y-2 text-sm text-white/80">
                        {shopLinks.map((l) => (
                            <li key={l.href}>
                                <Link href={l.href} className="hover:text-white">
                                    {l.label}
                                </Link>
                            </li>
                        ))}
                        <li>
                            <Link href="/customer/login" className="hover:text-white">
                                Login
                            </Link>
                        </li>
                        <li>
                            <Link href="/customer/orders" className="hover:text-white">
                                My Orders
                            </Link>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-white/50">Policies</h3>
                    <ul className="space-y-2 text-sm text-white/80">
                        {legalLinks.map((l) => (
                            <li key={l.href}>
                                <Link href={l.href} className="hover:text-white">
                                    {l.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="sm:col-span-2 lg:col-span-1 lg:justify-self-end">
                    <FooterNewsletter />
                </div>
            </div>
            <div className="border-t border-white/10 py-4 text-center text-xs text-white/50">
                &copy; {new Date().getFullYear()} {siteName || 'Coolness Point'}. All rights reserved.
            </div>
        </footer>
    );
}
