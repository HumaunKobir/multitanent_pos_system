import { storeButtonVariants, storeCn } from '@/lib/store-cn';

export function StoreButton({
    variant = 'primary',
    className,
    children,
    ...props
}) {
    return (
        <button
            className={storeCn(
                'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50',
                storeButtonVariants[variant],
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}
