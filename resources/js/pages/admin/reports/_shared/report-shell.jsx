import { route } from '@/lib/route';
import { router } from '@inertiajs/react';
import { BarChart2, Calendar, RotateCcw, Search, SlidersHorizontal, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dateInputRightIconClassName } from '@/components/ui/date-kit';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { toDateInputValue } from '@/lib/format-bd-date';
import { cn } from '@/lib/utils';

/**
 * Debounced Inertia visit when filter state changes (no Apply button).
 */
export function useLiveReportFilters(routeName, query, deps) {
    useDebouncedEffect(
        () => {
            const params = Object.fromEntries(
                Object.entries(query).filter(
                    ([, value]) => value !== '' && value != null && value !== 'all' && value !== '__all',
                ),
            );
            router.get(route(routeName), params, { preserveState: true, replace: true });
        },
        deps,
        350,
        { skipFirstRun: true },
    );
}

export function ReportPage({ title, description, filterBar, filterGridClassName, filterActions, children }) {
    return (
        <div className="min-w-0 px-2 py-1 sm:px-3">
            <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-4 py-3 shadow-sm sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-white/15">
                        <BarChart2 className="size-4 text-white" />
                    </div>
                    <div className="min-w-0">
                        <h1 className="text-base font-semibold text-white">{title}</h1>
                        <p className="text-xs text-white/60">{description}</p>
                    </div>
                </div>
            </div>

            {filterBar ? (
                <ReportFilterPanel className={filterGridClassName} actions={filterActions}>
                    {filterBar}
                </ReportFilterPanel>
            ) : null}

            {children}
        </div>
    );
}

export function ReportFilterPanel({ children, className, actions }) {
    return (
        <div className="mb-4 overflow-hidden rounded-lg border border-blue-950/10 bg-card shadow-sm">
            <div className="flex flex-wrap items-center gap-2.5 bg-blue-950 px-4 py-2.5">
                <div className="flex size-6 items-center justify-center rounded bg-white/15">
                    <SlidersHorizontal className="size-3.5 text-white" />
                </div>
                <h2 className="text-sm font-semibold uppercase tracking-wide text-white">Report Filters</h2>
                <div className="ml-auto flex flex-wrap items-center gap-2">
                    {actions}
                    <span className="hidden text-[10px] font-medium uppercase tracking-wider text-white/50 sm:inline">
                        Live update
                    </span>
                </div>
            </div>
            <div
                className={cn(
                    'grid gap-4 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 p-4 dark:from-slate-900/40 dark:via-card dark:to-blue-950/10 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
                    className,
                )}
            >
                {children}
            </div>
        </div>
    );
}

export function ReportFilterReset({ onClick, disabled = false }) {
    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onClick}
            disabled={disabled}
            className="h-7 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white"
        >
            <RotateCcw className="size-3.5" />
            Reset
        </Button>
    );
}

export function ReportFilterField({ label, icon: Icon, children, className = '' }) {
    return (
        <div className={['min-w-0', className].filter(Boolean).join(' ')}>
            <Label className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-blue-950 dark:text-blue-100">
                {Icon && (
                    <span className="flex size-5 items-center justify-center rounded bg-blue-950/10 text-blue-950 dark:bg-white/10 dark:text-blue-100">
                        <Icon className="size-3" />
                    </span>
                )}
                {label}
            </Label>
            <div className="rounded-md border border-blue-950/10 bg-white shadow-xs ring-1 ring-blue-950/5 dark:bg-slate-950/50">
                {children}
            </div>
        </div>
    );
}

const selectTriggerClassName =
    'h-9 w-full border-0 bg-transparent shadow-none focus-visible:ring-0 focus-visible:ring-offset-0';

