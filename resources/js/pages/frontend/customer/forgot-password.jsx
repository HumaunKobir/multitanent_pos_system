import { Form, Head, Link } from '@inertiajs/react';

import { CustomerPasswordResetSteps } from '@/components/password-reset-steps';
import { CustomerAuthField } from '@/components/frontend/customer-auth-field';
import { CustomerAuthLayout } from '@/components/frontend/customer-auth-layout';
import { CustomerAuthSubmit } from '@/components/frontend/customer-auth-submit';

export default function CustomerForgotPassword({ status }) {
    return (
        <CustomerAuthLayout
            variant="login"
            title="Forgot password"
            subtitle="Enter your email and we will send a verification code"
            alternatePrompt="Remember your password?"
            alternateHref="/customer/login"
            alternateLabel="Sign in"
        >
            <Head title="Forgot password" />

            <CustomerPasswordResetSteps currentStep={1} />

            {status ? (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {status}
                </div>
            ) : null}

            <Form action="/customer/forgot-password" method="post" className="space-y-5">
                {({ processing, errors }) => (
                    <>
                        <CustomerAuthField
                            label="Email"
                            type="email"
                            name="email"
                            autoComplete="email"
                            error={errors.email}
                            required
                        />
                        <CustomerAuthSubmit disabled={processing}>
                            {processing ? 'Sending code…' : 'Send verification code'}
                        </CustomerAuthSubmit>
                        <p className="text-center text-sm text-store-muted">
                            Already have a code?{' '}
                            <Link href="/customer/reset-password/verify" className="font-semibold text-store-accent underline-offset-4 hover:underline">
                                Verify code
                            </Link>
                        </p>
                    </>
                )}
            </Form>
        </CustomerAuthLayout>
    );
}
