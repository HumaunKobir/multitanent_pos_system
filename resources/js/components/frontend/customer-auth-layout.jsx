import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ArrowLeft, ShieldCheck, Sparkles, Tag, Truck } from 'lucide-react';

const panelContent = {
    login: {
        badge: 'Member access',
        watermark: '01',
        headline: (
            <>
                Shop smarter.
                <br />
                <span className="text-teal-300/95 italic">Live stylish.</span>
            </>
        ),
        description: 'Your wardrobe, your orders, your rewards — all in one place built for modern shoppers.',
    },
    register: {
        badge: 'Join free',
        watermark: '02',
        headline: (
            <>
                Join the
                <br />
                <span className="text-teal-300/95 italic">inner circle.</span>
            </>
        ),
        description: 'Create your account in seconds and unlock faster checkout, order tracking, and member perks.',
    },
};

const perks = [
    { icon: Truck, text: 'Real-time order tracking' },
    { icon: ShieldCheck, text: 'Secure & encrypted checkout' },
    { icon: Tag, text: 'Exclusive offers for members' },
];

const trustStats = [
    { value: 'Fast', label: 'Checkout' },
    { value: '100%', label: 'Secure' },
    { value: 'Free', label: 'To join' },
];

function PanelWave() {
    return (
        <svg
            viewBox="0 0 80 1200"
            preserveAspectRatio="none"
            className="pointer-events-none absolute -right-px top-0 z-20 hidden h-full w-14 lg:block xl:w-20"
            aria-hidden="true"
        >
            <path
                d="M0,0 Q40,300 20,600 T0,1200 L80,1200 L80,0 Z"
                fill="var(--auth-bg)"
            />
            <path
                d="M0,0 Q40,300 20,600 T0,1200"
                fill="none"
                stroke="rgb(255 255 255 / 0.08)"
                strokeWidth="1"
            />
        </svg>
    );
}

