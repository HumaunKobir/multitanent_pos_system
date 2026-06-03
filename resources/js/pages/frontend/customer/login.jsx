import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { StoreButton } from '@/components/frontend/store-button';

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
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-store-primary via-[#16213e] to-[#0f3460] p-4 font-[Inter,system-ui,sans-serif]">
            <Head title="Login" />
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                className="w-full max-w-md rounded-xl border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur-md sm:p-8"
            >
                <div className="mb-6 text-center">
                    <h1 className="text-2xl font-bold text-white">Login</h1>
                    <p className="mt-1 text-sm text-white/60">Sign in to your account</p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-white/90">Email or phone</label>
                        <input
                            type="text"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="w-full rounded-md border border-white/20 bg-white/90 px-3 py-2 text-sm focus:border-store-accent focus:outline-none"
                            required
                        />
                        {errors.email && <p className="mt-1 text-xs text-red-300">{errors.email}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-white/90">Password</label>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="w-full rounded-md border border-white/20 bg-white/90 px-3 py-2 text-sm focus:border-store-accent focus:outline-none"
                            required
                        />
                        {errors.password && <p className="mt-1 text-xs text-red-300">{errors.password}</p>}
                    </div>
                    <StoreButton type="submit" className="w-full" disabled={processing}>
                        {processing ? 'Signing in...' : 'Sign In'}
                    </StoreButton>
                    <p className="text-center text-sm text-white/70">
                        Don&apos;t have an account?{' '}
                        <Link href="/customer/register" className="font-medium text-white underline">
                            Register
                        </Link>
                    </p>
                    <Link href="/" className="block text-center text-xs text-white/50 hover:text-white">
                        ← Back to shop
                    </Link>
                </form>
            </motion.div>
        </div>
    );
}
