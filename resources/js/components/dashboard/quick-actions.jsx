import { Can } from '@/components/can';
import { route } from '@/lib/route';
import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    CircleDollarSign,
    HandCoins,
    Plus,
    UsersRound,
    Wallet,
} from 'lucide-react';

const actions = [
    {
        label: 'New Sale',
        href: () => route('inventory.sell.create'),
        permission: 'inventory.sell.create',
        icon: CircleDollarSign,
    },
    {
        label: 'New Purchase',
        href: () => route('inventory.purchase.create'),
        permission: 'inventory.purchase.create',
        icon: HandCoins,
    },
    {
        label: 'Sale Return',
        href: () => route('inventory.sale-return.create'),
        permission: 'inventory.sale-return.create',
        icon: ArrowLeftRight,
    },
    {
        label: 'Supplier Payment',
        href: () => route('party.supplier-payment.index'),
        permission: 'party.supplier-payment.view',
        icon: Wallet,
    },
    {
        label: 'Customers',
        href: () => route('party.customer.index'),
        permission: 'party.customer.view',
        icon: UsersRound,
    },
];

export function QuickActions() {
    return (
        <div className="border border-border bg-card p-4 shadow-none">
            <p className="mb-3 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Quick Actions</p>
            <div className="flex flex-wrap gap-2">
                {actions.map((action) => (
                    <Can key={action.label} permission={action.permission}>
                        <Link
                            href={action.href()}
                            className="inline-flex items-center gap-2 border border-border bg-background px-3 py-2 text-xs font-medium transition-colors hover:bg-muted"
                        >
                            <action.icon className="size-3.5" />
                            <Plus className="size-3 opacity-50" />
                            {action.label}
                        </Link>
                    </Can>
                ))}
            </div>
        </div>
    );
}
