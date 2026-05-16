import { Form } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, } from '@/components/ui/dialog';
import { InputOTP, InputOTPGroup, InputOTPSlot, } from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/hooks/use-appearance';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { confirm } from '@/routes/two-factor';
function GridScanIcon() {
    return (_jsx("div", { className: "mb-3 rounded-none border border-border bg-card p-0.5 shadow-sm", children: _jsxs("div", { className: "relative overflow-hidden rounded-none border border-border bg-muted p-2.5", children: [_jsx("div", { className: "absolute inset-0 grid grid-cols-5 opacity-50", children: Array.from({ length: 5 }, (_, i) => (_jsx("div", { className: "border-r border-border last:border-r-0" }, `col-${i + 1}`))) }), _jsx("div", { className: "absolute inset-0 grid grid-rows-5 opacity-50", children: Array.from({ length: 5 }, (_, i) => (_jsx("div", { className: "border-b border-border last:border-b-0" }, `row-${i + 1}`))) }), _jsx(ScanLine, { className: "relative z-20 size-6 text-foreground" })] }) }));
}
function TwoFactorSetupStep({ qrCodeSvg, manualSetupKey, buttonText, onNextStep, errors, }) {
    const { resolvedAppearance } = useAppearance();
    const [copiedText, copy] = useClipboard();
    const IconComponent = copiedText === manualSetupKey ? Check : Copy;

    return (_jsx(_Fragment, { children: errors?.length ? (_jsx(AlertError, { errors: errors })) : (_jsxs(_Fragment, { children: [_jsx("div", { className: "mx-auto flex max-w-md overflow-hidden", children: _jsx("div", { className: "mx-auto aspect-square w-64 rounded-none border border-border", children: _jsx("div", { className: "z-10 flex h-full w-full items-center justify-center p-5", children: qrCodeSvg ? (_jsx("div", { className: "aspect-square w-full rounded-none bg-white p-2 [&_svg]:size-full", dangerouslySetInnerHTML: {
                                    __html: qrCodeSvg,
                                }, style: {
                                    filter: resolvedAppearance === 'dark'
                                        ? 'invert(1) brightness(1.5)'
                                        : undefined,
                                } })) : (_jsx(Spinner, {})) }) }) }), _jsx("div", { className: "flex w-full space-x-5", children: _jsx(Button, { className: "w-full", onClick: onNextStep, children: buttonText }) }), _jsxs("div", { className: "relative flex w-full items-center justify-center", children: [_jsx("div", { className: "absolute inset-0 top-1/2 h-px w-full bg-border" }), _jsx("span", { className: "relative bg-card px-2 py-1", children: "or, enter the code manually" })] }), _jsx("div", { className: "flex w-full space-x-2", children: _jsx("div", { className: "flex w-full items-stretch overflow-hidden rounded-none border border-border", children: !manualSetupKey ? (_jsx("div", { className: "flex h-full w-full items-center justify-center bg-muted p-3", children: _jsx(Spinner, {}) })) : (_jsxs(_Fragment, { children: [_jsx("input", { type: "text", readOnly: true, value: manualSetupKey, className: "h-full w-full bg-background p-3 text-foreground outline-none" }), _jsx("button", { onClick: () => copy(manualSetupKey), className: "border-l border-border px-3 hover:bg-muted", children: _jsx(IconComponent, { className: "w-4" }) })] })) }) })] })) }));
}
function TwoFactorVerificationStep({ onClose, onBack, }) {
    const [code, setCode] = useState('');
    const pinInputContainerRef = useRef(null);
    useEffect(() => {
        setTimeout(() => {
            pinInputContainerRef.current?.querySelector('input')?.focus();
        }, 0);
    }, []);

    return (_jsx(Form, { ...confirm.form(), onSuccess: () => onClose(), resetOnError: true, resetOnSuccess: true, children: ({ processing, errors, }) => (_jsx(_Fragment, { children: _jsxs("div", { ref: pinInputContainerRef, className: "relative w-full space-y-3", children: [_jsxs("div", { className: "flex w-full flex-col items-center space-y-3 py-2", children: [_jsx(InputOTP, { id: "otp", name: "code", maxLength: OTP_MAX_LENGTH, onChange: setCode, disabled: processing, pattern: REGEXP_ONLY_DIGITS, autoFocus: true, children: _jsx(InputOTPGroup, { children: Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (_jsx(InputOTPSlot, { index: index }, index))) }) }), _jsx(InputError, { message: errors?.confirmTwoFactorAuthentication?.code })] }), _jsxs("div", { className: "flex w-full space-x-5", children: [_jsx(Button, { type: "button", variant: "outline", className: "flex-1", onClick: onBack, disabled: processing, children: "Back" }), _jsx(Button, { type: "submit", className: "flex-1", disabled: processing || code.length < OTP_MAX_LENGTH, children: "Confirm" })] })] }) })) }));
}
export default function TwoFactorSetupModal({ isOpen, onClose, requiresConfirmation, twoFactorEnabled, qrCodeSvg, manualSetupKey, clearSetupData, fetchSetupData, errors, }) {
    const [showVerificationStep, setShowVerificationStep] = useState(false);
    const modalConfig = useMemo(() => {
        if (twoFactorEnabled) {
            return {
                title: 'Two-factor authentication enabled',
                description: 'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
                buttonText: 'Close',
            };
        }

        if (showVerificationStep) {
            return {
                title: 'Verify authentication code',
                description: 'Enter the 6-digit code from your authenticator app',
                buttonText: 'Continue',
            };
        }

        return {
            title: 'Enable two-factor authentication',
            description: 'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
            buttonText: 'Continue',
        };
    }, [twoFactorEnabled, showVerificationStep]);
    const resetModalState = useCallback(() => {
        setShowVerificationStep(false);
        clearSetupData();
    }, [clearSetupData]);
    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);
    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);

            return;
        }

        handleClose();
    }, [requiresConfirmation, handleClose]);
    const fetchSetupDataRef = useRef(fetchSetupData);
    useEffect(() => {
        fetchSetupDataRef.current = fetchSetupData;
    }, [fetchSetupData]);
    useEffect(() => {
        if (isOpen && !qrCodeSvg) {
            fetchSetupDataRef.current();
        }
    }, [isOpen, qrCodeSvg]);

    return (_jsx(Dialog, { open: isOpen, onOpenChange: (open) => !open && handleClose(), children: _jsxs(DialogContent, { className: "sm:max-w-md", children: [_jsxs(DialogHeader, { className: "flex items-center justify-center", children: [_jsx(GridScanIcon, {}), _jsx(DialogTitle, { children: modalConfig.title }), _jsx(DialogDescription, { className: "text-center", children: modalConfig.description })] }), _jsx("div", { className: "flex flex-col items-center space-y-5", children: showVerificationStep ? (_jsx(TwoFactorVerificationStep, { onClose: handleClose, onBack: () => setShowVerificationStep(false) })) : (_jsx(TwoFactorSetupStep, { qrCodeSvg: qrCodeSvg, manualSetupKey: manualSetupKey, buttonText: modalConfig.buttonText, onNextStep: handleModalNextStep, errors: errors })) })] }) }));
}
