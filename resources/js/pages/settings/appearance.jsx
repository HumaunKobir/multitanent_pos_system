import { Head } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';
export default function Appearance() {
    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Appearance settings" }), _jsx("h1", { className: "sr-only", children: "Appearance settings" }), _jsxs("div", { className: "space-y-6", children: [_jsx(Heading, { variant: "small", title: "Appearance settings", description: "Update your account's appearance settings" }), _jsx(AppearanceTabs, {})] })] }));
}
Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
