import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDown, Grid3X3, Home, Info, Mail, ShoppingBag } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

function categoryImage(category) {
    if (!category.image) {
        return null;
    }

    return category.image.startsWith('/') ? category.image : `/storage/${category.image}`;
}

function NavLink({ href, label, icon: Icon, active, onClick }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            data-active={active ? 'true' : 'false'}
            className="group relative flex items-center gap-1.5 px-4 py-3 text-sm font-medium text-white/75 transition-colors hover:text-white data-[active=true]:text-white"
        >
            {Icon && <Icon className="size-3.5 opacity-70 group-hover:opacity-100" />}
            {label}
            <span className="absolute inset-x-3 bottom-1.5 h-0.5 origin-center scale-x-0 rounded-full bg-store-accent transition-transform duration-300 group-hover:scale-x-100 group-data-[active=true]:scale-x-100" />
        </Link>
    );
}

function CategoryCard({ category, onNavigate }) {
    const imageSrc = categoryImage(category);

    return (
        <Link
            href={`/category/${category.slug}/products`}
            onClick={onNavigate}
            className="group flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-store-accent/30 hover:shadow-md"
        >
            <div className="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-store-gradient-from/20 to-store-gradient-to/20">
                {imageSrc ? (
                    <img
                        src={imageSrc}
                        alt={category.name}
                        className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center">
                        <ShoppingBag className="size-8 text-store-accent/40" />
                    </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-store-primary/60 via-transparent to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
            </div>
            <div className="px-3 py-2.5">
                <p className="text-sm font-semibold text-store-primary group-hover:text-store-accent">{category.name}</p>
                <p className="text-xs text-store-muted">Browse collection</p>
            </div>
        </Link>
    );
}

export function StoreNavBar({ onNavigate }) {
    const { categories = [] } = usePage().props;
    const { url } = usePage();
    const [shopOpen, setShopOpen] = useState(false);
    const shopRef = useRef(null);
    const closeTimer = useRef(null);

    const isActive = useCallback(
        (path) => {
            if (path === '/') {
                return url === '/';
            }

            return url.startsWith(path);
        },
        [url],
    );

    const openShop = () => {
        if (closeTimer.current) {
            clearTimeout(closeTimer.current);
        }
        setShopOpen(true);
    };

    const closeShop = () => {
        closeTimer.current = setTimeout(() => setShopOpen(false), 120);
    };

    const handleNavigate = () => {
        setShopOpen(false);
        onNavigate?.();
    };

    useEffect(() => {
        return () => {
            if (closeTimer.current) {
                clearTimeout(closeTimer.current);
            }
        };
    }, []);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (shopRef.current && !shopRef.current.contains(event.target)) {
                setShopOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div className="relative hidden border-t border-white/10 bg-store-primary md:block">
            <div className="store-container flex items-center justify-center">
                <NavLink href="/" label="Home" icon={Home} active={isActive('/')} onClick={handleNavigate} />

                <div ref={shopRef} className="relative" onMouseEnter={openShop} onMouseLeave={closeShop}>
                    <button
                        type="button"
                        onClick={() => setShopOpen((open) => !open)}
                        data-active={shopOpen || url.includes('/category/') ? 'true' : 'false'}
                        className="group relative flex items-center gap-1.5 px-4 py-3 text-sm font-medium text-white/75 transition-colors hover:text-white data-[active=true]:text-white"
                        aria-expanded={shopOpen}
                        aria-haspopup="true"
                    >
                        <Grid3X3 className="size-3.5 opacity-70 group-hover:opacity-100" />
                        Shop
                        <ChevronDown className={`size-3.5 transition-transform duration-200 ${shopOpen ? 'rotate-180' : ''}`} />
                        <span className="absolute inset-x-3 bottom-1.5 h-0.5 origin-center scale-x-0 rounded-full bg-store-accent transition-transform duration-300 group-hover:scale-x-100 group-data-[active=true]:scale-x-100" />
                    </button>

                    <AnimatePresence>
                        {shopOpen && (
                            <motion.div
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: 8 }}
                                transition={{ duration: 0.18 }}
                                className="absolute left-1/2 top-full z-50 w-[min(720px,calc(100vw-2rem))] -translate-x-1/2 pt-2"
                            >
                                <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl shadow-store-primary/20">
                                    <div className="store-gradient px-5 py-3">
                                        <p className="text-sm font-semibold text-white">Shop by Category</p>
                                        <p className="text-xs text-white/80">All categories from your store — updated automatically</p>
                                    </div>

                                    {categories.length > 0 ? (
                                        <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3">
                                            {categories.map((category) => (
                                                <CategoryCard key={category.id} category={category} onNavigate={handleNavigate} />
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="px-5 py-8 text-center">
                                            <p className="text-sm font-medium text-store-primary">No categories yet</p>
                                            <p className="mt-1 text-xs text-store-muted">Add categories from admin to show them here</p>
                                        </div>
                                    )}
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>

                <NavLink href="/about" label="About" icon={Info} active={isActive('/about')} onClick={handleNavigate} />
                <NavLink href="/contact" label="Contact" icon={Mail} active={isActive('/contact')} onClick={handleNavigate} />
            </div>
        </div>
    );
}

export function MobileCategoryGrid({ categories, onNavigate }) {
    if (!categories.length) {
        return (
            <p className="px-3 py-4 text-center text-xs text-store-muted">No categories available</p>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-2 px-3 pb-2">
            {categories.map((category) => {
                const imageSrc = categoryImage(category);

                return (
                    <Link
                        key={category.id}
                        href={`/category/${category.slug}/products`}
                        onClick={onNavigate}
                        className="overflow-hidden rounded-xl border border-gray-100 bg-store-surface"
                    >
                        <div className="aspect-square bg-gradient-to-br from-store-gradient-from/15 to-store-gradient-to/15">
                            {imageSrc ? (
                                <img src={imageSrc} alt={category.name} className="size-full object-cover" />
                            ) : (
                                <div className="flex size-full items-center justify-center">
                                    <ShoppingBag className="size-6 text-store-accent/50" />
                                </div>
                            )}
                        </div>
                        <p className="truncate px-2 py-2 text-center text-xs font-semibold text-store-primary">{category.name}</p>
                    </Link>
                );
            })}
        </div>
    );
}
