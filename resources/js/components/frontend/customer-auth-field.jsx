import { storeCn, storeInputClass } from '@/lib/store-cn';

const fieldLabelClass = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-store-muted';

const fieldInputClass = storeCn(
    storeInputClass,
    'rounded-xl transition-all duration-200 hover:border-store-accent/50',
);

function fieldErrorClass(error) {
    return error ? 'border-red-400 bg-red-50/40 focus:border-red-500 focus:ring-red-500/15' : '';
}

export function CustomerAuthField({ label, error, className, id, ...props }) {
    const inputId = id || label?.toLowerCase()?.replace(/[^a-z0-9]+/g, '-');

    return (
        <div className={className}>
            {label && (
                <label htmlFor={inputId} className={fieldLabelClass}>
                    {label}
                </label>
            )}
            <input
                id={inputId}
                className={storeCn(fieldInputClass, 'py-3', fieldErrorClass(error))}
                {...props}
            />
            {error && <p className="mt-1.5 text-xs font-medium text-store-accent">{error}</p>}
        </div>
    );
}

export function CustomerAuthTextarea({ label, error, className, id, rows = 5, ...props }) {
    const inputId = id || label?.toLowerCase()?.replace(/[^a-z0-9]+/g, '-');

    return (
        <div className={className}>
            {label && (
                <label htmlFor={inputId} className={fieldLabelClass}>
                    {label}
                </label>
            )}
            <textarea
                id={inputId}
                rows={rows}
                className={storeCn(fieldInputClass, 'resize-y py-3', fieldErrorClass(error))}
                {...props}
            />
            {error && <p className="mt-1.5 text-xs font-medium text-store-accent">{error}</p>}
        </div>
    );
}
