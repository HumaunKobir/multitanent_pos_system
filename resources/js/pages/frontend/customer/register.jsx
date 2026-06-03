import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { StoreButton } from '@/components/frontend/store-button';

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

    const fields = [
        { key: 'name', label: 'Name *', type: 'text', required: true },
        { key: 'phone', label: 'Phone *', type: 'tel', required: true },
        { key: 'email', label: 'Email', type: 'email', required: false },
        { key: 'password', label: 'Password *', type: 'password', required: true },
        { key: 'password_confirmation', label: 'Confirm password *', type: 'password', required: true },
    ];

    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-store-primary via-[#16213e] to-[#0f3460] p-4 font-[Inter,system-ui,sans-serif]">
            <Head title="Register" />
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                className="w-full max-w-md rounded-xl border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur-md sm:p-8"
            >
                <div className="mb-6 text-center">
                    <h1 className="text-2xl font-bold text-white">Create Account</h1>
                    <p className="mt-1 text-sm text-white/60">Register for free</p>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    {fields.map(({ key, label, type, required }) => (
                        <div key={key}>
                            <label className="mb-1 block text-sm font-medium text-white/90">{label}</label>
                            <input
                                type={type}
                                value={data[key]}
                                onChange={(e) => setData(key, e.target.value)}
                                className="w-full rounded-md border border-white/20 bg-white/90 px-3 py-2 text-sm focus:border-store-accent focus:outline-none"
                                required={required}
                            />
                            {errors[key] && <p className="mt-1 text-xs text-red-300">{errors[key]}</p>}
                        </div>
                    ))}
                    <StoreButton type="submit" className="w-full" disabled={processing}>
                        {processing ? 'Creating account...' : 'Register'}
                    </StoreButton>
                    <p className="text-center text-sm text-white/70">
                        Already have an account?{' '}
                        <Link href="/customer/login" className="font-medium text-white underline">
                            Login
                        </Link>
                    </p>
                </form>
            </motion.div>
        </div>
    );
}
