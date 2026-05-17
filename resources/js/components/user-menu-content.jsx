import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { route } from '@/lib/route';
export function UserMenuContent({ user }) {
    const cleanup = useMobileNavigation();
    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (_jsxs(_Fragment, { children: [_jsx(DropdownMenuLabel, { className: "p-0 font-normal", children: _jsx("div", { className: "flex items-center gap-2 px-1 py-1.5 text-left text-sm", children: _jsx(UserInfo, { user: user, showEmail: true }) }) }), _jsx(DropdownMenuSeparator, {}), _jsx(DropdownMenuGroup, { children: _jsx(DropdownMenuItem, { asChild: true, children: _jsxs(Link, { className: "block w-full cursor-pointer", href: route('profile.edit'), prefetch: true, onClick: cleanup, children: [_jsx(Settings, { className: "mr-2" }), "Settings"] }) }) }), _jsx(DropdownMenuSeparator, {}), _jsx(DropdownMenuItem, { asChild: true, children: _jsxs(Link, { className: "block w-full cursor-pointer", href: route('logout'), method: "post", as: "button", onClick: handleLogout, "data-test": "logout-button", children: [_jsx(LogOut, { className: "mr-2" }), "Log out"] }) })] }));
}
