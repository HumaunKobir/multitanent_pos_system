import { Form, Head, Link, usePage } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
export default function Profile({ mustVerifyEmail, status, }) {
    const { auth } = usePage().props;

    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Profile settings" }), _jsx("h1", { className: "sr-only", children: "Profile settings" }), _jsxs("div", { className: "space-y-6", children: [_jsx(Heading, { variant: "small", title: "Profile information", description: "Update your name and email address" }), _jsx(Form, { ...ProfileController.update.form(), options: {
                            preserveScroll: true,
                        }, className: "space-y-6", children: ({ processing, errors }) => (_jsxs(_Fragment, { children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "name", children: "Name" }), _jsx(Input, { id: "name", className: "mt-1 block w-full", defaultValue: auth.user.name, name: "name", required: true, autoComplete: "name", placeholder: "Full name" }), _jsx(InputError, { className: "mt-2", message: errors.name })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "email", children: "Email address" }), _jsx(Input, { id: "email", type: "email", className: "mt-1 block w-full", defaultValue: auth.user.email, name: "email", required: true, autoComplete: "username", placeholder: "Email address" }), _jsx(InputError, { className: "mt-2", message: errors.email })] }), mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (_jsxs("div", { children: [_jsxs("p", { className: "-mt-4 text-sm text-muted-foreground", children: ["Your email address is unverified.", ' ', _jsx(Link, { href: send(), as: "button", className: "text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500", children: "Click here to resend the verification email." })] }), status ===
                                            'verification-link-sent' && (_jsx("div", { className: "mt-2 text-sm font-medium text-green-600", children: "A new verification link has been sent to your email address." }))] })), _jsx("div", { className: "flex items-center gap-4", children: _jsx(Button, { disabled: processing, "data-test": "update-profile-button", children: "Save" }) })] })) })] }), _jsx(DeleteUser, {})] }));
}
Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
