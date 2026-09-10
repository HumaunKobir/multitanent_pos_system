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
                <span className="text-store-accent italic">Live stylish.</span>
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
                <span className="text-store-accent italic">inner circle.</span>
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
                fill="var(--store-surface)"
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
    const brandName = siteName || 'POS SYSTEM';

    return (
        <div className="auth-body min-h-screen bg-store-surface font-[Inter,system-ui,sans-serif] text-store-primary">
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
                        className="pointer-events-none absolute left-0 top-0 h-full w-1 store-gradient opacity-90"
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
                        <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-white/80 ring-1 ring-white/10">
                            <span className="size-1.5 rounded-full bg-store-accent" />
                            {panel.badge}
                        </span>

                        <h2 className="auth-display mt-6 text-[2.75rem] font-bold leading-[1.08] tracking-tight text-white xl:text-5xl">
                            {panel.headline}
                        </h2>

                        <div className="mt-6 flex items-center gap-2">
                            <div className="h-1 w-10 rounded-full bg-store-accent" />
                            <div className="h-px flex-1 max-w-12 bg-store-accent/30" />
                        </div>

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
                                    <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                                        <Icon className="size-4 text-store-accent" strokeWidth={2.25} />
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
                        className="pointer-events-none absolute -right-20 top-1/4 size-72 rounded-full bg-[#667eea]/25 blur-3xl"
                        aria-hidden="true"
                    />
                    <div
                        className="pointer-events-none absolute -bottom-20 left-1/4 size-80 rounded-full bg-store-accent/20 blur-3xl"
                        aria-hidden="true"
                    />

                    <PanelWave />
                </aside>

                <main className="auth-form-canvas relative flex flex-1 flex-col">
                    <header className="relative z-10 flex items-center justify-between px-4 py-4 sm:px-6 lg:px-12 lg:py-6">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white/80 px-3 py-1.5 text-sm font-medium text-store-muted shadow-sm backdrop-blur-sm transition-colors hover:border-store-accent/40 hover:text-store-primary lg:hidden"
                        >
                            <ArrowLeft className="size-4" />
                            Back to shop
                        </Link>
                        <Link href="/" className="hidden items-center gap-2 lg:inline-flex">
                            {logo ? (
                                <img src={logo} alt={brandName} className="h-8 object-contain" />
                            ) : (
                                <span className="text-lg font-bold text-store-primary">{brandName}</span>
                            )}
                        </Link>
                        <Link
                            href="/"
                            className="hidden rounded-full border border-gray-200 bg-white/80 px-4 py-1.5 text-sm font-medium text-store-muted shadow-sm backdrop-blur-sm transition-colors hover:border-store-accent/40 hover:text-store-accent lg:inline-block"
                        >
                            Continue shopping →
                        </Link>
                    </header>

                    <div className="relative z-10 flex flex-1 items-center justify-center px-4 pb-10 sm:px-6 lg:px-12 lg:pb-14">
                        <div
                            className="pointer-events-none absolute left-1/2 top-1/2 size-[min(90vw,420px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-store-accent/10"
                            aria-hidden="true"
                        />
                        <div
                            className="pointer-events-none absolute left-1/2 top-1/2 size-[min(75vw,340px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-[#667eea]/20"
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
                                    <div className="flex size-11 items-center justify-center rounded-2xl store-gradient shadow-lg shadow-[#667eea]/30">
                                        <Sparkles className="size-5 text-white" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-store-accent">
                                            {panel.badge}
                                        </p>
                                        <p className="text-lg font-bold text-store-primary">{brandName}</p>
                                    </div>
                                </div>

                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h1 className="auth-display text-3xl font-bold tracking-tight text-store-primary sm:text-[2rem]">
                                            {title}
                                        </h1>
                                        {subtitle && (
                                            <p className="mt-2 max-w-sm text-sm leading-relaxed text-store-muted">
                                                {subtitle}
                                            </p>
                                        )}
                                    </div>
                                    <span className="auth-display hidden shrink-0 text-5xl font-bold text-store-primary/10 sm:block">
                                        {panel.watermark}
                                    </span>
                                </div>
                            </div>

                            <div className="auth-card-frame rounded-[1.35rem] p-[2px] shadow-xl shadow-store-primary/10">
                                <div className="rounded-[1.25rem] bg-white p-6 sm:p-8">{children}</div>
                            </div>

                            {alternatePrompt && alternateHref && alternateLabel && (
                                <p className="mt-7 text-center text-sm text-store-muted">
                                    {alternatePrompt}{' '}
                                    <Link
                                        href={alternateHref}
                                        className="font-semibold text-store-accent underline decoration-store-accent/30 underline-offset-4 transition-colors hover:opacity-90 hover:decoration-store-accent"
                                    >
                                        {alternateLabel}
                                    </Link>
                                </p>
                            )}

                            <div className="mt-6 flex items-center justify-center gap-3 lg:hidden">
                                {trustStats.map((stat) => (
                                    <div
                                        key={stat.label}
                                        className="rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-center shadow-sm"
                                    >
                                        <p className="text-xs font-bold text-store-accent">{stat.value}</p>
                                        <p className="text-[10px] text-store-muted">{stat.label}</p>
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
