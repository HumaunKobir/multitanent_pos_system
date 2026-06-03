import { storeCn } from '@/lib/store-cn';

const variants = {
    sale: 'bg-store-accent text-white',
    new: 'store-gradient text-white',
    default: 'bg-store-primary text-white',
};

export function StoreBadge({ variant = 'default', className, children }) {
    return (
        <span
            className={storeCn(
                'inline-flex items-center rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                variants[variant],
                className,
            )}
        >
            {children}
        </span>
    );
}
