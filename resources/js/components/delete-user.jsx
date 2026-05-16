import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger, } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
export default function DeleteUser() {
    const passwordInput = useRef(null);

    return (_jsxs("div", { className: "space-y-6", children: [_jsx(Heading, { variant: "small", title: "Delete account", description: "Delete your account and all of its resources" }), _jsxs("div", { className: "space-y-4 rounded-none border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10", children: [_jsxs("div", { className: "relative space-y-0.5 text-red-600 dark:text-red-100", children: [_jsx("p", { className: "font-medium", children: "Warning" }), _jsx("p", { className: "text-sm", children: "Please proceed with caution, this cannot be undone." })] }), _jsxs(Dialog, { children: [_jsx(DialogTrigger, { asChild: true, children: _jsx(Button, { variant: "destructive", "data-test": "delete-user-button", children: "Delete account" }) }), _jsxs(DialogContent, { children: [_jsx(DialogTitle, { children: "Are you sure you want to delete your account?" }), _jsx(DialogDescription, { children: "Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account." }), _jsx(Form, { ...ProfileController.destroy.form(), options: {
                                            preserveScroll: true,
                                        }, onError: () => passwordInput.current?.focus(), resetOnSuccess: true, className: "space-y-6", children: ({ resetAndClearErrors, processing, errors }) => (_jsxs(_Fragment, { children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password", className: "sr-only", children: "Password" }), _jsx(PasswordInput, { id: "password", name: "password", ref: passwordInput, placeholder: "Password", autoComplete: "current-password" }), _jsx(InputError, { message: errors.password })] }), _jsxs(DialogFooter, { className: "gap-2", children: [_jsx(DialogClose, { asChild: true, children: _jsx(Button, { variant: "secondary", onClick: () => resetAndClearErrors(), children: "Cancel" }) }), _jsx(Button, { variant: "destructive", disabled: processing, asChild: true, children: _jsx("button", { type: "submit", "data-test": "confirm-delete-user-button", children: "Delete account" }) })] })] })) })] })] })] })] }));
}
