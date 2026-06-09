import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Package } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

const routes = {
    index: (query) => route('online-order.index', query ? { query } : undefined),
    show: (id) => route('online-order.show', { onlineOrder: id }),
};

const STATUS_COLORS = {
    1: 'bg-amber-100 text-amber-800',
    2: 'bg-blue-100 text-blue-800',
    3: 'bg-indigo-100 text-indigo-800',
    4: 'bg-cyan-100 text-cyan-800',
    5: 'bg-emerald-100 text-emerald-800',
    6: 'bg-red-100 text-red-800',
};

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusLabel(statuses, value) {
    const match = statuses.find((status) => status.value === value);

    return match?.label ?? 'Unknown';
}

export default function OnlineOrderIndex({ orders, filters, statuses }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ? String(filters.status) : 'all');
    const [courier, setCourier] = useState(filters.courier ?? 'all');

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                routes.index({
                    search: search || undefined,
                    status: status === 'all' ? undefined : status,
                    courier: courier === 'all' ? undefined : courier,
                }),
                { preserveState: true, replace: true },
            );
        },
        [search, status, courier],
        350,
        { skipFirstRun: true },
    );

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (orders.from ?? 0) + i,
        },
        {
            header: 'Order',
            render: (row) => (
                <div>
                    <p className="font-medium">#{row.id}</p>
                    <p className="text-xs text-muted-foreground">{formatDate(row.created_at)}</p>
                </div>
            ),
        },
        {
            header: 'Customer',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">{row.phone}</p>
                </div>
            ),
        },
        {
            header: 'Total',
            render: (row) => <span className="font-semibold">৳{Number(row.total).toFixed(0)}</span>,
        },
        {
            header: 'Payment',
            render: (row) => (
                <div>
                    <p className="text-sm uppercase">{row.payment_method ?? '—'}</p>
                    <p className="text-xs text-muted-foreground">{row.payment_status}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            render: (row) => (
                <span
                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_COLORS[row.status] ?? 'bg-muted text-muted-foreground'}`}
                >
                    {statusLabel(statuses, row.status)}
                </span>
            ),
        },
        {
            header: 'Courier',
            render: (row) =>
                row.courier_tracking_code ? (
                    <div>
                        <p className="text-xs font-medium uppercase text-indigo-700">{row.courier ?? 'steadfast'}</p>
                        <p className="font-mono text-xs text-muted-foreground">{row.courier_tracking_code}</p>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">Not sent</span>
                ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end">
                    <Link
                        href={routes.show(row.id)}
                        aria-label={`View order #${row.id}`}
                        className="inline-flex size-6 items-center justify-center rounded-none border border-blue-200 bg-blue-50 text-blue-600 transition-all duration-200 hover:-translate-y-1 hover:border-blue-500 hover:bg-blue-600 hover:text-white hover:shadow-sm hover:shadow-blue-500/35"
                    >
                        <Eye className="size-2.5" strokeWidth={2.5} />
                    </Link>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Online Orders" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Package className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Online Orders</h1>
                            <p className="text-xs text-white/60">Manage ecommerce orders and Steadfast courier shipments.</p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by order, customer, phone, tracking..."
                        className="max-w-sm"
                    />
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-[160px]">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statuses.map((item) => (
                                <SelectItem key={item.value} value={String(item.value)}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={courier} onValueChange={setCourier}>
                        <SelectTrigger className="w-[160px]">
                            <SelectValue placeholder="All couriers" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All couriers</SelectItem>
                            <SelectItem value="steadfast">Steadfast</SelectItem>
                            <SelectItem value="none">Not sent</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} rows={orders.data} rowKey="id" emptyMessage="No online orders found." />

                {orders.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {orders.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                className={[
                                    'border px-3 py-1 text-sm transition-colors',
                                    link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                preserveScroll
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
