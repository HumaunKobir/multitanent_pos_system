import { useForm } from '@inertiajs/react';
import { CustomerAuthField } from '@/components/frontend/customer-auth-field';
import { CustomerAuthLayout } from '@/components/frontend/customer-auth-layout';
import { CustomerAuthSubmit } from '@/components/frontend/customer-auth-submit';

const fields = [
    { key: 'name', label: 'Full name', type: 'text', autoComplete: 'name', required: true },
    { key: 'phone', label: 'Phone number', type: 'tel', autoComplete: 'tel', required: true },
    { key: 'email', label: 'Email (optional)', type: 'email', autoComplete: 'email', required: false },
    { key: 'password', label: 'Password', type: 'password', autoComplete: 'new-password', required: true },
    {
        key: 'password_confirmation',
        label: 'Confirm password',
        type: 'password',
        autoComplete: 'new-password',
        required: true,
    },
];

export default function CustomerRegister() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/customer/register');
    };

    return (
        <CustomerAuthLayout
            variant="register"
            title="Create your account"
            subtitle="Join for free — track orders and checkout faster"
            alternatePrompt="Already have an account?"
            alternateHref="/customer/login"
            alternateLabel="Sign in"
        >
            <form onSubmit={submit} className="space-y-4">
                {fields.map(({ key, label, type, autoComplete, required }) => (
                    <CustomerAuthField
                        key={key}
                        label={required ? `${label} *` : label}
                        type={type}
                        autoComplete={autoComplete}
                        value={data[key]}
                        onChange={(e) => setData(key, e.target.value)}
                        error={errors[key]}
                        required={required}
                    />
                ))}
                <CustomerAuthSubmit disabled={processing} className="mt-2">
                    {processing ? 'Creating account…' : 'Create account'}
                </CustomerAuthSubmit>
            </form>
        </CustomerAuthLayout>
    );
}
