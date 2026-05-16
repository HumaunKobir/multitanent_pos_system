import { Link } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, } from '@/components/ui/card';
import { home } from '@/routes';
export default function AuthCardLayout({ children, title, description, }) {
    return (_jsx("div", { className: "flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10", children: _jsxs("div", { className: "flex w-full max-w-md flex-col gap-6", children: [_jsx(Link, { href: home(), className: "flex items-center gap-2 self-center font-medium", children: _jsx("div", { className: "flex h-9 w-9 items-center justify-center", children: _jsx(AppLogoIcon, { className: "size-9 fill-current text-black dark:text-white" }) }) }), _jsx("div", { className: "flex flex-col gap-6", children: _jsxs(Card, { className: "rounded-none", children: [_jsxs(CardHeader, { className: "px-10 pt-8 pb-0 text-center", children: [_jsx(CardTitle, { className: "text-xl", children: title }), _jsx(CardDescription, { children: description })] }), _jsx(CardContent, { className: "px-10 py-8", children: children })] }) })] }) }));
}
