import { Link } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import AppLogoIcon from '@/components/app-logo-icon';
import { route } from '@/lib/route';
export default function AuthSimpleLayout({ children, title, description, }) {
    return (_jsx("div", { className: "flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10", children: _jsx("div", { className: "w-full max-w-sm", children: _jsxs("div", { className: "flex flex-col gap-8", children: [_jsxs("div", { className: "flex flex-col items-center gap-4", children: [_jsxs(Link, { href: route('home'), className: "flex flex-col items-center gap-2 font-medium", children: [_jsx("div", { className: "mb-1 flex h-9 w-9 items-center justify-center rounded-none", children: _jsx(AppLogoIcon, { className: "size-9 fill-current text-[var(--foreground)] dark:text-white" }) }), _jsx("span", { className: "sr-only", children: title })] }), _jsxs("div", { className: "space-y-2 text-center", children: [_jsx("h1", { className: "text-xl font-medium", children: title }), _jsx("p", { className: "text-center text-sm text-muted-foreground", children: description })] })] }), children] }) }) }));
}
