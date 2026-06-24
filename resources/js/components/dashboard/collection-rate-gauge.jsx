import { motion } from 'framer-motion';

export function CollectionRateGauge({ collection }) {
    const rate = collection?.rate ?? 0;
    const paid = collection?.paid ?? 0;
    const gross = collection?.gross ?? 0;

    const clampedRate = Math.min(100, Math.max(0, rate));
    const radius = 38;
    const strokeWidth = 10;
    const center = 50;
    const circumference = 2 * Math.PI * radius;
    const progressOffset = circumference * (1 - clampedRate / 100);

    return (
        <div className="flex h-full min-h-40 flex-col border border-border bg-card p-4">
            <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Collection Rate</p>
            <p className="mt-1 font-mono text-3xl font-bold tabular-nums">{rate.toFixed(1)}%</p>
            <p className="mt-0.5 text-xs text-muted-foreground">Month to date</p>

            <div className="relative mx-auto mt-3 size-28 shrink-0">
                <svg viewBox="0 0 100 100" className="size-full" aria-hidden="true">
                    <circle
                        cx={center}
                        cy={center}
                        r={radius}
                        fill="none"
                        className="stroke-border"
                        strokeWidth={strokeWidth}
                    />
                    <motion.circle
                        cx={center}
                        cy={center}
                        r={radius}
                        fill="none"
                        className="stroke-emerald-600 dark:stroke-emerald-500"
                        strokeWidth={strokeWidth}
                        strokeLinecap="round"
                        strokeDasharray={circumference}
                        initial={{ strokeDashoffset: circumference }}
                        animate={{ strokeDashoffset: progressOffset }}
                        transition={{ duration: 0.9, ease: [0.22, 1, 0.36, 1] }}
                        transform={`rotate(-90 ${center} ${center})`}
                    />
                </svg>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-2 text-center">
                    <p className="font-mono text-[10px] leading-tight tabular-nums text-emerald-700 dark:text-emerald-400">
                        ৳{paid.toFixed(2)}
                    </p>
                    <p className="font-mono text-[9px] leading-tight tabular-nums text-muted-foreground">
                        of ৳{gross.toFixed(2)}
                    </p>
                </div>
            </div>
        </div>
    );
}
