import { RequiredMark } from '@/components/form-field';
import { storeInputClass, storeCn } from '@/lib/store-cn';

export function StoreInput({ label, required = false, error, className, id, ...props }) {
    const inputId = id || label?.toLowerCase()?.replace(/\s+/g, '-');

    return (
        <div>
            {label && (
                <label htmlFor={inputId} className="mb-1 block text-sm font-medium text-store-primary">
                    {label}
                    {required && <RequiredMark />}
                </label>
            )}
            <input id={inputId} className={storeCn(storeInputClass, className)} {...props} />
            {error && <p className="mt-1 text-xs text-store-accent">{error}</p>}
        </div>
    );
}
