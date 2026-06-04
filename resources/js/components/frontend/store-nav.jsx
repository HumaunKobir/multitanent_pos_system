import { Link, usePage } from '@inertiajs/react';
import { Home } from 'lucide-react';
import { useCallback, useState } from 'react';

import { FilterNavDropdown } from '@/components/frontend/filter-nav-dropdown';

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

export function StoreNavBar({ onNavigate }) {
    const { categories = [], brands = [], tags = [] } = usePage().props;
    const { url } = usePage();
    const [openFilter, setOpenFilter] = useState(null);

    const isActive = useCallback(
        (path) => {
            if (path === '/') {
                return url === '/';
            }

            return url.startsWith(path);
        },
        [url],
    );

    const handleNavigate = () => {
        setOpenFilter(null);
        onNavigate?.();
    };

    const categoryPath = (slug) => `/category/${slug}/products`;
    const brandPath = (slug) => `/brand/${slug}/products`;
    const tagPath = (name) => `/collection/${encodeURIComponent(name)}`;

    const isCategoryActive = (item) => url.startsWith(categoryPath(item.slug));
    const isBrandActive = (item) => url.startsWith(brandPath(item.slug));
    const isTagActive = (item) => {
        const prefix = `/collection/${encodeURIComponent(item.name)}`;

        return url === prefix || url.startsWith(`${prefix}?`);
    };

    return (
        <div className="relative hidden border-t border-white/10 bg-store-primary md:block">
            <div className="store-container flex items-center justify-center gap-0.5 py-1">
                <NavLink href="/" label="Home" icon={Home} active={isActive('/')} onClick={handleNavigate} />

                <FilterNavDropdown
                    label="Category"
                    items={categories}
                    buildHref={(item) => categoryPath(item.slug)}
                    isItemActive={isCategoryActive}
                    active={url.includes('/category/')}
                    open={openFilter === 'category'}
                    onOpenChange={(next) => setOpenFilter(next ? 'category' : null)}
                    onNavigate={handleNavigate}
                    emptyMessage="No categories yet"
                />

                <FilterNavDropdown
                    label="Brand"
                    items={brands}
                    buildHref={(item) => brandPath(item.slug)}
                    isItemActive={isBrandActive}
                    active={url.includes('/brand/')}
                    open={openFilter === 'brand'}
                    onOpenChange={(next) => setOpenFilter(next ? 'brand' : null)}
                    onNavigate={handleNavigate}
                    emptyMessage="No brands yet"
                />

                <FilterNavDropdown
                    label="Tags"
                    items={tags}
                    buildHref={(item) => tagPath(item.name)}
                    isItemActive={isTagActive}
                    active={url.includes('/collection/')}
                    open={openFilter === 'tags'}
                    onOpenChange={(next) => setOpenFilter(next ? 'tags' : null)}
                    onNavigate={handleNavigate}
                    emptyMessage="No tags yet"
                />
            </div>
        </div>
    );
}

export function MobileFilterSection({ label, items, buildHref, onNavigate, emptyMessage }) {
    const [search, setSearch] = useState('');
    const query = search.trim().toLowerCase();
    const filtered = query ? items.filter((item) => item.name.toLowerCase().includes(query)) : items;

    return (
        <div className="px-3 pb-2.5">
            <p className="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-store-muted">{label}</p>
            <div className="relative mb-2 overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200/90 focus-within:ring-2 focus-within:ring-store-accent/25">
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search ..."
                    className="w-full appearance-none rounded-full border-0 bg-transparent py-2 pl-3 pr-3 text-xs focus:outline-none"
                />
            </div>
            <ul className="store-filter-scroll flex max-h-36 flex-col gap-2 overflow-y-auto pr-0.5">
                {filtered.length > 0 ? (
                    filtered.map((item) => (
                        <li key={item.id}>
                            <Link
                                href={buildHref(item)}
                                onClick={onNavigate}
                                className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-[13px] font-medium text-store-primary hover:bg-store-surface"
                            >
                                <span className="size-3.5 shrink-0 rounded-full border-[1.5px] border-gray-300" aria-hidden />
                                {item.name}
                            </Link>
                        </li>
                    ))
                ) : (
                    <li className="py-2.5 text-center text-xs text-store-muted">
                        {items.length === 0 ? emptyMessage : 'No matches found'}
                    </li>
                )}
            </ul>
        </div>
    );
}
