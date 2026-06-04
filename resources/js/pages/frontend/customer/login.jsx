import { Link, useForm } from '@inertiajs/react';
import { CustomerAuthField } from '@/components/frontend/customer-auth-field';
import { CustomerAuthLayout } from '@/components/frontend/customer-auth-layout';

export default function CustomerLogin() {
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
            title="Sign in"
            subtitle="Use your email or phone number to access your account"
            alternatePrompt="Don't have an account?"
            alternateHref="/customer/register"
            alternateLabel="Create one"
        >
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
                <button
                    type="submit"
                    disabled={processing}
                    className="auth-btn-gradient w-full rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-md shadow-auth-accent/30 transition-all hover:opacity-95 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </CustomerAuthLayout>
    );
}
