import { Form, Head, Link, usePage } from '@inertiajs/react';

import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { route, routeForm } from '@/lib/route';

function LoginPageLayout({ children }) {
    const { name } = usePage().props;

    return (
        <div className="min-h-svh flex bg-white dark:bg-zinc-950">
            {/* ── Strip: vertical brand accent ── */}
            <div className="hidden lg:flex w-[72px] shrink-0 bg-indigo-600 flex-col items-center justify-between py-10 select-none">
                <span
                    className="text-white font-black text-[11px] tracking-[0.3em] uppercase"
                    style={{ writingMode: 'vertical-rl', transform: 'rotate(180deg)' }}
                >
                    {name}
                </span>

                <div className="flex flex-col items-center gap-3">
                    {[...Array(6)].map((_, i) => (
                        <div key={i} className="w-1 h-1 rounded-full bg-white/30" />
                    ))}
                </div>

                <span
                    className="text-white/30 text-[9px] tracking-widest"
                    style={{ writingMode: 'vertical-rl', transform: 'rotate(180deg)' }}
                >
                    © {new Date().getFullYear()}
                </span>
            </div>

            {/* ── Center: dark hero panel ── */}
            <div className="hidden lg:flex lg:w-[42%] shrink-0 bg-zinc-950 flex-col justify-between px-14 py-14 select-none overflow-hidden relative">
                {/* Faint grid lines */}
                <div
                    className="absolute inset-0 opacity-100"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px)',
                        backgroundSize: '60px 60px',
                    }}
                />

                {/* Large faint number in background */}
                <div
                    className="absolute -right-6 bottom-16 text-white/[0.03] font-black select-none pointer-events-none leading-none"
                    style={{ fontSize: '22rem' }}
                    aria-hidden="true"
                >
                    01
                </div>

                {/* Top */}
                <div className="relative z-10">
                    <span className="inline-flex items-center gap-2 text-[10px] text-indigo-400 uppercase tracking-[0.22em] font-semibold">
                        <span className="w-4 h-px bg-indigo-400 inline-block" />
                        Point of Sale
                    </span>
                </div>

                {/* Headline */}
                <div className="relative z-10">
                    <h2 className="text-white font-black leading-[1.04] tracking-tight" style={{ fontSize: '3.4rem' }}>
                        Sell more.
                        <br />
                        Track
                        <br />
                        everything.
                    </h2>
                    <div className="mt-8 h-[3px] w-12 bg-indigo-600" />
                    <p className="mt-5 text-zinc-400 text-[13.5px] leading-relaxed max-w-[260px]">
                        Inventory, sales, and customers — all in one dashboard built for modern businesses.
                    </p>
                </div>

                {/* Stats row */}
                <div className="relative z-10 flex gap-8">
                    {[
                        { value: '100%', label: 'Uptime' },
                        { value: '< 1s', label: 'Load time' },
                        { value: '∞', label: 'Products' },
                    ].map((stat) => (
                        <div key={stat.label}>
                            <p className="text-white font-bold text-xl">{stat.value}</p>
                            <p className="text-zinc-500 text-[11px] mt-0.5">{stat.label}</p>
                        </div>
                    ))}
                </div>
            </div>

            {/* ── Right: form panel ── */}
            <div className="flex-1 flex flex-col items-center justify-center bg-white dark:bg-zinc-900 px-8 py-12 relative">
                {/* Top-right corner accent */}
                <div className="absolute top-0 right-0 w-24 h-24 bg-indigo-50 dark:bg-indigo-950/30" aria-hidden="true" />
                <div className="absolute top-0 right-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30" aria-hidden="true" />

                {/* Mobile brand */}
                <Link
                    href={route('home')}
                    className="mb-10 font-black text-lg tracking-tight lg:hidden"
                >
                    {name}
                </Link>

                <div className="w-full max-w-sm relative z-10">{children}</div>
            </div>
        </div>
    );
}

export default function Login({ status, canResetPassword, canRegister }) {
    return (
        <>
            <Head title="Log in" />

            {/* Heading */}
            <div className="mb-8">
                <p className="text-[11px] text-indigo-500 uppercase tracking-[0.2em] font-semibold mb-2">
                    Account access
                </p>
                <h1 className="text-[1.75rem] font-black tracking-tight leading-tight">
                    Sign in
                </h1>
                <p className="text-muted-foreground text-[13px] mt-1.5">
                    Enter your email and password below
                </p>
            </div>

            {/* Status */}
            {status ? (
                <div className="mb-6 text-sm text-green-700 bg-green-50 border-l-4 border-green-500 px-4 py-3">
                    {status}
                </div>
            ) : null}

            <Form {...routeForm('login.store')} resetOnSuccess={['password']} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        {/* Email */}
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="email" className="text-[12.5px] font-semibold tracking-wide uppercase text-muted-foreground">
                                Email
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="you@example.com"
                                className="h-11 text-sm bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                            />
                            <InputError message={errors.email} />
                        </div>

                        {/* Password */}
                        <div className="flex flex-col gap-1.5">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password" className="text-[12.5px] font-semibold tracking-wide uppercase text-muted-foreground">
                                    Password
                                </Label>
                                {canResetPassword ? (
                                    <TextLink
                                        href={route('password.request')}
                                        className="text-[12px] text-indigo-500 hover:text-indigo-600 no-underline"
                                        tabIndex={5}
                                    >
                                        Forgot?
                                    </TextLink>
                                ) : null}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="••••••••"
                                className="h-11 text-sm bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                            />
                            <InputError message={errors.password} />
                        </div>

                        {/* Remember me */}
                        <div className="flex items-center gap-2.5">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                tabIndex={3}
                                className="size-3.5 border border-input accent-indigo-600 cursor-pointer"
                            />
                            <Label
                                htmlFor="remember"
                                className="text-[13px] text-muted-foreground font-normal cursor-pointer"
                            >
                                Keep me signed in
                            </Label>
                        </div>

                        {/* Submit */}
                        <button
                            type="submit"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                            className="mt-2 w-full h-11 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold text-[13.5px] tracking-wide transition-colors duration-150 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <>
                                    <span>Sign in</span>
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="size-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth={2.5}
                                        aria-hidden="true"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </>
                            )}
                        </button>

                        {/* Register */}
                        {canRegister ? (
                            <p className="text-center text-[13px] text-muted-foreground mt-1">
                                Don&apos;t have an account?{' '}
                                <TextLink
                                    href={route('register')}
                                    tabIndex={6}
                                    className="font-semibold text-indigo-600 no-underline hover:underline"
                                >
                                    Create one →
                                </TextLink>
                            </p>
                        ) : null}
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = (page) => <LoginPageLayout>{page}</LoginPageLayout>;
