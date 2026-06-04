import { storeCn } from '@/lib/store-cn';

export function CustomerAuthField({ label, error, className, id, ...props }) {
    const inputId = id || label?.toLowerCase()?.replace(/[^a-z0-9]+/g, '-');

    return (
        <div className={className}>
            {label && (
                <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-auth-ink">
                    {label}
                </label>
            )}
            <input
                id={inputId}
                className={storeCn(
                    'w-full rounded-xl border border-auth-border bg-auth-bg/50 px-4 py-2.5 text-sm text-auth-ink',
                    'placeholder:text-auth-muted/70 transition-colors',
                    'focus:border-auth-accent focus:bg-white focus:outline-none focus:ring-2 focus:ring-auth-accent/20',
                    error && 'border-auth-error focus:border-auth-error focus:ring-auth-error/20',
                )}
                {...props}
            />
            {error && <p className="mt-1.5 text-xs font-medium text-auth-error">{error}</p>}
        </div>
    );
}
