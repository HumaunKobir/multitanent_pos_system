import { Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Headphones,
    HelpCircle,
    Home,
    Mail,
    MessageSquare,
    Phone,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import { FaqAccordion } from '@/components/frontend/faq-accordion';
import { StoreButton } from '@/components/frontend/store-button';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

const supportPerks = [
    { icon: Truck, text: 'Nationwide delivery' },
    { icon: ShieldCheck, text: 'Secure checkout' },
    { icon: Headphones, text: 'Friendly support' },
];

export default function Faq({ items }) {
    const { siteName, contact = {}, supportTime } = usePage().props;
    const brand = siteName ?? 'POS SYSTEM';
    const { phone, email } = contact;

    return (
        <FrontendLayout>
            <Head title="FAQ" />

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
                            FAQ
                        </span>
                    </nav>

                    <div className="mt-3 max-w-xl">
                        <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/75 ring-1 ring-white/10">
                            <HelpCircle className="size-3" aria-hidden />
                            Help center
                        </span>
                        <h1 className="auth-display mt-3 text-xl font-bold text-white sm:text-2xl">
                            Frequently Asked Questions
                        </h1>
                        <p className="mt-1 text-sm text-white/75">
                            Quick answers about shopping, delivery, and orders at {brand}.
                        </p>
                    </div>
                </div>
            </section>

            <section className="store-container py-5 sm:py-6">
                <div className="overflow-hidden rounded-2xl shadow-xl shadow-store-primary/10 lg:flex">
                    <aside className="auth-panel-surface relative flex flex-col overflow-hidden p-4 sm:p-5 lg:w-[34%] xl:w-[32%]">
                        <div className="auth-grid-overlay pointer-events-none absolute inset-0" aria-hidden />
                        <div
                            className="pointer-events-none absolute left-0 top-0 h-full w-1 store-gradient opacity-90"
                            aria-hidden
                        />

                        <div className="relative z-10 flex flex-1 flex-col">
                            <div>
                                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/75 ring-1 ring-white/10">
                                    <span className="size-1.5 rounded-full bg-store-accent" />
                                    Need help?
                                </span>
                                <h2 className="auth-display mt-3 text-lg font-bold leading-snug text-white">
                                    We&apos;re here for you
                                </h2>
                                <div className="mt-2.5 flex items-center gap-2">
                                    <div className="h-0.5 w-8 rounded-full bg-store-accent" />
                                    <div className="h-px max-w-10 flex-1 bg-store-accent/30" />
                                </div>
                            </div>

                            <p className="mt-4 text-xs leading-relaxed text-white/60">
                                Can&apos;t find what you&apos;re looking for? Reach out and our team will get back to you
                                as soon as possible.
                            </p>

                            {(phone || email) && (
                                <ul className="mt-4 space-y-2">
                                    {phone && (
                                        <li>
                                            <a
                                                href={`tel:${phone.replace(/\s/g, '')}`}
                                                className="flex items-center gap-2.5 rounded-xl p-2 text-xs font-medium text-white/85 transition-colors hover:bg-white/5"
                                            >
                                                <Phone className="size-3.5 shrink-0 text-store-accent" aria-hidden />
                                                {phone}
                                            </a>
                                        </li>
                                    )}
                                    {email && (
                                        <li>
                                            <a
                                                href={`mailto:${email}`}
                                                className="flex items-center gap-2.5 rounded-xl p-2 text-xs font-medium text-white/85 transition-colors hover:bg-white/5"
                                            >
                                                <Mail className="size-3.5 shrink-0 text-store-accent" aria-hidden />
                                                {email}
                                            </a>
                                        </li>
                                    )}
                                </ul>
                            )}

                            {supportTime && (
                                <p className="mt-3 rounded-xl bg-white/5 px-3 py-2 text-[11px] leading-relaxed text-white/55 ring-1 ring-white/10">
                                    Support hours: {supportTime}
                                </p>
                            )}

                            <ul className="mt-4 space-y-2 border-t border-white/10 pt-4">
                                {supportPerks.map(({ icon: Icon, text }) => (
                                    <li key={text} className="flex items-center gap-2.5 text-xs font-medium text-white/75">
                                        <Icon className="size-3.5 shrink-0 text-store-accent" strokeWidth={2.25} aria-hidden />
                                        {text}
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-5">
                                <Link href="/contact">
                                    <StoreButton
                                        variant="outline"
                                        className="w-full rounded-xl border-white/25 bg-white/10 py-2.5 text-white hover:border-white hover:bg-white hover:text-store-primary"
                                    >
                                        <MessageSquare className="size-3.5" aria-hidden />
                                        Contact support
                                    </StoreButton>
                                </Link>
                            </div>
                        </div>
                    </aside>

                    <div className="auth-form-canvas flex flex-1 flex-col p-4 sm:p-5">
                        <div className="mx-auto w-full max-w-2xl">
                            <div className="mb-4 flex items-start gap-2">
                                <HelpCircle className="mt-0.5 size-4 shrink-0 text-store-accent" aria-hidden />
                                <div>
                                    <h2 className="text-base font-bold text-store-primary">Common questions</h2>
                                    <p className="mt-0.5 text-xs text-store-muted">
                                        Browse answers below or search for a specific topic.
                                    </p>
                                </div>
                            </div>

                            <div className="auth-card-frame rounded-xl p-px shadow-lg shadow-store-primary/10">
                                <div className="rounded-[11px] bg-white p-4 sm:p-5">
                                    <FaqAccordion items={items} />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </FrontendLayout>
    );
}
