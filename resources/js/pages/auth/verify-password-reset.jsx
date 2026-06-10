import { Form, Head } from '@inertiajs/react';

import InputError from '@/components/input-error';
import { StaffPasswordResetSteps } from '@/components/password-reset-steps';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { staffAuthLayout } from '@/layouts/auth/login-page-layout';
import { route, routeForm } from '@/lib/route';

export default function VerifyPasswordReset({ email, status }) {
    return (
        <>
            <Head title="Verify code" />

            <StaffPasswordResetSteps currentStep={2} />

            <div className="mb-8">
                <p className="text-[11px] text-indigo-500 uppercase tracking-[0.2em] font-semibold mb-2">
                    Step 2 of 3
                </p>
                <h1 className="text-[1.75rem] font-black tracking-tight leading-tight">
                    Verify your code
                </h1>
                <p className="text-muted-foreground text-[13px] mt-1.5">
                    Enter the 6-digit code sent to your email
                </p>
            </div>

            {status ? (
                <div className="mb-6 text-sm text-green-700 bg-green-50 border-l-4 border-green-500 px-4 py-3">
                    {status}
                </div>
            ) : null}

            <Form {...routeForm('password.verify.store')} resetOnError={['otp']} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="email" value={email} />

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="email-display" className="text-[12.5px] font-semibold tracking-wide uppercase text-muted-foreground">
                                Email
                            </Label>
                            <Input
                                id="email-display"
                                type="email"
                                value={email}
                                readOnly
                                className="h-11 text-sm border-zinc-200 dark:border-zinc-700"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="otp" className="text-[12.5px] font-semibold tracking-wide uppercase text-muted-foreground">
                                Verification code
                            </Label>
                            <Input
                                id="otp"
                                name="otp"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                required
                                autoFocus
                                placeholder="000000"
                                className="h-11 text-sm tracking-[0.35em] text-center font-mono bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                            />
                            <InputError message={errors.otp} />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            data-test="verify-password-reset-button"
                            className="mt-2 w-full h-11 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold text-[13.5px] tracking-wide transition-colors duration-150 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            {processing ? <Spinner /> : 'Verify code'}
                        </button>

                        <p className="text-center text-[13px] text-muted-foreground">
                            Didn&apos;t get a code?{' '}
                            <TextLink href={route('password.request')} className="font-semibold text-indigo-600 no-underline hover:underline">
                                Request again
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyPasswordReset.layout = staffAuthLayout;
