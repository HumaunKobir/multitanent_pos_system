import { Form, Head } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
// Components
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { route, routeForm } from '@/lib/route';
export default function VerifyEmail({ status }) {
    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Email verification" }), status === 'verification-link-sent' && (_jsx("div", { className: "mb-4 text-center text-sm font-medium text-green-600", children: "A new verification link has been sent to the email address you provided during registration." })), _jsx(Form, { ...routeForm('verification.send'), className: "space-y-6 text-center", children: ({ processing }) => (_jsxs(_Fragment, { children: [_jsxs(Button, { disabled: processing, variant: "secondary", children: [processing && _jsx(Spinner, {}), "Resend verification email"] }), _jsx(TextLink, { href: route('logout'), className: "mx-auto block text-sm", children: "Log out" })] })) })] }));
}
VerifyEmail.layout = {
    title: 'Verify email',
    description: 'Please verify your email address by clicking on the link we just emailed to you.',
};
