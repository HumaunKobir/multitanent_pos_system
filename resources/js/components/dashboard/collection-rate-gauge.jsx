import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts';

export function CollectionRateGauge({ collection }) {
    const rate = collection?.rate ?? 0;
    const paid = collection?.paid ?? 0;
    const due = collection?.due ?? 0;
    const gross = collection?.gross ?? 0;

    const gaugeData = [
        { name: 'Collected', value: paid, color: '#059669' },
        { name: 'Due', value: due, color: '#e5e7eb' },
    ];

    return (
        <div className="flex h-full min-h-40 flex-col border border-border bg-card p-4">
            <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Collection Rate</p>
            <p className="mt-1 font-mono text-3xl font-bold tabular-nums">{rate.toFixed(1)}%</p>
            <p className="mt-0.5 text-xs text-muted-foreground">Month to date</p>

            <div className="relative mt-2 h-28 flex-1">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={gaugeData}
                            dataKey="value"
                            cx="50%"
                            cy="100%"
                            startAngle={180}
                            endAngle={0}
                            innerRadius={52}
                            outerRadius={72}
                            paddingAngle={0}
                            stroke="none"
                        >
                            {gaugeData.map((entry) => (
                                <Cell key={entry.name} fill={entry.color} />
                            ))}
                        </Pie>
                    </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-x-0 bottom-2 text-center">
                    <p className="font-mono text-xs tabular-nums text-emerald-700 dark:text-emerald-400">
                        ৳{paid.toFixed(2)} collected
                    </p>
                    <p className="font-mono text-[10px] tabular-nums text-muted-foreground">
                        of ৳{gross.toFixed(2)} total
                    </p>
                </div>
            </div>
        </div>
    );
}
