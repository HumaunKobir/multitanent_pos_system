import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDown, Search } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

/**
 * @param {{
 *   label: string;
 *   items: { id: number | string; name: string }[];
 *   buildHref: (item: { id: number | string; name: string }) => string;
 *   isItemActive?: (item: { id: number | string; name: string }) => boolean;
 *   active?: boolean;
 *   open?: boolean;
 *   onOpenChange?: (open: boolean) => void;
 *   onNavigate?: () => void;
 *   emptyMessage?: string;
 * }} props
 */
export function FilterNavDropdown({
    label,
    items,
    buildHref,
    isItemActive,
    active = false,
    open: controlledOpen,
    onOpenChange,
    onNavigate,
    emptyMessage = 'No options available',
}) {
    const listId = useId();
    const [internalOpen, setInternalOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef(null);

    const open = controlledOpen ?? internalOpen;

    const setOpen = (next) => {
        if (controlledOpen === undefined) {
            setInternalOpen(next);
        }
        onOpenChange?.(next);
        if (!next) {
            setSearch('');
        }
    };

    const filteredItems = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return items;
        }

        return items.filter((item) => item.name.toLowerCase().includes(query));
    }, [items, search]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const handlePointerDownOutside = (event) => {
            if (containerRef.current?.contains(event.target)) {
                return;
            }

            setOpen(false);
        };

        document.addEventListener('mousedown', handlePointerDownOutside);

        return () => document.removeEventListener('mousedown', handlePointerDownOutside);
    }, [open]);

    const handleNavigate = () => {
        setOpen(false);
        onNavigate?.();
    };

    return (
        <div ref={containerRef} className="relative">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                data-active={active || open ? 'true' : 'false'}
                className="group relative flex items-center gap-1 rounded-lg bg-white/10 px-3 py-2 text-[13px] font-medium text-white/90 transition-colors hover:bg-white/15 hover:text-white data-[active=true]:bg-white/15 data-[active=true]:text-white"
                aria-expanded={open}
                aria-haspopup="listbox"
                aria-controls={listId}
            >
                {label}
                <ChevronDown
                    className={cn('size-3 opacity-80 transition-transform duration-200', open && 'rotate-180')}
                    aria-hidden
                />
                <span className="absolute inset-x-2.5 -bottom-0.5 h-0.5 origin-center scale-x-0 rounded-full bg-store-accent transition-transform duration-300 group-hover:scale-x-100 group-data-[active=true]:scale-x-100" />
            </button>

            <AnimatePresence>
                {open && (
                    <motion.div
                        initial={{ opacity: 0, y: 4 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: 4 }}
                        transition={{ duration: 0.14 }}
                        className="absolute left-1/2 top-[calc(100%+0.375rem)] z-50 w-[min(248px,calc(100vw-2rem))] -translate-x-1/2"
                    >
                        <div className="overflow-hidden rounded-xl border border-gray-200/90 bg-white p-2 shadow-lg shadow-black/8 ring-1 ring-black/5">
                            <div className="relative overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200/90 transition-shadow focus-within:bg-white focus-within:ring-2 focus-within:ring-store-accent/25">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search ..."
                                    className="w-full appearance-none rounded-full border-0 bg-transparent py-2 pl-8 pr-3 text-xs text-gray-900 placeholder:text-gray-400 focus:outline-none [&::-webkit-search-cancel-button]:hidden"
                                    autoFocus
                                />
                            </div>

                            <ul
                                id={listId}
                                role="listbox"
                                className="store-filter-scroll mt-2 flex max-h-52 flex-col gap-2 overflow-y-auto overscroll-contain py-0.5 pr-0.5"
                                aria-label={label}
                            >
                                {filteredItems.length > 0 ? (
                                    filteredItems.map((item) => {
                                        const selected = isItemActive?.(item) ?? false;

                                        return (
                                            <li key={item.id} role="option" aria-selected={selected}>
                                                <Link
                                                    href={buildHref(item)}
                                                    onClick={handleNavigate}
                                                    className={cn(
                                                        'flex items-center gap-2 rounded-lg px-2 py-1.5 text-[13px] leading-tight text-gray-800 transition-colors',
                                                        selected ? 'bg-gray-100 font-semibold' : 'font-medium hover:bg-gray-50',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'flex size-3.5 shrink-0 items-center justify-center rounded-full border-[1.5px]',
                                                            selected ? 'border-gray-900' : 'border-gray-300',
                                                        )}
                                                        aria-hidden
                                                    >
                                                        {selected ? (
                                                            <span className="size-1.5 rounded-full bg-gray-900" />
                                                        ) : null}
                                                    </span>
                                                    <span className="truncate">{item.name}</span>
                                                </Link>
                                            </li>
                                        );
                                    })
                                ) : (
                                    <li className="rounded-lg px-2 py-4 text-center text-xs text-gray-500">
                                        {items.length === 0 ? emptyMessage : 'No matches found'}
                                    </li>
                                )}
                            </ul>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
