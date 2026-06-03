export function PageHero({ title, subtitle }) {
    return (
        <div className="relative overflow-hidden bg-store-primary py-12 sm:py-16">
            <div className="absolute inset-0 store-gradient opacity-30" />
            <div className="relative store-container text-center">
                <h1 className="text-2xl font-bold text-white sm:text-3xl">{title}</h1>
                {subtitle && <p className="mt-2 text-sm text-white/70">{subtitle}</p>}
            </div>
        </div>
    );
}
