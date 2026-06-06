import { formatBdDate } from '@/lib/format-bd-date';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

function formatAxisDate(date) {
    if (!date) {
        return '';
    }

    const parts = date.split('-');
    if (parts.length !== 3) {
        return date;
    }

    return `${parts[2]}/${parts[1]}`;
}

export function SalesTrendChart({ data }) {
    const chartData = (data ?? []).map((row) => ({
        ...row,
        label: formatAxisDate(row.date),
    }));

    if (chartData.every((row) => row.gross === 0 && row.count === 0)) {
        return (
            <div className="flex h-64 items-center justify-center border border-dashed border-border text-sm text-muted-foreground">
                No sales in the last 30 days
            </div>
        );
    }

    return (
        <div className="h-72 w-full border border-border bg-card p-3">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData} margin={{ top: 8, right: 16, left: 0, bottom: 0 }}>
                    <defs>
                        <linearGradient id="salesTrendFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#059669" stopOpacity={0.35} />
                            <stop offset="95%" stopColor="#059669" stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="label" tick={{ fontSize: 10 }} interval="preserveStartEnd" />
                    <YAxis tick={{ fontSize: 11 }} tickFormatter={(v) => (v >= 1000 ? `${(v / 1000).toFixed(0)}K` : v)} />
                    <Tooltip
                        labelFormatter={(_, payload) => {
                            const date = payload?.[0]?.payload?.date;

                            return date ? formatBdDate(date) : '';
                        }}
                        formatter={(value, name) => [
                            name === 'count' ? value : `৳${Number(value).toFixed(2)}`,
                            name === 'gross' ? 'Sales' : name === 'paid' ? 'Collected' : 'Invoices',
                        ]}
                        contentStyle={{
                            borderRadius: 0,
                            border: '1px solid hsl(var(--border))',
                            background: 'hsl(var(--card))',
                        }}
                    />
                    <Area type="monotone" dataKey="gross" stroke="#059669" fill="url(#salesTrendFill)" strokeWidth={2} />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}