export function ReportSelect({ value, onChange, placeholder, options = [], className = '' }) {
    const hasValue = value !== '' && value != null;

    return (
        <Select value={hasValue ? String(value) : undefined} onValueChange={onChange}>
            <SelectTrigger className={cn(selectTriggerClassName, className)}>
                <SelectValue placeholder={placeholder ?? 'Select…'} />
            </SelectTrigger>
            <SelectContent>
                {options.map((opt) => (
                    <SelectItem key={opt.value} value={String(opt.value)}>
                        {opt.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function ReportDateInput({ value, onChange, className = '' }) {
    return (
        <div className="relative">
            <Input
                type="date"
                value={toDateInputValue(value)}
                onChange={(e) => onChange(e.target.value)}
                className={cn('h-9 w-full border-0 bg-transparent pl-3 shadow-none focus-visible:ring-0', dateInputRightIconClassName, className)}
            />
            <Calendar
                className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-blue-900/70 dark:text-blue-300/80"
                aria-hidden
            />
        </div>
    );
}

export function ReportProductSearch({
    value,
    onChange,
    selectedProduct = null,
    branchId = 'all',
    searchRoute = 'report.products.search',
    placeholder = 'Search product by name or code…',
}) {
    const containerRef = useRef(null);
    const listboxRef = useRef(null);
    const timerRef = useRef(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [selectedLabel, setSelectedLabel] = useState(selectedProduct?.label ?? '');
    const [dropdownStyle, setDropdownStyle] = useState({ top: 0, left: 0, width: 0 });

    const updateDropdownPosition = useCallback(() => {
        const input = containerRef.current;

        if (!input) {
            return;
        }

        const rect = input.getBoundingClientRect();

        setDropdownStyle({
            top: rect.bottom + 4,
            left: rect.left,
            width: rect.width,
        });
    }, []);

    useEffect(() => {
        setSelectedLabel(selectedProduct?.label ?? '');
    }, [selectedProduct?.id, selectedProduct?.label]);

    useEffect(() => {
        if (!open) {
            return;
        }

        updateDropdownPosition();

        window.addEventListener('scroll', updateDropdownPosition, true);
        window.addEventListener('resize', updateDropdownPosition);

        return () => {
            window.removeEventListener('scroll', updateDropdownPosition, true);
            window.removeEventListener('resize', updateDropdownPosition);
        };
    }, [open, updateDropdownPosition]);

    async function fetchProducts(search, selectedId = null, activeBranchId = branchId) {
        setLoading(true);

        try {
            const params = new URLSearchParams();

            if (search) {
                params.set('search', search);
            }

            if (selectedId) {
                params.set('selected_id', String(selectedId));
            }

            if (activeBranchId && activeBranchId !== 'all') {
                params.set('branch_id', String(activeBranchId));
            }

            const queryString = params.toString();
            const url = route(searchRoute) + (queryString ? `?${queryString}` : '');
            const response = await fetch(url, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                setResults([]);

                return;
            }

            const data = await response.json();
            setResults(Array.isArray(data) ? data : []);
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        function handleClick(event) {
            const target = event.target;

            if (!(target instanceof Node)) {
                return;
            }

            if (containerRef.current?.contains(target)) {
                return;
            }

            if (listboxRef.current?.contains(target)) {
                return;
            }

            setOpen(false);
            setQuery('');
        }

        document.addEventListener('mousedown', handleClick);

        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        fetchProducts(query, value && value !== 'all' ? value : null, branchId);
    }, [branchId]);

    function handleFocus() {
        setOpen(true);
        updateDropdownPosition();
        fetchProducts(query, value && value !== 'all' ? value : null);
    }

    function handleChange(event) {
        const next = event.target.value;
        setQuery(next);
        setOpen(true);
        updateDropdownPosition();
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => fetchProducts(next), 350);
    }

    function selectProduct(product) {
        onChange(String(product.id));
        setSelectedLabel(product.label);
        setOpen(false);
        setQuery('');
        setResults([]);
    }

    function clearSelection() {
        onChange('all');
        setSelectedLabel('');
        setQuery('');
        setResults([]);
        setOpen(false);
    }

    const hasSelection = value && value !== 'all';
    const displayValue = open ? query : hasSelection ? selectedLabel : '';

    const dropdown =
        open && typeof document !== 'undefined'
            ? createPortal(
                  <div
                      ref={listboxRef}
                      style={{
                          position: 'fixed',
                          top: dropdownStyle.top,
                          left: dropdownStyle.left,
                          width: dropdownStyle.width,
                      }}
                      className="z-100 max-h-56 overflow-y-auto rounded-md border border-border bg-popover shadow-md"
                      onMouseDown={(event) => event.preventDefault()}
                  >
                      <button
                          type="button"
                          className="flex w-full px-3 py-2 text-left text-xs hover:bg-accent"
                          onMouseDown={(event) => {
                              event.preventDefault();
                              clearSelection();
                          }}
                      >
                          All products
                      </button>
                      {loading ? (
                          <p className="px-3 py-2 text-xs text-muted-foreground">Searching…</p>
                      ) : results.length === 0 ? (
                          <p className="px-3 py-2 text-xs text-muted-foreground">No products found.</p>
                      ) : (
                          results.map((product) => (
                              <button
                                  key={product.id}
                                  type="button"
                                  className="flex w-full px-3 py-2 text-left text-xs hover:bg-accent"
                                  onMouseDown={(event) => {
                                      event.preventDefault();
                                      selectProduct(product);
                                  }}
                              >
                                  {product.label}
                              </button>
                          ))
                      )}
                  </div>,
                  document.body,
              )
            : null;

    return (
        <div ref={containerRef} className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
                value={displayValue}
                onChange={handleChange}
                onFocus={handleFocus}
                placeholder={hasSelection && !open ? selectedLabel : placeholder}
                className="h-9 border-0 bg-transparent pr-8 pl-8 shadow-none focus-visible:ring-0"
            />
            {hasSelection && !open ? (
                <button
                    type="button"
                    onClick={clearSelection}
                    className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
                    aria-label="Clear product filter"
                >
                    <X className="size-3.5" />
                </button>
            ) : null}
            {dropdown}
        </div>
    );
}

export function ReportInfoBanner({ children }) {
    return (
        <div className="mb-4 rounded-lg border border-blue-950/15 bg-gradient-to-r from-blue-950/5 via-blue-50/50 to-transparent px-4 py-3 text-sm shadow-sm dark:from-blue-950/20 dark:via-blue-950/10">
            {children}
        </div>
    );
}

export function MoneyCell({ value, className = '' }) {
    return <span className={className}>৳{parseFloat(value ?? 0).toFixed(2)}</span>;
}
