import { cn } from '@/lib/utils';

export const storeButtonVariants = {
    primary: 'store-gradient text-white shadow-md hover:opacity-90 active:scale-[0.98]',
    outline: 'border border-store-primary/20 bg-white text-store-primary hover:border-store-accent hover:text-store-accent',
    ghost: 'text-store-primary hover:bg-store-surface',
    accent: 'bg-store-accent text-white hover:bg-store-accent/90 active:scale-[0.98]',
};

export const storeInputClass =
    'w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-store-primary placeholder:text-gray-400 focus:border-store-accent focus:outline-none focus:ring-2 focus:ring-store-accent/20';

export function storeCn(...inputs) {
    return cn(inputs);
}
