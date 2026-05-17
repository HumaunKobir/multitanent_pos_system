// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { route, routeForm } from '@/lib/route';
export default function ForgotPassword({ status }) {
    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Forgot password" }), status && (_jsx("div", { className: "mb-4 text-center text-sm font-medium text-green-600", children: status })), _jsxs("div", { className: "space-y-6", children: [_jsx(Form, { ...routeForm('password.email'), children: ({ processing, errors }) => (_jsxs(_Fragment, { children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "email", children: "Email address" }), _jsx(Input, { id: "email", type: "email", name: "email", autoComplete: "off", autoFocus: true, placeholder: "email@example.com" }), _jsx(InputError, { message: errors.email })] }), _jsx("div", { className: "my-6 flex items-center justify-start", children: _jsxs(Button, { className: "w-full", disabled: processing, "data-test": "email-password-reset-link-button", children: [processing && (_jsx(LoaderCircle, { className: "h-4 w-4 animate-spin" })), "Email password reset link"] }) })] })) }), _jsxs("div", { className: "space-x-1 text-center text-sm text-muted-foreground", children: [_jsx("span", { children: "Or, return to" }), _jsx(TextLink, { href: route('login'), children: "log in" })] })] })] }));
}
ForgotPassword.layout = {
    title: 'Forgot password',
    description: 'Enter your email to receive a password reset link',
};
