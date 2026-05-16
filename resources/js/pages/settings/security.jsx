import { Form, Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';
export default function Security({ canManageTwoFactor = false, requiresConfirmation = false, twoFactorEnabled = false, }) {
    const passwordInput = useRef(null);
    const currentPasswordInput = useRef(null);
    const { qrCodeSvg, hasSetupData, manualSetupKey, clearSetupData, clearTwoFactorAuthData, fetchSetupData, recoveryCodesList, fetchRecoveryCodes, errors, } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);
    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Security settings" }), _jsx("h1", { className: "sr-only", children: "Security settings" }), _jsxs("div", { className: "space-y-6", children: [_jsx(Heading, { variant: "small", title: "Update password", description: "Ensure your account is using a long, random password to stay secure" }), _jsx(Form, { ...SecurityController.update.form(), options: {
                            preserveScroll: true,
                        }, resetOnError: [
                            'password',
                            'password_confirmation',
                            'current_password',
                        ], resetOnSuccess: true, onError: (errors) => {
                            if (errors.password) {
                                passwordInput.current?.focus();
                            }

                            if (errors.current_password) {
                                currentPasswordInput.current?.focus();
                            }
                        }, className: "space-y-6", children: ({ errors, processing }) => (_jsxs(_Fragment, { children: [_jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "current_password", children: "Current password" }), _jsx(PasswordInput, { id: "current_password", ref: currentPasswordInput, name: "current_password", className: "mt-1 block w-full", autoComplete: "current-password", placeholder: "Current password" }), _jsx(InputError, { message: errors.current_password })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password", children: "New password" }), _jsx(PasswordInput, { id: "password", ref: passwordInput, name: "password", className: "mt-1 block w-full", autoComplete: "new-password", placeholder: "New password" }), _jsx(InputError, { message: errors.password })] }), _jsxs("div", { className: "grid gap-2", children: [_jsx(Label, { htmlFor: "password_confirmation", children: "Confirm password" }), _jsx(PasswordInput, { id: "password_confirmation", name: "password_confirmation", className: "mt-1 block w-full", autoComplete: "new-password", placeholder: "Confirm password" }), _jsx(InputError, { message: errors.password_confirmation })] }), _jsx("div", { className: "flex items-center gap-4", children: _jsx(Button, { disabled: processing, "data-test": "update-password-button", children: "Save password" }) })] })) })] }), canManageTwoFactor && (_jsxs("div", { className: "space-y-6", children: [_jsx(Heading, { variant: "small", title: "Two-factor authentication", description: "Manage your two-factor authentication settings" }), twoFactorEnabled ? (_jsxs("div", { className: "flex flex-col items-start justify-start space-y-4", children: [_jsx("p", { className: "text-sm text-muted-foreground", children: "You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone." }), _jsx("div", { className: "relative inline", children: _jsx(Form, { ...disable.form(), children: ({ processing }) => (_jsx(Button, { variant: "destructive", type: "submit", disabled: processing, children: "Disable 2FA" })) }) }), _jsx(TwoFactorRecoveryCodes, { recoveryCodesList: recoveryCodesList, fetchRecoveryCodes: fetchRecoveryCodes, errors: errors })] })) : (_jsxs("div", { className: "flex flex-col items-start justify-start space-y-4", children: [_jsx("p", { className: "text-sm text-muted-foreground", children: "When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone." }), _jsx("div", { children: hasSetupData ? (_jsxs(Button, { onClick: () => setShowSetupModal(true), children: [_jsx(ShieldCheck, {}), "Continue setup"] })) : (_jsx(Form, { ...enable.form(), onSuccess: () => setShowSetupModal(true), children: ({ processing }) => (_jsx(Button, { type: "submit", disabled: processing, children: "Enable 2FA" })) })) })] })), _jsx(TwoFactorSetupModal, { isOpen: showSetupModal, onClose: () => setShowSetupModal(false), requiresConfirmation: requiresConfirmation, twoFactorEnabled: twoFactorEnabled, qrCodeSvg: qrCodeSvg, manualSetupKey: manualSetupKey, clearSetupData: clearSetupData, fetchSetupData: fetchSetupData, errors: errors })] }))] }));
}
Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
