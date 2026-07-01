import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

import { RequiredMark } from '@/components/form-field';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { staffAuthLayout } from '@/layouts/auth/login-page-layout';
import { route, routeForm } from '@/lib/route';

export default function Login({ status, canResetPassword, canRegister }) {
    const [loginMode, setLoginMode] = useState(null);

    return (
        <>
            <Head title="Log in" />

            {loginMode === null ? (
                <LoginModeSelection onSelect={setLoginMode} />
            ) : (
                <LoginForm
                    status={status}
                    canResetPassword={canResetPassword}
                    canRegister={canRegister}
                    startBusinessSession={loginMode === 'with_session'}
                    onBack={() => setLoginMode(null)}
                />
            )}
        </>
    );
}

function LoginModeSelection({ onSelect }) {
    return (
        <>
            <div className="mb-8">
                <p className="text-[11px] text-indigo-500 uppercase tracking-[0.2em] font-semibold mb-2">
                    Account access
                </p>
                <h1 className="text-[1.75rem] font-black tracking-tight leading-tight">
                    Choose how to sign in
                </h1>
                <p className="text-muted-foreground text-[13px] mt-1.5">
                    Start a daily business session or continue without one
                </p>
            </div>

            <div className="flex flex-col gap-3">
                <button
                    type="button"
                    onClick={() => onSelect('with_session')}
                    className="w-full border border-indigo-200 bg-indigo-50 px-4 py-4 text-left transition hover:border-indigo-400 hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950/40 dark:hover:bg-indigo-950"
                >
                    <p className="text-sm font-bold text-indigo-900 dark:text-indigo-100">Start Session and Login</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Opens a business session and records opening balances when you sign in.
                    </p>
                </button>

                <button
                    type="button"
                    onClick={() => onSelect('without_session')}
                    className="w-full border border-border bg-card px-4 py-4 text-left transition hover:border-indigo-300 hover:bg-muted/40"
                >
                    <p className="text-sm font-bold">Login Without Starting Session</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Sign in normally. You can start a session later from the panel.
                    </p>
                </button>
            </div>
        </>
    );
}

function LoginForm({ status, canResetPassword, canRegister, startBusinessSession, onBack }) {
    return (
        <>
            <div className="mb-8">
                <button
                    type="button"
                    onClick={onBack}
                    className="mb-3 text-xs font-medium text-indigo-600 hover:underline"
                >
                    ← Change login mode
                </button>
                <p className="text-[11px] text-indigo-500 uppercase tracking-[0.2em] font-semibold mb-2">
                    {startBusinessSession ? 'Session + login' : 'Login only'}
                </p>
                <h1 className="text-[1.75rem] font-black tracking-tight leading-tight">
                    Sign in
                </h1>
                <p className="text-muted-foreground text-[13px] mt-1.5">
                    {startBusinessSession
                        ? 'A business session will start after successful login.'
                        : 'No business session will be created automatically.'}
                </p>
            </div>

            {status ? (
                <div className="mb-6 text-sm text-green-700 bg-green-50 border-l-4 border-green-500 px-4 py-3">
                    {status}
                </div>
            ) : null}

            <Form {...routeForm('login.store')} resetOnSuccess={['password']} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="start_business_session" value={startBusinessSession ? '1' : '0'} />

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

                        <div className="flex items-center gap-2.5">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                tabIndex={3}
                                className="size-3.5 border border-input accent-indigo-600 cursor-pointer"
                            />
                            <Label htmlFor="remember" className="text-[13px] text-muted-foreground font-normal cursor-pointer">
                                Keep me signed in
                            </Label>
                        </div>

                        <button
                            type="submit"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                            className="mt-2 w-full h-11 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold text-[13.5px] tracking-wide transition-colors duration-150 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            {processing ? <Spinner /> : <span>Sign in</span>}
                        </button>

                        {canRegister ? (
                            <p className="text-center text-[13px] text-muted-foreground mt-1">
                                Don&apos;t have an account?{' '}
                                <TextLink href={route('register')} tabIndex={6} className="font-semibold text-indigo-600 no-underline hover:underline">
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
