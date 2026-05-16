import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
export function UserInfo({ user, showEmail = false, }) {
    const getInitials = useInitials();

    return (_jsxs(_Fragment, { children: [_jsxs(Avatar, { className: "h-8 w-8 overflow-hidden rounded-none", children: [_jsx(AvatarImage, { src: user.avatar, alt: user.name }), _jsx(AvatarFallback, { className: "rounded-none bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white", children: getInitials(user.name) })] }), _jsxs("div", { className: "grid flex-1 text-left text-sm leading-tight", children: [_jsx("span", { className: "truncate font-medium", children: user.name }), showEmail && (_jsx("span", { className: "truncate text-xs text-muted-foreground", children: user.email }))] })] }));
}