export function CustomerAuthLayout({
    variant = 'login',
    title,
    subtitle,
    children,
    alternatePrompt,
    alternateHref,
    alternateLabel,
}) {
    const { siteName, logo } = usePage().props;
    const panel = panelContent[variant] ?? panelContent.login;
    const brandName = siteName || 'Coolness Point';

    return (
        <div className="auth-body min-h-screen bg-auth-bg text-auth-ink">
            <Head title={title} />

            <div className="flex min-h-screen flex-col lg:flex-row">
                <aside className="auth-panel-surface relative hidden shrink-0 flex-col justify-between overflow-hidden px-10 py-12 text-white lg:flex lg:w-[44%] xl:w-[46%]">
                    <div className="auth-grid-overlay pointer-events-none absolute inset-0" aria-hidden="true" />

                    <div
                        className="auth-display pointer-events-none absolute -right-4 bottom-8 select-none text-[14rem] font-bold leading-none text-white/4 xl:text-[18rem]"
                        aria-hidden="true"
                    >
                        {panel.watermark}
                    </div>

                    <div
                        className="pointer-events-none absolute left-0 top-0 h-full w-1 bg-linear-to-b from-teal-400/80 via-teal-300/40 to-amber-400/50"
                        aria-hidden="true"
                    />

                    <div className="relative z-10">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2.5 rounded-2xl bg-white/10 px-3.5 py-2.5 ring-1 ring-white/15 backdrop-blur-sm transition-all hover:bg-white/15 hover:ring-white/25"
                        >
                            {logo ? (
                                <img
                                    src={logo}
                                    alt={brandName}
                                    className="h-8 max-w-[180px] object-contain brightness-0 invert"
                                />
                            ) : (
                                <span className="auth-display text-xl font-bold tracking-tight">{brandName}</span>
                            )}
                        </Link>
                    </div>

                    <div className="relative z-10 max-w-md">
                        <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-teal-200 ring-1 ring-white/10">
                            <span className="size-1.5 rounded-full bg-teal-300" />
                            {panel.badge}
                        </span>

                        <h2 className="auth-display mt-6 text-[2.75rem] font-bold leading-[1.08] tracking-tight text-white xl:text-5xl">
                            {panel.headline}
                        </h2>

                        <div className="mt-6 h-1 w-14 rounded-full bg-linear-to-r from-teal-400 to-amber-400/80" />

                        <p className="mt-5 max-w-[280px] text-sm leading-relaxed text-white/85">{panel.description}</p>

                        <ul className="mt-10 space-y-3.5">
                            {perks.map(({ icon: Icon, text }, index) => (
                                <motion.li
                                    key={text}
                                    initial={{ opacity: 0, x: -12 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    transition={{ delay: 0.15 + index * 0.08, duration: 0.4 }}
                                    className="flex items-center gap-3.5 text-sm font-medium text-white"
                                >
                                    <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-white/20 to-white/5 ring-1 ring-white/20">
                                        <Icon className="size-4 text-teal-200" strokeWidth={2.25} />
                                    </span>
                                    {text}
                                </motion.li>
                            ))}
                        </ul>
                    </div>

                    <div className="relative z-10 flex items-end justify-between gap-6">
                        <div className="flex gap-8">
                            {trustStats.map((stat) => (
                                <div key={stat.label}>
                                    <p className="auth-display text-2xl font-bold text-white">{stat.value}</p>
                                    <p className="mt-0.5 text-[11px] uppercase tracking-wider text-white/50">{stat.label}</p>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-white/45">
                            &copy; {new Date().getFullYear()} {brandName}
                        </p>
                    </div>

                    <div
                        className="pointer-events-none absolute -right-20 top-1/4 size-72 rounded-full bg-teal-400/25 blur-3xl"
                        aria-hidden="true"
                    />
                    <div
                        className="pointer-events-none absolute -bottom-20 left-1/4 size-80 rounded-full bg-amber-400/20 blur-3xl"
                        aria-hidden="true"
                    />

                    <PanelWave />
                </aside>

                <main className="auth-form-canvas relative flex flex-1 flex-col">
                    <header className="relative z-10 flex items-center justify-between px-4 py-4 sm:px-6 lg:px-12 lg:py-6">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 rounded-full border border-auth-border/80 bg-white/70 px-3 py-1.5 text-sm font-medium text-auth-muted shadow-sm backdrop-blur-sm transition-colors hover:border-auth-accent/30 hover:text-auth-ink lg:hidden"
                        >
                            <ArrowLeft className="size-4" />
                            Back to shop
                        </Link>
                        <Link href="/" className="hidden items-center gap-2 lg:inline-flex">
                            {logo ? (
                                <img src={logo} alt={brandName} className="h-8 object-contain" />
                            ) : (
                                <span className="auth-display text-lg font-bold text-auth-ink">{brandName}</span>
                            )}
                        </Link>
                        <Link
                            href="/"
                            className="hidden rounded-full border border-auth-border/80 bg-white/70 px-4 py-1.5 text-sm font-medium text-auth-muted shadow-sm backdrop-blur-sm transition-colors hover:border-auth-accent/30 hover:text-auth-accent lg:inline-block"
                        >
                            Continue shopping →
                        </Link>
                    </header>

                    <div className="relative z-10 flex flex-1 items-center justify-center px-4 pb-10 sm:px-6 lg:px-12 lg:pb-14">
                        <div
                            className="pointer-events-none absolute left-1/2 top-1/2 size-[min(90vw,420px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-auth-accent/10"
                            aria-hidden="true"
                        />
                        <div
                            className="pointer-events-none absolute left-1/2 top-1/2 size-[min(75vw,340px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-auth-accent/15"
                            aria-hidden="true"
                        />

                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
                            className="relative w-full max-w-[420px]"
                        >
                            <div className="mb-7 lg:mb-9">
                                <div className="mb-5 flex items-center gap-3 lg:hidden">
                                    <div className="flex size-11 items-center justify-center rounded-2xl auth-btn-gradient shadow-lg shadow-auth-accent/30">
                                        <Sparkles className="size-5 text-white" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-auth-accent">
                                            {panel.badge}
                                        </p>
                                        <p className="auth-display text-lg font-bold text-auth-ink">{brandName}</p>
                                    </div>
                                </div>

                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h1 className="auth-display text-3xl font-bold tracking-tight text-auth-ink sm:text-[2rem]">
                                            {title}
                                        </h1>
                                        {subtitle && (
                                            <p className="mt-2 max-w-sm text-sm leading-relaxed text-auth-muted">
                                                {subtitle}
                                            </p>
                                        )}
                                    </div>
                                    <span className="auth-display hidden shrink-0 text-5xl font-bold text-auth-accent/15 sm:block">
                                        {panel.watermark}
                                    </span>
                                </div>
                            </div>

                            <div className="auth-card-frame rounded-[1.35rem] p-[2px] shadow-xl shadow-auth-accent/10">
                                <div className="rounded-[1.25rem] bg-white p-6 sm:p-8">{children}</div>
                            </div>

                            {alternatePrompt && alternateHref && alternateLabel && (
                                <p className="mt-7 text-center text-sm text-auth-muted">
                                    {alternatePrompt}{' '}
                                    <Link
                                        href={alternateHref}
                                        className="font-semibold text-auth-accent underline decoration-auth-accent/30 underline-offset-4 transition-colors hover:text-auth-accent-hover hover:decoration-auth-accent"
                                    >
                                        {alternateLabel}
                                    </Link>
                                </p>
                            )}

                            <div className="mt-6 flex items-center justify-center gap-3 lg:hidden">
                                {trustStats.map((stat) => (
                                    <div
                                        key={stat.label}
                                        className="rounded-xl border border-auth-border/80 bg-white/80 px-3 py-2 text-center shadow-sm backdrop-blur-sm"
                                    >
                                        <p className="text-xs font-bold text-auth-accent">{stat.value}</p>
                                        <p className="text-[10px] text-auth-muted">{stat.label}</p>
                                    </div>
                                ))}
                            </div>
                        </motion.div>
                    </div>
                </main>
            </div>
        </div>
    );
}
