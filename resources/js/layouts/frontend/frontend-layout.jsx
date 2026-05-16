import { Link, usePage } from '@inertiajs/react';
import { ShoppingCart, Search, User, Menu, X, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { route } from '@/wayfinder';

export default function FrontendLayout({ children }) {
    const { cart = {}, auth } = usePage().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const cartCount = Object.keys(cart).length;

    const handleSearch = (e) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            window.location.href = `/search?q=${encodeURIComponent(searchQuery)}`;
        }
    };

    return (
        <div className="min-h-screen bg-white text-gray-900">
            {/* Top notice bar */}
            {usePage().props.topNotice && (
                <div className="bg-black py-2 text-center text-xs text-white">
                    {usePage().props.topNotice}
                </div>
            )}

            {/* Header */}
            <header className="sticky top-0 z-50 border-b border-gray-100 bg-white shadow-sm">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        {/* Logo */}
                        <Link href="/" className="text-xl font-bold tracking-tight text-gray-900">
                            {usePage().props.siteName || 'Coolness Point'}
                        </Link>

                        {/* Desktop nav */}
                        <nav className="hidden items-center gap-6 md:flex">
                            <Link href="/" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                                হোম
                            </Link>
                            <Link href="/about" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                                আমাদের সম্পর্কে
                            </Link>
                            <Link href="/contact" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                                যোগাযোগ
                            </Link>
                        </nav>

                        {/* Right icons */}
                        <div className="flex items-center gap-3">
                            <button
                                onClick={() => setSearchOpen(!searchOpen)}
                                className="rounded p-1.5 text-gray-600 hover:bg-gray-100"
                                aria-label="Search"
                            >
                                <Search className="size-5" />
                            </button>

                            <Link href="/cart" className="relative rounded p-1.5 text-gray-600 hover:bg-gray-100">
                                <ShoppingCart className="size-5" />
                                {cartCount > 0 && (
                                    <span className="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-black text-[10px] font-bold text-white">
                                        {cartCount}
                                    </span>
                                )}
                            </Link>

                            <Link
                                href="/customer/dashboard"
                                className="rounded p-1.5 text-gray-600 hover:bg-gray-100"
                                aria-label="Account"
                            >
                                <User className="size-5" />
                            </Link>

                            <button
                                className="rounded p-1.5 text-gray-600 hover:bg-gray-100 md:hidden"
                                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                aria-label="Menu"
                            >
                                {mobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                            </button>
                        </div>
                    </div>

                    {/* Search bar */}
                    {searchOpen && (
                        <div className="border-t border-gray-100 py-3">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="পণ্য খুঁজুন..."
                                    className="flex-1 border border-gray-300 px-3 py-2 text-sm focus:border-black focus:outline-none"
                                    autoFocus
                                />
                                <button
                                    type="submit"
                                    className="bg-black px-4 py-2 text-sm text-white hover:bg-gray-800"
                                >
                                    খুঁজুন
                                </button>
                            </form>
                        </div>
                    )}
                </div>

                {/* Mobile menu */}
                {mobileMenuOpen && (
                    <div className="border-t border-gray-100 bg-white px-4 py-4 md:hidden">
                        <nav className="flex flex-col gap-3">
                            <Link href="/" className="text-sm font-medium text-gray-700">হোম</Link>
                            <Link href="/about" className="text-sm font-medium text-gray-700">আমাদের সম্পর্কে</Link>
                            <Link href="/contact" className="text-sm font-medium text-gray-700">যোগাযোগ</Link>
                            <Link href="/customer/login" className="text-sm font-medium text-gray-700">লগইন</Link>
                        </nav>
                    </div>
                )}
            </header>

            {/* Main */}
            <main>{children}</main>

            {/* Footer */}
            <footer className="mt-16 border-t border-gray-200 bg-gray-50">
                <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <h3 className="mb-3 text-sm font-semibold text-gray-900">
                                {usePage().props.siteName || 'Coolness Point'}
                            </h3>
                            <p className="text-sm text-gray-600">
                                ফ্যাশন ও পোশাকের সেরা গন্তব্য।
                            </p>
                        </div>
                        <div>
                            <h3 className="mb-3 text-sm font-semibold text-gray-900">পলিসি</h3>
                            <ul className="space-y-2 text-sm text-gray-600">
                                <li><Link href="/refund-policy" className="hover:text-gray-900">রিফান্ড পলিসি</Link></li>
                                <li><Link href="/cancellation-policy" className="hover:text-gray-900">বাতিল পলিসি</Link></li>
                                <li><Link href="/privacy-policy" className="hover:text-gray-900">প্রাইভেসি পলিসি</Link></li>
                                <li><Link href="/terms-policy" className="hover:text-gray-900">শর্তাবলী</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h3 className="mb-3 text-sm font-semibold text-gray-900">সহায়তা</h3>
                            <ul className="space-y-2 text-sm text-gray-600">
                                <li><Link href="/faq" className="hover:text-gray-900">FAQ</Link></li>
                                <li><Link href="/size-guide" className="hover:text-gray-900">সাইজ গাইড</Link></li>
                                <li><Link href="/contact" className="hover:text-gray-900">যোগাযোগ</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h3 className="mb-3 text-sm font-semibold text-gray-900">অ্যাকাউন্ট</h3>
                            <ul className="space-y-2 text-sm text-gray-600">
                                <li><Link href="/customer/login" className="hover:text-gray-900">লগইন</Link></li>
                                <li><Link href="/customer/register" className="hover:text-gray-900">রেজিস্ট্রেশন</Link></li>
                                <li><Link href="/customer/orders" className="hover:text-gray-900">আমার অর্ডার</Link></li>
                            </ul>
                        </div>
                    </div>
                    <div className="mt-8 border-t border-gray-200 pt-6 text-center text-xs text-gray-500">
                        &copy; {new Date().getFullYear()} {usePage().props.siteName || 'Coolness Point'}. সর্বস্বত্ব সংরক্ষিত।
                    </div>
                </div>
            </footer>
        </div>
    );
}
