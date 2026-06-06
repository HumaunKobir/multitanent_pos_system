import { Banknote, CreditCard } from 'lucide-react';
import { cn } from '@/lib/utils';

const methods = [
    {
        value: 'cod',
        label: 'Cash on Delivery',
        desc: 'Pay when you receive',
        icon: Banknote,
    },
    {
        value: 'sslcommerz',
        label: 'SSLCommerz',
        desc: 'Card / mobile banking',
        icon: CreditCard,
    },
];

export function PaymentMethodCards({ value, onChange, error }) {
    return (
        <div>
            <div className="grid grid-cols-1 divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                {methods.map(({ value: v, label, desc, icon: Icon }) => {
                    const selected = value === v;

                    return (
                        <button
                            key={v}
                            type="button"
                            onClick={() => onChange(v)}
                            className={cn(
                                'group px-3 py-3 text-left transition-all',
                                selected
                                    ? 'bg-store-accent/5 ring-2 ring-inset ring-store-accent/30'
                                    : 'bg-white hover:bg-store-surface/50',
                            )}
                        >
                            <span
                                className={cn(
                                    'inline-flex size-8 items-center justify-center rounded-lg border transition-colors',
                                    selected
                                        ? 'border-store-accent/30 bg-store-accent/10'
                                        : 'border-gray-200 bg-store-surface group-hover:border-store-accent/20',
                                )}
                            >
                                <Icon
                                    className={cn('size-4', selected ? 'text-store-accent' : 'text-store-muted')}
                                    aria-hidden
                                />
                            </span>
                            <span className="mt-2 block text-[11px] font-bold text-store-primary">{label}</span>
                            <span className="mt-0.5 block text-[10px] text-store-muted">{desc}</span>
                        </button>
                    );
                })}
            </div>
            {error && <p className="mt-2 text-[11px] text-red-600">{error}</p>}
        </div>
    );
}
