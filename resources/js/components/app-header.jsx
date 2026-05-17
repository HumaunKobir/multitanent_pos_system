import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Menu, Search, Shield } from 'lucide-react';

import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { route } from '@/lib/route';

const mainNavItems = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
        icon: LayoutGrid,
        activeMatch: 'exact',
    },
    {
        title: 'Admin',
        href: route('admin.dashboard'),
        icon: Shield,
        activeMatch: 'prefix',
    },
];

const headerNavSelected =
    'border-indigo-500/90 bg-indigo-600 text-white shadow-[0_6px_20px_-6px_rgba(79,70,229,0.48),0_2px_8px_-2px_rgba(67,56,202,0.3)] dark:border-indigo-400/70 dark:bg-indigo-700 dark:shadow-[0_6px_20px_-6px_rgba(67,56,202,0.42),0_2px_8px_-2px_rgba(55,48,163,0.32)]';

/**
 * @param {{ breadcrumbs?: { title: string; href: string }[] }} props
 */
export function AppHeader({ breadcrumbs = [] }) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <>
            <div className="border-b border-border/80 bg-background">
                <div className="mx-auto flex h-14 items-center gap-3 px-4 md:max-w-7xl md:px-6">
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="outline" size="icon" className="size-8 shrink-0 rounded-none border-border">
                                    <Menu className="size-4.5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex w-[min(100%,18rem)] flex-col border-r border-border bg-sidebar p-0"
                            >
                                <SheetTitle className="sr-only">Navigation menu</SheetTitle>
                                <SheetHeader className="border-b border-sidebar-border px-4 py-4 text-left">
                                    <AppLogoIcon className="size-7 fill-current text-sidebar-foreground" />
                                </SheetHeader>
                                <nav className="flex flex-1 flex-col gap-0.5 p-3" aria-label="Main">
                                    {mainNavItems.map((item) => {
                                        const active =
                                            item.activeMatch === 'prefix'
                                                ? isCurrentOrParentUrl(item.href)
                                                : isCurrentUrl(item.href);
                                        const Icon = item.icon;

                                        return (
                                            <Link
                                                key={item.title}
                                                href={item.href}
                                                prefetch
                                                className={cn(
                                                    'flex items-center gap-3 border border-transparent px-3 py-2.5 text-sm font-medium transition-[color,background-color,border-color,box-shadow]',
                                                    active
                                                        ? cn(headerNavSelected, 'font-semibold')
                                                        : 'text-muted-foreground hover:border-border/80 hover:bg-sidebar-accent/60 hover:text-foreground',
                                                )}
                                            >
                                                {Icon ? (
                                                    <Icon
                                                        className={cn('size-4 shrink-0', active && 'text-white')}
                                                        strokeWidth={active ? 2.25 : 2}
                                                    />
                                                ) : null}
                                                {item.title}
                                            </Link>
                                        );
                                    })}
                                </nav>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link href={route('dashboard')} prefetch className="flex shrink-0 items-center gap-2">
                        <AppLogo />
                    </Link>

                    <nav
                        className="ml-4 hidden h-9 items-stretch border border-border bg-muted/35 lg:inline-flex"
                        aria-label="Main"
                    >
                        {mainNavItems.map((item) => {
                            const active =
                                item.activeMatch === 'prefix'
                                    ? isCurrentOrParentUrl(item.href)
                                    : isCurrentUrl(item.href);
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    prefetch
                                    className={cn(
                                        'inline-flex items-center gap-2 border-r border-border px-4 text-sm font-medium transition-[color,background-color,box-shadow] last:border-r-0',
                                        active
                                            ? cn(headerNavSelected, 'relative z-1 font-semibold')
                                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                    )}
                                >
                                    {Icon ? (
                                        <Icon className={cn('size-4 shrink-0 opacity-90', active && 'text-white opacity-100')} aria-hidden />
                                    ) : null}
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="ml-auto flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-8 rounded-none border-border bg-transparent"
                            aria-label="Search"
                        >
                            <Search className="size-4.5 text-muted-foreground" />
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8 rounded-none border-border p-0"
                                    aria-label="Account menu"
                                >
                                    <Avatar className="size-7 overflow-hidden rounded-none">
                                        <AvatarImage src={auth.user?.avatar} alt={auth.user?.name} />
                                        <AvatarFallback className="rounded-none bg-muted text-xs font-medium text-foreground">
                                            {getInitials(auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56 rounded-none" align="end">
                                {auth.user ? <UserMenuContent user={auth.user} /> : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            {breadcrumbs.length > 1 ? (
                <div className="border-b border-border/70 bg-muted/15">
                    <div className="mx-auto flex h-11 w-full items-center px-4 text-muted-foreground md:max-w-7xl md:px-6">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            ) : null}
        </>
    );
}
