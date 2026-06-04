import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { LayoutDashboard, LogOut, Package, Settings, UserRound } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export function StoreAccountButton({ customer, className = '', onClick }) {
    const isLoggedIn = Boolean(customer);

    if (!isLoggedIn) {
        return (
            <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }} className={className}>
                <Link
                    href="/customer/login"
                    onClick={onClick}
                    className="group inline-flex items-center gap-2 rounded-full border border-store-primary/10 bg-store-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors duration-200 hover:border-store-accent hover:bg-store-accent"
                >
                    <UserRound className="size-4 shrink-0" strokeWidth={2.25} />
                    <span>Login</span>
                </Link>
            </motion.div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'flex size-10 items-center justify-center overflow-hidden rounded-full border-2 border-gray-200 bg-store-surface ring-store-accent/0 transition hover:border-store-accent hover:ring-2 hover:ring-store-accent/30 focus:outline-none focus:ring-2 focus:ring-store-accent/40 data-[state=open]:border-store-accent data-[state=open]:ring-2 data-[state=open]:ring-store-accent/30',
                        className,
                    )}
                    aria-label="Account menu"
                >
                    {customer.image_url ? (
                        <img
                            src={customer.image_url}
                            alt={customer.name}
                            className="size-full object-cover"
                        />
                    ) : (
                        <UserRound className="size-5 text-store-muted" strokeWidth={2} />
                    )}
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="z-[60] min-w-[11rem] overflow-hidden rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg"
            >
                <DropdownMenuItem asChild className="cursor-pointer rounded-lg focus:bg-store-surface">
                    <Link
                        href="/customer/dashboard"
                        className="flex w-full items-center gap-2 px-2 py-2 text-sm text-store-primary"
                        onClick={onClick}
                    >
                        <LayoutDashboard className="size-4 text-store-accent" />
                        Dashboard
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild className="cursor-pointer rounded-lg focus:bg-store-surface">
                    <Link
                        href="/customer/orders"
                        className="flex w-full items-center gap-2 px-2 py-2 text-sm text-store-primary"
                        onClick={onClick}
                    >
                        <Package className="size-4 text-store-accent" />
                        Online Orders
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild className="cursor-pointer rounded-lg focus:bg-store-surface">
                    <Link
                        href="/customer/settings"
                        className="flex w-full items-center gap-2 px-2 py-2 text-sm text-store-primary"
                        onClick={onClick}
                    >
                        <Settings className="size-4 text-store-accent" />
                        My Profile
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuSeparator className="bg-gray-100" />

                <DropdownMenuItem
                    asChild
                    variant="destructive"
                    className="cursor-pointer rounded-lg focus:bg-red-50"
                >
                    <Link
                        href="/customer/logout"
                        method="post"
                        as="button"
                        className="flex w-full items-center gap-2 px-2 py-2 text-sm"
                        onClick={onClick}
                    >
                        <LogOut className="size-4" />
                        Logout
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
