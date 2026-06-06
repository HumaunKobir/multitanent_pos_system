import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

function formatShortCurrency(value) {
    if (value >= 100000) {
        return `৳${(value / 100000).toFixed(1)}L`;
    }

    if (value >= 1000) {
        return `৳${(value / 1000).toFixed(1)}K`;
    }

    return `৳${value}`;
}

export function BranchSalesChart({ data }) {
    const chartData = (data ?? []).map((row) => ({
        name: row.branch_name,
        sales: row.month_gross,
        paid: row.month_paid,
    }));

    if (chartData.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center border border-dashed border-border text-sm text-muted-foreground">
                No branch sales data yet
            </div>
        );
    }

    return (
        <div className="h-72 w-full border border-border bg-card p-3">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 4 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="hsl(var(--border))" />
                    <XAxis type="number" tickFormatter={formatShortCurrency} tick={{ fontSize: 11 }} />
                    <YAxis type="category" dataKey="name" width={100} tick={{ fontSize: 11 }} />
                    <Tooltip
                        formatter={(value) => [`৳${Number(value).toFixed(2)}`, '']}
                        contentStyle={{
                            borderRadius: 0,
                            border: '1px solid hsl(var(--border))',
                            background: 'hsl(var(--card))',
                        }}
                    />
                    <Bar dataKey="sales" name="Month Sales" fill="#059669" radius={0} />
                    <Bar dataKey="paid" name="Collected" fill="#2563eb" radius={0} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
