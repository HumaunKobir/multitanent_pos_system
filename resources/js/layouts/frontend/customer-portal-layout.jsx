import { Link, router, usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Home, LogOut, Package, User } from 'lucide-react';

export function CustomerPortalLayout({ children, title }) {
    const { auth } = usePage().props;
    const customer = auth?.customer;

    const nav = [
        { href: '/customer/profile', label: 'Dashboard', icon: User },
        { href: '/customer/orders', label: 'My Orders', icon: Package },
        { href: '/', label: 'Shop', icon: Home },
    ];

    const logout = () => router.post('/customer/logout');

    return (
        <div className="min-h-screen bg-gradient-to-br from-store-primary via-[#16213e] to-[#0f3460] font-[Inter,system-ui,sans-serif]">
            {title && <Head title={title} />}
            <nav className="border-b border-white/10 bg-white/5 backdrop-blur-md">
                <div className="store-container flex h-14 items-center justify-between">
                    <div className="flex items-center gap-1 sm:gap-4">
                        {nav.map(({ href, label, icon: Icon }) => (
                            <Link
                                key={href}
                                href={href}
                                className="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-white/80 hover:bg-white/10 hover:text-white sm:px-3 sm:text-sm"
                            >
                                <Icon className="size-4" />
                                <span className="hidden sm:inline">{label}</span>
                            </Link>
                        ))}
                    </div>
                    {customer && (
                        <button
                            onClick={logout}
                            className="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs text-white/70 hover:bg-white/10 hover:text-white sm:text-sm"
                        >
                            <LogOut className="size-4" />
                            <span className="hidden sm:inline">Logout</span>
                        </button>
                    )}
                </div>
            </nav>
            <main className="store-container py-6">{children}</main>
        </div>
    );
}
