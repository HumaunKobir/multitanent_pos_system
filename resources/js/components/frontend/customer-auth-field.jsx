import { storeCn } from '@/lib/store-cn';

export function CustomerAuthField({ label, error, className, id, ...props }) {
    const inputId = id || label?.toLowerCase()?.replace(/[^a-z0-9]+/g, '-');

    return (
        <div className={className}>
            {label && (
                <label
                    htmlFor={inputId}
                    className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-auth-muted"
                >
                    {label}
                </label>
            )}
            <div className="group relative">
                <span
                    className={storeCn(
                        'pointer-events-none absolute left-3 top-1/2 z-10 h-0 w-0.5 -translate-y-1/2 rounded-full bg-auth-accent/0 transition-all duration-200',
                        'group-focus-within:h-[55%] group-focus-within:bg-auth-accent',
                        error && 'group-focus-within:bg-auth-error',
                    )}
                    aria-hidden="true"
                />
                <input
                    id={inputId}
                    className={storeCn(
                        'w-full rounded-xl border border-auth-border bg-stone-50/80 py-3 pl-4 pr-4 text-sm text-auth-ink',
                        'placeholder:text-auth-muted/60 transition-all duration-200',
                        'hover:border-auth-accent/40 hover:bg-white',
                        'focus:border-auth-accent focus:bg-white focus:outline-none focus:ring-2 focus:ring-auth-accent/15',
                        error && 'border-auth-error bg-red-50/30 focus:border-auth-error focus:ring-auth-error/15',
                    )}
                    {...props}
                />
            </div>
            {error && <p className="mt-1.5 text-xs font-medium text-auth-error">{error}</p>}
        </div>
    );
}
