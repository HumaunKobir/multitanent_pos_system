import { Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Menu, Search, Sparkles, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { StoreAccountButton } from '@/components/frontend/store-account-button';
import { StoreSearchBox } from '@/components/frontend/store-search-box';
import { MobileFilterSection, StoreNavBar } from '@/components/frontend/store-nav';

export function StoreHeader() {
    const { props, url } = usePage();
    const { siteName, topNotice, categories = [], brands = [], tags = [], auth, logo } = props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);

    const initialSearchQuery = useMemo(() => {
        if (!url.startsWith('/search')) {
            return '';
        }

        const queryString = url.includes('?') ? url.split('?')[1] : '';

        return new URLSearchParams(queryString).get('q') ?? '';
    }, [url]);

    const closeMobileMenu = () => setMobileMenuOpen(false);

    return (
        <>
            {topNotice && (
                <>
                    <div className="h-7 shrink-0" aria-hidden="true" />
                    <div className="store-top-banner fixed top-0 left-0 right-0 z-60 h-7">
                        <marquee className="flex h-full items-center" scrollAmount={3}>
                            <span className="mx-10 inline-flex items-center gap-2.5 text-xs font-semibold uppercase tracking-[0.18em] text-white/90">
                                <span className="size-1.5 shrink-0 rounded-full bg-store-accent shadow-[0_0_6px_rgb(233_69_96/0.8)]" />
                                {topNotice}
                                <span className="size-1.5 shrink-0 rounded-full bg-store-accent shadow-[0_0_6px_rgb(233_69_96/0.8)]" />
                            </span>
                        </marquee>
                    </div>
                </>
            )}

            <header className={`sticky z-50 shadow-md shadow-store-primary/5 ${topNotice ? 'top-7' : 'top-0'}`}>
                <div className="overflow-visible border-b border-gray-100 bg-white/95 backdrop-blur-md">
                    <div className="store-container overflow-visible">
                        <div className="relative flex h-16 items-center gap-3 overflow-visible lg:gap-6">
                            <div className="flex shrink-0 items-center gap-2">
                                <button
                                    className="rounded-lg p-2 text-store-primary transition-colors hover:bg-store-surface md:hidden"
                                    onClick={() => setMobileMenuOpen(true)}
                                    aria-label="Open menu"
                                >
                                    <Menu className="size-5" />
                                </button>

                                <Link href="/" className="group flex shrink-0 items-center gap-2.5">
                                    {logo ? (
                                        <img src={logo} alt={siteName} className="h-8 object-contain sm:h-9" />
                                    ) : (
                                        <div className="flex flex-col">
                                            <span className="text-lg font-bold tracking-tight text-store-primary sm:text-xl">
                                                {siteName || 'POS SYSTEM'}
                                            </span>
                                            <span className="hidden items-center gap-1 text-[10px] font-medium uppercase tracking-widest text-store-muted sm:flex">
                                                <Sparkles className="size-3 text-store-accent" />
                                                Curated Style
                                            </span>
                                        </div>
                                    )}
                                    <span className="hidden h-8 w-0.5 store-gradient sm:block" aria-hidden="true" />
                                </Link>
                            </div>

                            <StoreSearchBox initialQuery={initialSearchQuery} variant="desktop" />

                            <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                                <button
                                    onClick={() => setSearchOpen(!searchOpen)}
                                    className="rounded-full p-2.5 text-store-primary transition-colors hover:bg-store-surface md:hidden"
                                    aria-label="Search"
                                >
                                    <Search className="size-5" />
                                </button>

                                <StoreAccountButton customer={auth?.customer} />
                            </div>
                        </div>

                        {searchOpen && (
                            <div className="border-t border-gray-100 pb-3 pt-2 md:hidden">
                                <StoreSearchBox
                                    initialQuery={initialSearchQuery}
                                    variant="mobile"
                                    autoFocus
                                    onSubmitted={() => setSearchOpen(false)}
                                />
                            </div>
                        )}
                    </div>
                </div>

                <StoreNavBar />
            </header>

            {mobileMenuOpen && (
                <div className="fixed inset-0 z-[55] md:hidden">
                    <div className="absolute inset-0 bg-store-primary/40 backdrop-blur-sm" onClick={closeMobileMenu} />
                    <motion.div
                        initial={{ x: '-100%' }}
                        animate={{ x: 0 }}
                        exit={{ x: '-100%' }}
                        className="absolute inset-y-0 left-0 flex w-[min(320px,88vw)] flex-col bg-white shadow-2xl"
                    >
                        <div className="store-gradient px-4 py-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-semibold text-white">Menu</p>
                                    <p className="text-xs text-white/80">Explore our store</p>
                                </div>
                                <button onClick={closeMobileMenu} className="rounded-full p-1.5 text-white/90 hover:bg-white/10">
                                    <X className="size-5" />
                                </button>
                            </div>
                        </div>

                        <nav className="flex-1 overflow-y-auto py-3">
                            <Link
                                href="/"
                                className="mx-3 mb-2 block rounded-xl px-3 py-2.5 text-sm font-semibold text-store-primary hover:bg-store-surface"
                                onClick={closeMobileMenu}
                            >
                                Home
                            </Link>

                            <Link
                                href="/products"
                                className="mx-3 mb-3 block rounded-xl px-3 py-2.5 text-sm font-semibold text-store-primary hover:bg-store-surface"
                                onClick={closeMobileMenu}
                            >
                                All Products
                            </Link>

                            <MobileFilterSection
                                label="Category"
                                items={categories}
                                buildHref={(item) => `/category/${item.slug}/products`}
                                onNavigate={closeMobileMenu}
                                emptyMessage="No categories yet"
                            />

                            <MobileFilterSection
                                label="Brand"
                                items={brands}
                                buildHref={(item) => `/brand/${item.slug}/products`}
                                onNavigate={closeMobileMenu}
                                emptyMessage="No brands yet"
                            />

                            <MobileFilterSection
                                label="Tags"
                                items={tags}
                                buildHref={(item) => `/collection/${encodeURIComponent(item.name)}`}
                                onNavigate={closeMobileMenu}
                                emptyMessage="No tags yet"
                            />
                        </nav>

                        <div className="border-t border-gray-100 p-4">
                            <div className="flex justify-center">
                                <StoreAccountButton
                                    customer={auth?.customer}
                                    onClick={closeMobileMenu}
                                />
                            </div>
                        </div>
                    </motion.div>
                </div>
            )}
        </>
    );
}
