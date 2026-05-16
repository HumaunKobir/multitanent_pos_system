import { Form, Head } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
export default function Login({ status, canResetPassword, canRegister, }) {
    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Log in" }), _jsx(Form, { ...store.form(), resetOnSuccess: ['password'], className: "flex flex-col gap-6", children: ({ processing, errors }) => (_jsxs(_Fragment, { children: [_jsxs("div", { className: "grid gap-6", children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "email", children: "Email address" }), _jsx(Input, { id: "email", type: "email", name: "email", required: true, autoFocus: true, tabIndex: 1, autoComplete: "email", placeholder: "email@example.com" }), _jsx(InputError, { message: errors.email })] }), _jsxs("div", { className: "grid gap-2", children: [_jsxs("div", { className: "flex items-center", children: [_jsx(Label, { htmlFor: "password", children: "Password" }), canResetPassword && (_jsx(TextLink, { href: request(), className: "ml-auto text-sm", tabIndex: 5, children: "Forgot password?" }))] }), _jsx(PasswordInput, { id: "password", name: "password", required: true, tabIndex: 2, autoComplete: "current-password", placeholder: "Password" }), _jsx(InputError, { message: errors.password })] }), _jsxs("div", { className: "flex items-center space-x-3", children: [_jsx(Checkbox, { id: "remember", name: "remember", tabIndex: 3 }), _jsx(Label, { htmlFor: "remember", children: "Remember me" })] }), _jsxs(Button, { type: "submit", className: "mt-4 w-full", tabIndex: 4, disabled: processing, "data-test": "login-button", children: [processing && _jsx(Spinner, {}), "Log in"] })] }), canRegister && (_jsxs("div", { className: "text-center text-sm text-muted-foreground", children: ["Don't have an account?", ' ', _jsx(TextLink, { href: register(), tabIndex: 5, children: "Sign up" })] }))] })) }), status && (_jsx("div", { className: "mb-4 text-center text-sm font-medium text-green-600", children: status }))] }));
}
Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
