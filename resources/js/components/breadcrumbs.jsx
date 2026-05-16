import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator, } from '@/components/ui/breadcrumb';
export function Breadcrumbs({ breadcrumbs, }) {
    return (_jsx(_Fragment, { children: breadcrumbs.length > 0 && (_jsx(Breadcrumb, { children: _jsx(BreadcrumbList, { children: breadcrumbs.map((item, index) => {
                    const isLast = index === breadcrumbs.length - 1;

                    return (_jsxs(Fragment, { children: [_jsx(BreadcrumbItem, { children: isLast ? (_jsx(BreadcrumbPage, { children: item.title })) : (_jsx(BreadcrumbLink, { asChild: true, children: _jsx(Link, { href: item.href, children: item.title }) })) }), !isLast && _jsx(BreadcrumbSeparator, {})] }, index));
                }) }) })) }));
}
