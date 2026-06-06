import { Link, router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Search } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

function formatPrice(value) {
    return Number(value).toLocaleString('en-BD', { maximumFractionDigits: 0 });
}

function getSuggestionPriceLabel(product) {
    if (product.has_variations && product.price_min != null) {
        return `From ৳${formatPrice(product.price_min)}`;
    }

    return `৳${formatPrice(product.price)}`;
}

function SearchSuggestions({ query, suggestions, loading, onSelect, position, dropdownRef }) {
    if (!query.trim() || query.trim().length < 2) {
        return null;
    }

    return (
        <motion.div
            ref={dropdownRef}
            initial={{ opacity: 0, y: -10, scaleY: 0.96 }}
            animate={{ opacity: 1, y: 0, scaleY: 1 }}
            exit={{ opacity: 0, y: -8, scaleY: 0.98 }}
            transition={{ duration: 0.2, ease: 'easeOut' }}
            style={{
                transformOrigin: 'top center',
                position: 'fixed',
                top: position.top,
                left: position.left,
                width: position.width,
                zIndex: 80,
            }}
            className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl ring-1 ring-black/5"
        >
            {loading ? (
                <p className="px-4 py-3 text-sm text-store-muted">Searching...</p>
            ) : suggestions.length === 0 ? (
                <p className="px-4 py-3 text-sm text-store-muted">No products found.</p>
            ) : (
                <>
                    <ul className="max-h-80 overflow-y-auto py-1">
                        {suggestions.map((product) => (
                            <li key={product.id}>
                                <Link
                                    href={`/products/${product.slug}`}
                                    onClick={onSelect}
                                    className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-store-surface"
                                >
                                    <div className="size-12 shrink-0 overflow-hidden rounded-xl bg-gray-100 ring-1 ring-gray-200">
                                        {product.image ? (
                                            <img
                                                src={product.image}
                                                alt={product.name}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-[10px] text-store-muted">
                                                No image
                                            </div>
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-store-primary">{product.name}</p>
                                        <p className="mt-0.5 text-xs font-semibold text-store-accent">
                                            {getSuggestionPriceLabel(product)}
                                        </p>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <button
                        type="button"
                        onClick={() => {
                            onSelect();
                            router.get(route('search', { query: { q: query.trim() } }));
                        }}
                        className="w-full border-t border-gray-100 px-4 py-3 text-left text-sm font-semibold text-store-primary transition-colors hover:bg-store-surface"
                    >
                        View all results for &quot;{query.trim()}&quot;
                    </button>
                </>
            )}
        </motion.div>
    );
}

export function StoreSearchBox({
    initialQuery = '',
    variant = 'desktop',
    autoFocus = false,
    onSubmitted,
    onSuggestionsOpenChange,
}) {
    const [searchQuery, setSearchQuery] = useState(initialQuery);
    const [suggestions, setSuggestions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [dropdownPosition, setDropdownPosition] = useState({ top: 0, left: 0, width: 0 });
    const containerRef = useRef(null);
    const dropdownRef = useRef(null);
    const requestIdRef = useRef(0);

    useEffect(() => {
        setSearchQuery(initialQuery);
    }, [initialQuery]);

    const showSuggestions = open && searchQuery.trim().length >= 2;

    const updateDropdownPosition = () => {
        if (!containerRef.current) {
            return;
        }

        const rect = containerRef.current.getBoundingClientRect();

        setDropdownPosition({
            top: rect.bottom + 8,
            left: rect.left,
            width: rect.width,
        });
    };

    useLayoutEffect(() => {
        if (!showSuggestions) {
            return;
        }

        updateDropdownPosition();
    }, [showSuggestions, searchQuery, variant]);

    useEffect(() => {
        onSuggestionsOpenChange?.(showSuggestions);
    }, [showSuggestions, onSuggestionsOpenChange]);

    useEffect(() => {
        if (!showSuggestions) {
            return;
        }

        const handleReposition = () => updateDropdownPosition();

        window.addEventListener('resize', handleReposition);
        window.addEventListener('scroll', handleReposition, true);

        return () => {
            window.removeEventListener('resize', handleReposition);
            window.removeEventListener('scroll', handleReposition, true);
        };
    }, [showSuggestions]);

    useEffect(() => {
        function handleClickOutside(event) {
            const clickedInsideInput = containerRef.current?.contains(event.target);
            const clickedInsideDropdown = dropdownRef.current?.contains(event.target);

            if (!clickedInsideInput && !clickedInsideDropdown) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useDebouncedEffect(
        () => {
            const trimmedQuery = searchQuery.trim();

            if (trimmedQuery.length < 2) {
                setSuggestions([]);
                setLoading(false);
                return;
            }

            const requestId = ++requestIdRef.current;

            setLoading(true);

            fetch(route('search.suggestions', { query: { q: trimmedQuery } }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => (response.ok ? response.json() : []))
                .then((data) => {
                    if (requestId !== requestIdRef.current) {
                        return;
                    }

                    setSuggestions(Array.isArray(data) ? data : []);
                })
                .catch(() => {
                    if (requestId !== requestIdRef.current) {
                        return;
                    }

                    setSuggestions([]);
                })
                .finally(() => {
                    if (requestId === requestIdRef.current) {
                        setLoading(false);
                    }
                });
        },
        [searchQuery],
        300,
    );

    const handleSubmit = (event) => {
        event.preventDefault();

        const trimmedQuery = searchQuery.trim();

        if (!trimmedQuery) {
            return;
        }

        setOpen(false);

        router.get(
            route('search', { query: { q: trimmedQuery } }),
            {},
            {
                preserveScroll: false,
                onSuccess: () => onSubmitted?.(),
            },
        );
    };

    const isDesktop = variant === 'desktop';

    const searchField = (
        <div ref={containerRef} className="relative min-w-0 flex-1">
            <div className="relative flex items-center">
                <Search className={`absolute top-1/2 size-4 -translate-y-1/2 text-store-muted ${isDesktop ? 'left-4' : 'left-3'}`} />
                <input
                    type="search"
                    value={searchQuery}
                    onChange={(event) => {
                        setSearchQuery(event.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    placeholder="Search products..."
                    autoFocus={autoFocus}
                    className={
                        isDesktop
                            ? 'w-full rounded-full border border-gray-200 bg-store-surface/80 py-3 pl-11 pr-24 text-sm text-store-primary placeholder:text-store-muted focus:border-store-accent focus:bg-white focus:outline-none focus:ring-2 focus:ring-store-accent/20'
                            : 'w-full rounded-full border border-gray-200 bg-store-surface py-2.5 pl-10 pr-4 text-sm focus:border-store-accent focus:outline-none focus:ring-2 focus:ring-store-accent/20'
                    }
                />
                {isDesktop ? (
                    <button
                        type="submit"
                        className="absolute right-1.5 rounded-full bg-store-accent px-4 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                    >
                        Search
                    </button>
                ) : null}
            </div>

        </div>
    );

    const suggestionsPortal =
        typeof document !== 'undefined' &&
        createPortal(
            <AnimatePresence>
                {showSuggestions && (
                    <SearchSuggestions
                        query={searchQuery}
                        suggestions={suggestions}
                        loading={loading}
                        onSelect={() => setOpen(false)}
                        position={dropdownPosition}
                        dropdownRef={dropdownRef}
                    />
                )}
            </AnimatePresence>,
            document.body,
        );

    if (isDesktop) {
        return (
            <>
                <form onSubmit={handleSubmit} className="hidden min-w-0 flex-1 md:block">
                    {searchField}
                </form>
                {suggestionsPortal}
            </>
        );
    }

    return (
        <>
            <form onSubmit={handleSubmit} className="flex items-center gap-2">
                {searchField}
                <button
                    type="submit"
                    className="shrink-0 rounded-full bg-store-accent px-4 py-2.5 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                >
                    Search
                </button>
            </form>
            {suggestionsPortal}
        </>
    );
}
