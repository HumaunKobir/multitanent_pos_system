import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ArrowLeft, ShieldCheck, Sparkles, Truck } from 'lucide-react';

const perks = [
    { icon: Truck, text: 'Track orders in real time' },
    { icon: ShieldCheck, text: 'Secure checkout & account' },
    { icon: Sparkles, text: 'Exclusive member offers' },
];

export function CustomerAuthLayout({
    title,
    subtitle,
    children,
    alternatePrompt,
    alternateHref,
    alternateLabel,
}) {
    const { siteName, logo } = usePage().props;

    return (
        <div className="min-h-screen bg-auth-bg font-[Inter,system-ui,sans-serif] text-auth-ink">
            <Head title={title} />

            <div className="flex min-h-screen flex-col lg:flex-row">
                <aside className="auth-panel-surface relative hidden shrink-0 flex-col justify-between overflow-hidden px-10 py-12 text-white lg:flex lg:w-[42%] xl:w-[44%]">
                    <div className="relative z-10">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2.5 rounded-xl bg-white/10 px-3 py-2 backdrop-blur-sm transition-colors hover:bg-white/15"
                        >
                            {logo ? (
                                <img
                                    src={logo}
                                    alt={siteName}
                                    className="h-8 max-w-[180px] object-contain brightness-0 invert"
                                />
                            ) : (
                                <span className="text-xl font-bold tracking-tight text-white">
                                    {siteName || 'Coolness Point'}
                                </span>
                            )}
                        </Link>
                    </div>

                    <div className="relative z-10 max-w-sm">
                        <p className="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-teal-200/90">
                            Welcome back
                        </p>
                        <h2 className="text-3xl font-bold leading-tight text-white drop-shadow-sm xl:text-4xl">
                            Your style journey starts here
                        </h2>
                        <p className="mt-4 text-sm leading-relaxed text-white/90">
                            Sign in or create an account to manage orders, save favorites, and enjoy a smoother
                            shopping experience.
                        </p>
                        <ul className="mt-8 space-y-4">
                            {perks.map(({ icon: Icon, text }) => (
                                <li key={text} className="flex items-center gap-3 text-sm font-medium text-white">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                                        <Icon className="size-4 text-teal-300" strokeWidth={2.25} />
                                    </span>
                                    {text}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative z-10 text-xs text-white/60">
                        &copy; {new Date().getFullYear()} {siteName || 'Coolness Point'}
                    </p>

                    <div
                        className="pointer-events-none absolute -right-24 -top-24 size-80 rounded-full bg-teal-400/20 blur-3xl"
                        aria-hidden="true"
                    />
                    <div
                        className="pointer-events-none absolute -bottom-32 -left-16 size-96 rounded-full bg-amber-400/15 blur-3xl"
                        aria-hidden="true"
                    />
                </aside>

                <main className="flex flex-1 flex-col">
                    <header className="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-10 lg:py-6">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 text-sm font-medium text-auth-muted transition-colors hover:text-auth-ink lg:hidden"
                        >
                            <ArrowLeft className="size-4" />
                            Back to shop
                        </Link>
                        <Link href="/" className="hidden items-center gap-2 lg:inline-flex">
                            {logo ? (
                                <img src={logo} alt={siteName} className="h-8 object-contain" />
                            ) : (
                                <span className="text-lg font-bold text-auth-ink">{siteName || 'Coolness Point'}</span>
                            )}
                        </Link>
                        <Link
                            href="/"
                            className="hidden text-sm font-medium text-auth-muted transition-colors hover:text-auth-accent lg:inline-block"
                        >
                            Continue shopping
                        </Link>
                    </header>

                    <div className="flex flex-1 items-center justify-center px-4 pb-8 sm:px-6 lg:px-10 lg:pb-12">
                        <motion.div
                            initial={{ opacity: 0, y: 16 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.35, ease: 'easeOut' }}
                            className="w-full max-w-md"
                        >
                            <div className="mb-8 lg:mb-10">
                                <div className="mb-4 flex size-12 items-center justify-center rounded-2xl auth-btn-gradient shadow-lg shadow-auth-accent/25 lg:hidden">
                                    <Sparkles className="size-5 text-white" />
                                </div>
                                <h1 className="text-2xl font-bold tracking-tight text-auth-ink sm:text-3xl">{title}</h1>
                                {subtitle && <p className="mt-2 text-sm text-auth-muted sm:text-base">{subtitle}</p>}
                            </div>

                            <div className="rounded-2xl border border-auth-border bg-white p-6 shadow-sm shadow-stone-200/60 sm:p-8">
                                {children}
                            </div>

                            {alternatePrompt && alternateHref && alternateLabel && (
                                <p className="mt-6 text-center text-sm text-auth-muted">
                                    {alternatePrompt}{' '}
                                    <Link
                                        href={alternateHref}
                                        className="font-semibold text-auth-accent transition-colors hover:text-auth-accent-hover"
                                    >
                                        {alternateLabel}
                                    </Link>
                                </p>
                            )}

                            <Link
                                href="/"
                                className="mt-4 block text-center text-xs text-auth-muted transition-colors hover:text-auth-accent lg:hidden"
                            >
                                ← Back to shop
                            </Link>
                        </motion.div>
                    </div>
                </main>
            </div>
        </div>
    );
}
