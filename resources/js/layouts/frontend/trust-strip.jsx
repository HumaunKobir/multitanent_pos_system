import { ShieldCheck, Truck, Wallet } from 'lucide-react';

export function TrustStrip() {
    const items = [
        { icon: ShieldCheck, title: 'Satisfaction guaranteed', desc: '100% quality assured' },
        { icon: Truck, title: 'Cash on delivery', desc: 'Inside & outside Dhaka' },
        { icon: Wallet, title: 'Flexible payment', desc: 'SSLCommerz · COD' },
    ];

    return (
        <section className="border-y border-amber-100 bg-store-warm">
            <div className="store-container grid gap-4 py-5 sm:grid-cols-3 sm:gap-6">
                {items.map(({ icon: Icon, title, desc }) => (
                    <div key={title} className="flex items-center gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white shadow-sm">
                            <Icon className="size-4 text-store-accent" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-store-primary">{title}</p>
                            <p className="text-[11px] text-store-muted">{desc}</p>
                        </div>
                    </div>
                ))}
                <div className="hidden items-center justify-end sm:flex">
                    <img src="/images/SSLCommerz.png" alt="SSLCommerz" className="h-8 object-contain opacity-80" onError={(e) => { e.target.style.display = 'none'; }} />
                </div>
            </div>
        </section>
    );
}
