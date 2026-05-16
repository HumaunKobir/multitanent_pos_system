import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import { jsx as _jsx, Fragment as _Fragment, jsxs as _jsxs } from "react/jsx-runtime";
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputOTP, InputOTPGroup, InputOTPSlot, } from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';
export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState(false);
    const [code, setCode] = useState('');
    const authConfigContent = useMemo(() => {
        if (showRecoveryInput) {
            return {
                title: 'Recovery code',
                description: 'Please confirm access to your account by entering one of your emergency recovery codes.',
                toggleText: 'login using an authentication code',
            };
        }

        return {
            title: 'Authentication code',
            description: 'Enter the authentication code provided by your authenticator application.',
            toggleText: 'login using a recovery code',
        };
    }, [showRecoveryInput]);
    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });
    const toggleRecoveryMode = (clearErrors) => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (_jsxs(_Fragment, { children: [_jsx(Head, { title: "Two-factor authentication" }), _jsx("div", { className: "space-y-6", children: _jsx(Form, { ...store.form(), className: "space-y-4", resetOnError: true, resetOnSuccess: !showRecoveryInput, children: ({ errors, processing, clearErrors }) => (_jsxs(_Fragment, { children: [showRecoveryInput ? (_jsxs(_Fragment, { children: [_jsx(Input, { name: "recovery_code", type: "text", placeholder: "Enter recovery code", autoFocus: showRecoveryInput, required: true }), _jsx(InputError, { message: errors.recovery_code })] })) : (_jsxs("div", { className: "flex flex-col items-center justify-center space-y-3 text-center", children: [_jsx("div", { className: "flex w-full items-center justify-center", children: _jsx(InputOTP, { name: "code", maxLength: OTP_MAX_LENGTH, value: code, onChange: (value) => setCode(value), disabled: processing, pattern: REGEXP_ONLY_DIGITS, autoFocus: true, children: _jsx(InputOTPGroup, { children: Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (_jsx(InputOTPSlot, { index: index }, index))) }) }) }), _jsx(InputError, { message: errors.code })] })), _jsx(Button, { type: "submit", className: "w-full", disabled: processing, children: "Continue" }), _jsxs("div", { className: "text-center text-sm text-muted-foreground", children: [_jsx("span", { children: "or you can " }), _jsx("button", { type: "button", className: "cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500", onClick: () => toggleRecoveryMode(clearErrors), children: authConfigContent.toggleText })] })] })) }) })] }));
}
