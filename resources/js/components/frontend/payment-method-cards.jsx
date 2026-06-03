import { Banknote, CreditCard, Smartphone } from 'lucide-react';

const methods = [
    {
        value: 'cod',
        label: 'Cash on Delivery',
        desc: 'Pay on delivery',
        icon: Banknote,
    },
    {
        value: 'sslcommerz',
        label: 'SSLCommerz',
        desc: 'Card / mobile banking',
        icon: CreditCard,
    },
    {
        value: 'bkash',
        label: 'bKash',
        desc: 'Mobile wallet',
        icon: Smartphone,
    },
];

export function PaymentMethodCards({ value, onChange, error }) {
    return (
        <div>
            <p className="mb-2 text-sm font-medium text-store-primary">Payment method</p>
            <div className="grid gap-2 sm:grid-cols-3">
                {methods.map(({ value: v, label, desc, icon: Icon }) => (
                    <button
                        key={v}
                        type="button"
                        onClick={() => onChange(v)}
                        className={`flex flex-col items-start rounded-lg border p-3 text-left transition-all ${
                            value === v
                                ? 'border-store-accent bg-store-accent/5 ring-2 ring-store-accent/20'
                                : 'border-gray-200 hover:border-store-accent/50'
                        }`}
                    >
                        <Icon className={`mb-2 size-5 ${value === v ? 'text-store-accent' : 'text-store-muted'}`} />
                        <span className="text-sm font-semibold text-store-primary">{label}</span>
                        <span className="text-[11px] text-store-muted">{desc}</span>
                    </button>
                ))}
            </div>
            {error && <p className="mt-1 text-xs text-store-accent">{error}</p>}
        </div>
    );
}
