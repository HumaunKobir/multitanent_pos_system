import { Link, usePage } from '@inertiajs/react';

import { route } from '@/lib/route';

export function LoginPageLayout({ children }) {
    const { name } = usePage().props;

    return (
        <div className="min-h-svh flex bg-white dark:bg-zinc-950">
            <div className="hidden lg:flex w-[72px] shrink-0 bg-indigo-600 flex-col items-center justify-between py-10 select-none">
                <span
                    className="text-white font-black text-[11px] tracking-[0.3em] uppercase"
                    style={{ writingMode: 'vertical-rl', transform: 'rotate(180deg)' }}
                >
                    {name}
                </span>

                <div className="flex flex-col items-center gap-3">
                    {[...Array(6)].map((_, i) => (
                        <div key={i} className="w-1 h-1 rounded-full bg-white/30" />
                    ))}
                </div>

                <span
                    className="text-white/30 text-[9px] tracking-widest"
                    style={{ writingMode: 'vertical-rl', transform: 'rotate(180deg)' }}
                >
                    © {new Date().getFullYear()}
                </span>
            </div>

            <div className="hidden lg:flex lg:w-[42%] shrink-0 bg-zinc-950 flex-col justify-between px-14 py-14 select-none overflow-hidden relative">
                <div
                    className="absolute inset-0 opacity-100"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px)',
                        backgroundSize: '60px 60px',
                    }}
                />

                <div
                    className="absolute -right-6 bottom-16 text-white/[0.03] font-black select-none pointer-events-none leading-none"
                    style={{ fontSize: '22rem' }}
                    aria-hidden="true"
                >
                    01
                </div>

                <div className="relative z-10">
                    <span className="inline-flex items-center gap-2 text-[10px] text-indigo-400 uppercase tracking-[0.22em] font-semibold">
                        <span className="w-4 h-px bg-indigo-400 inline-block" />
                        Point of Sale
                    </span>
                </div>

                <div className="relative z-10">
                    <h2 className="text-white font-black leading-[1.04] tracking-tight" style={{ fontSize: '3.4rem' }}>
                        Sell more.
                        <br />
                        Track
                        <br />
                        everything.
                    </h2>
                    <div className="mt-8 h-[3px] w-12 bg-indigo-600" />
                    <p className="mt-5 text-zinc-400 text-[13.5px] leading-relaxed max-w-[260px]">
                        Inventory, sales, and customers — all in one dashboard built for modern businesses.
                    </p>
                </div>

                <div className="relative z-10 flex gap-8">
                    {[
                        { value: '100%', label: 'Uptime' },
                        { value: '< 1s', label: 'Load time' },
                        { value: '∞', label: 'Products' },
                    ].map((stat) => (
                        <div key={stat.label}>
                            <p className="text-white font-bold text-xl">{stat.value}</p>
                            <p className="text-zinc-500 text-[11px] mt-0.5">{stat.label}</p>
                        </div>
                    ))}
                </div>
            </div>

            <div className="flex-1 flex flex-col items-center justify-center bg-white dark:bg-zinc-900 px-8 py-12 relative">
                <div className="absolute top-0 right-0 w-24 h-24 bg-indigo-50 dark:bg-indigo-950/30" aria-hidden="true" />
                <div className="absolute top-0 right-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30" aria-hidden="true" />

                <Link
                    href={route('home')}
                    className="mb-10 font-black text-lg tracking-tight lg:hidden"
                >
                    {name}
                </Link>

                <div className="w-full max-w-sm relative z-10">{children}</div>
            </div>
        </div>
    );
}

export function staffAuthLayout({ children }) {
    return <LoginPageLayout>{children}</LoginPageLayout>;
}
