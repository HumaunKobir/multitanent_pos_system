import { jsx as _jsx, Fragment as _Fragment, jsxs as _jsxs } from "react/jsx-runtime";
import AppLogoIcon from '@/components/app-logo-icon';
export default function AppLogo() {
    return (_jsxs(_Fragment, { children: [_jsx("div", { className: "flex aspect-square size-8 items-center justify-center rounded-none bg-sidebar-primary text-sidebar-primary-foreground", children: _jsx(AppLogoIcon, { className: "size-5 fill-current text-white dark:text-black" }) }), _jsx("div", { className: "ml-1 grid flex-1 text-left text-sm", children: _jsx("span", { className: "mb-0.5 truncate leading-tight font-semibold", children: "Laravel Starter Kit" }) })] }));
}
