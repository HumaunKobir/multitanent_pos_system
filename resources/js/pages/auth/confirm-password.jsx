import { Form, Head } from '@inertiajs/react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
export default function ConfirmPassword() {
    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Confirm password" }), _jsx(Form, { ...store.form(), resetOnSuccess: ['password'], children: ({ processing, errors }) => (_jsxs("div", { className: "space-y-6", children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password", children: "Password" }), _jsx(PasswordInput, { id: "password", name: "password", placeholder: "Password", autoComplete: "current-password", autoFocus: true }), _jsx(InputError, { message: errors.password })] }), _jsx("div", { className: "flex items-center", children: _jsxs(Button, { className: "w-full", disabled: processing, "data-test": "confirm-password-button", children: [processing && _jsx(Spinner, {}), "Confirm password"] }) })] })) })] }));
}
ConfirmPassword.layout = {
    title: 'Confirm your password',
    description: 'This is a secure area of the application. Please confirm your password before continuing.',
};
