import { Form, Head, Link } from '@inertiajs/react';

import { CustomerAuthField } from '@/components/frontend/customer-auth-field';
import { CustomerAuthLayout } from '@/components/frontend/customer-auth-layout';
import { CustomerAuthSubmit } from '@/components/frontend/customer-auth-submit';
import { CustomerPasswordResetSteps } from '@/components/password-reset-steps';

export default function CustomerResetPassword({ email, status }) {
    return (
        <CustomerAuthLayout
            variant="login"
            title="Set new password"
            subtitle="Choose a strong password for your account"
            alternatePrompt="Need help?"
            alternateHref="/customer/login"
            alternateLabel="Back to sign in"
        >
            <Head title="New password" />

            <CustomerPasswordResetSteps currentStep={3} />

            {status ? (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {status}
                </div>
            ) : null}

            <Form action="/customer/reset-password" method="post" className="space-y-5">
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
                            label="New password"
                            type="password"
                            name="password"
                            autoComplete="new-password"
                            error={errors.password}
                            required
                        />

                        <CustomerAuthField
                            label="Confirm password"
                            type="password"
                            name="password_confirmation"
                            autoComplete="new-password"
                            error={errors.password_confirmation}
                            required
                        />

                        <InputErrorBlock message={errors.email} />

                        <CustomerAuthSubmit disabled={processing}>
                            {processing ? 'Updating password…' : 'Update password'}
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
