import { Form, Head, Link, usePage } from '@inertiajs/react';

import { RequiredMark } from '@/components/form-field';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { staffAuthLayout } from '@/layouts/auth/login-page-layout';
import { route, routeForm } from '@/lib/route';

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
                                    <RequiredMark />
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

Login.layout = staffAuthLayout;
