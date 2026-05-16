import { Form, Head } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';
export default function ResetPassword({ token, email }) {
    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Reset password" }), _jsx(Form, { ...update.form(), transform: (data) => ({ ...data, token, email }), resetOnSuccess: ['password', 'password_confirmation'], children: ({ processing, errors }) => (_jsxs("div", { className: "grid gap-6", children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "email", children: "Email" }), _jsx(Input, { id: "email", type: "email", name: "email", autoComplete: "email", value: email, className: "mt-1 block w-full", readOnly: true }), _jsx(InputError, { message: errors.email, className: "mt-2" })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password", children: "Password" }), _jsx(PasswordInput, { id: "password", name: "password", autoComplete: "new-password", className: "mt-1 block w-full", autoFocus: true, placeholder: "Password" }), _jsx(InputError, { message: errors.password })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password_confirmation", children: "Confirm password" }), _jsx(PasswordInput, { id: "password_confirmation", name: "password_confirmation", autoComplete: "new-password", className: "mt-1 block w-full", placeholder: "Confirm password" }), _jsx(InputError, { message: errors.password_confirmation, className: "mt-2" })] }), _jsxs(Button, { type: "submit", className: "mt-4 w-full", disabled: processing, "data-test": "reset-password-button", children: [processing && _jsx(Spinner, {}), "Reset password"] })] })) })] }));
}
ResetPassword.layout = {
    title: 'Reset password',
    description: 'Please enter your new password below',
};
