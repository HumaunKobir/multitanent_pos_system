import { Form, Head } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { route, routeForm } from '@/lib/route';
export default function Register() {
    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Register" }), _jsx(Form, { ...routeForm('register.store'), resetOnSuccess: ['password', 'password_confirmation'], disableWhileProcessing: true, className: "flex flex-col gap-6", children: ({ processing, errors }) => (_jsxs(_Fragment, { children: [_jsxs("div", { className: "grid gap-6", children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "name", children: "Name" }), _jsx(Input, { id: "name", type: "text", required: true, autoFocus: true, tabIndex: 1, autoComplete: "name", name: "name", placeholder: "Full name" }), _jsx(InputError, { message: errors.name, className: "mt-2" })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "email", children: "Email address" }), _jsx(Input, { id: "email", type: "email", required: true, tabIndex: 2, autoComplete: "email", name: "email", placeholder: "email@example.com" }), _jsx(InputError, { message: errors.email })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password", children: "Password" }), _jsx(PasswordInput, { id: "password", required: true, tabIndex: 3, autoComplete: "new-password", name: "password", placeholder: "Password" }), _jsx(InputError, { message: errors.password })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password_confirmation", children: "Confirm password" }), _jsx(PasswordInput, { id: "password_confirmation", required: true, tabIndex: 4, autoComplete: "new-password", name: "password_confirmation", placeholder: "Confirm password" }), _jsx(InputError, { message: errors.password_confirmation })] }), _jsxs(Button, { type: "submit", className: "mt-2 w-full", tabIndex: 5, "data-test": "register-user-button", children: [processing && _jsx(Spinner, {}), "Create account"] })] }), _jsxs("div", { className: "text-center text-sm text-muted-foreground", children: ["Already have an account?", ' ', _jsx(TextLink, { href: route('login'), tabIndex: 6, children: "Log in" })] })] })) })] }));
}
Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
