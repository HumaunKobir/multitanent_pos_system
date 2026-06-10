import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, UsersRound } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

const routes = {
    index: (query) => route('online-customer.index', query ? { query } : undefined),
    show: (id) => route('online-customer.show', { customer: id }),
};

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function CountBadge({ count, className = '' }) {
    if (!count) {
        return <span className="text-xs text-muted-foreground">0</span>;
    }

    return <span className={`text-sm font-semibold ${className}`}>{count}</span>;
}

export default function OnlineCustomerIndex({ customers, totalCustomers, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(routes.index({ search: search || undefined }), { preserveState: true, replace: true });
        },
        [search],
        350,
        { skipFirstRun: true },
    );

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (customers.from ?? 0) + i,
        },
        {
            header: 'Customer',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">{row.phone}</p>
                    {row.email && <p className="text-xs text-muted-foreground">{row.email}</p>}
                </div>
            ),
        },
        {
            header: 'Joined',
            render: (row) => <span className="text-sm">{formatDate(row.created_at)}</span>,
        },
        {
            header: 'Orders',
            align: 'center',
            render: (row) => <CountBadge count={row.orders_count} className="text-primary" />,
        },
        {
            header: 'Pending',
            align: 'center',
            render: (row) => <CountBadge count={row.pending_orders_count} className="text-amber-700" />,
        },
        {
            header: 'Processing',
            align: 'center',
            render: (row) => <CountBadge count={row.processing_orders_count} className="text-blue-700" />,
        },
        {
            header: 'Confirmed',
            align: 'center',
            render: (row) => <CountBadge count={row.confirmed_orders_count} className="text-cyan-700" />,
        },
        {
            header: 'Shipping',
            align: 'center',
            render: (row) => <CountBadge count={row.shipping_orders_count} className="text-indigo-700" />,
        },
        {
            header: 'Delivered',
            align: 'center',
            render: (row) => <CountBadge count={row.delivered_orders_count} className="text-emerald-700" />,
        },
        {
            header: 'Canceled',
            align: 'center',
            render: (row) => <CountBadge count={row.canceled_orders_count} className="text-red-700" />,
        },
        {
            header: 'COD',
            align: 'center',
            render: (row) => <CountBadge count={row.cod_orders_count} />,
        },
        {
            header: 'Online Pay',
            align: 'center',
            render: (row) => <CountBadge count={row.online_payment_orders_count} />,
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end">
                    <Link
                        href={routes.show(row.id)}
                        aria-label={`View customer ${row.name}`}
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
            <Head title="Online Customers" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <UsersRound className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Online Customers</h1>
                            <p className="text-xs text-white/60">
                                {totalCustomers} registered online customer{totalCustomers === 1 ? '' : 's'} with order and payment summary.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mb-4 flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, phone, or email..."
                        className="max-w-sm"
                    />
                </div>

                <DataTable columns={columns} rows={customers.data} rowKey="id" emptyMessage="No online customers found." />

                {customers.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {customers.links.map((link, i) => (
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
