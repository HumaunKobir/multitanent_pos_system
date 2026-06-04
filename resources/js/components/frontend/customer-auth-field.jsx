import { storeCn, storeInputClass } from '@/lib/store-cn';

export function CustomerAuthField({ label, error, className, id, ...props }) {
    const inputId = id || label?.toLowerCase()?.replace(/[^a-z0-9]+/g, '-');

    return (
        <div className={className}>
            {label && (
                <label
                    htmlFor={inputId}
                    className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-store-muted"
                >
                    {label}
                </label>
            )}
            <div className="group relative">
                <span
                    className={storeCn(
                        'pointer-events-none absolute left-3 top-1/2 z-10 h-0 w-0.5 -translate-y-1/2 rounded-full bg-store-accent/0 transition-all duration-200',
                        'group-focus-within:h-[55%] group-focus-within:bg-store-accent',
                        error && 'group-focus-within:bg-red-600',
                    )}
                    aria-hidden="true"
                />
                <input
                    id={inputId}
                    className={storeCn(
                        storeInputClass,
                        'rounded-xl py-3 transition-all duration-200',
                        'hover:border-store-accent/50',
                        error && 'border-red-400 bg-red-50/40 focus:border-red-500 focus:ring-red-500/15',
                    )}
                    {...props}
                />
            </div>
            {error && <p className="mt-1.5 text-xs font-medium text-store-accent">{error}</p>}
        </div>
    );
}
