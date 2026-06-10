import { Form, Head, Link } from '@inertiajs/react';

import { CustomerAuthField } from '@/components/frontend/customer-auth-field';
import { CustomerAuthLayout } from '@/components/frontend/customer-auth-layout';
import { CustomerAuthSubmit } from '@/components/frontend/customer-auth-submit';
import { CustomerPasswordResetSteps } from '@/components/password-reset-steps';

export default function CustomerVerifyPasswordReset({ email, status }) {
    return (
        <CustomerAuthLayout
            variant="login"
            title="Verify your code"
            subtitle="Enter the 6-digit code we sent to your email"
            alternatePrompt="Need a new code?"
            alternateHref="/customer/forgot-password"
            alternateLabel="Request again"
        >
            <Head title="Verify code" />

            <CustomerPasswordResetSteps currentStep={2} />

            {status ? (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {status}
                </div>
            ) : null}

            <Form action="/customer/reset-password/verify" method="post" className="space-y-5">
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="email" value={email} />

                        <CustomerAuthField
                            label="Email"
                            type="email"
                            value={email}
                            readOnly
                        />

                        <CustomerAuthField
                            label="Verification code"
                            type="text"
                            name="otp"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            placeholder="000000"
                            error={errors.otp}
                            required
                            inputClassName="text-center font-mono tracking-[0.35em]"
                        />

                        <InputErrorBlock message={errors.email} />

                        <CustomerAuthSubmit disabled={processing}>
                            {processing ? 'Verifying…' : 'Verify code'}
                        </CustomerAuthSubmit>

                        <p className="text-center text-sm text-store-muted">
                            <Link href="/customer/login" className="font-semibold text-store-accent underline-offset-4 hover:underline">
                                Back to sign in
                            </Link>
                        </p>
                    </>
                )}
            </Form>
        </CustomerAuthLayout>
    );
}

function InputErrorBlock({ message }) {
    if (!message) {
        return null;
    }

    return <p className="text-sm text-red-600">{message}</p>;
}
