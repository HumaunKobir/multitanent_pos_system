import { Link, usePage } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
export default function AuthSplitLayout({ children, title, description, }) {
    const { name } = usePage().props;

    return (_jsxs("div", { className: "relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0", children: [_jsxs("div", { className: "relative hidden h-full flex-col bg-muted p-10 text-white lg:flex dark:border-r", children: [_jsx("div", { className: "absolute inset-0 bg-zinc-900" }), _jsxs(Link, { href: home(), className: "relative z-20 flex items-center text-lg font-medium", children: [_jsx(AppLogoIcon, { className: "mr-2 size-8 fill-current text-white" }), name] })] }), _jsx("div", { className: "w-full lg:p-8", children: _jsxs("div", { className: "mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]", children: [_jsx(Link, { href: home(), className: "relative z-20 flex items-center justify-center lg:hidden", children: _jsx(AppLogoIcon, { className: "h-10 fill-current text-black sm:h-12" }) }), _jsxs("div", { className: "flex flex-col items-start gap-2 text-left sm:items-center sm:text-center", children: [_jsx("h1", { className: "text-xl font-medium", children: title }), _jsx("p", { className: "text-sm text-balance text-muted-foreground", children: description })] }), children] }) })] }));
}
