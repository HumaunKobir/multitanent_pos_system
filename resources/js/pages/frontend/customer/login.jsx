import { Link, useForm } from '@inertiajs/react';
import { CustomerAuthField } from '@/components/frontend/customer-auth-field';
import { CustomerAuthLayout } from '@/components/frontend/customer-auth-layout';
import { CustomerAuthSubmit } from '@/components/frontend/customer-auth-submit';

export default function CustomerLogin({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/customer/login');
    };

    return (
        <CustomerAuthLayout
            variant="login"
            title="Sign in"
            subtitle="Use your email or phone number to access your account"
            alternatePrompt="Don't have an account?"
            alternateHref="/customer/register"
            alternateLabel="Create one"
        >
            {status ? (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {status}
                </div>
            ) : null}
            <form onSubmit={submit} className="space-y-5">
                <CustomerAuthField
                    label="Email or phone"
                    type="text"
                    autoComplete="username"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                />
                <CustomerAuthField
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    required
                />
                <div className="flex justify-end">
                    <Link
                        href="/customer/forgot-password"
                        className="text-xs font-semibold text-store-accent underline-offset-4 hover:underline"
                    >
                        Forgot password?
                    </Link>
                </div>
                <CustomerAuthSubmit disabled={processing}>
                    {processing ? 'Signing in…' : 'Sign in'}
                </CustomerAuthSubmit>
            </form>
        </CustomerAuthLayout>
    );
}
