import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { jsx as _jsx, jsxs as _jsxs, Fragment as _Fragment } from "react/jsx-runtime";
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, } from '@/components/ui/card';
import { regenerateRecoveryCodes } from '@/routes/two-factor';
export default function TwoFactorRecoveryCodes({ recoveryCodesList, fetchRecoveryCodes, errors, }) {
    const [codesAreVisible, setCodesAreVisible] = useState(false);
    const codesSectionRef = useRef(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;
    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);
    useEffect(() => {
        if (!recoveryCodesList.length) {
            fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);
    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (_jsxs(Card, { children: [_jsxs(CardHeader, { children: [_jsxs(CardTitle, { className: "flex gap-3", children: [_jsx(LockKeyhole, { className: "size-4", "aria-hidden": "true" }), "2FA recovery codes"] }), _jsx(CardDescription, { children: "Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager." })] }), _jsxs(CardContent, { children: [_jsxs("div", { className: "flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between", children: [_jsxs(Button, { onClick: toggleCodesVisibility, className: "w-fit", "aria-expanded": codesAreVisible, "aria-controls": "recovery-codes-section", children: [_jsx(RecoveryCodeIconComponent, { className: "size-4", "aria-hidden": "true" }), codesAreVisible ? 'Hide' : 'View', " recovery codes"] }), canRegenerateCodes && (_jsx(Form, { ...regenerateRecoveryCodes.form(), options: { preserveScroll: true }, onSuccess: fetchRecoveryCodes, children: ({ processing }) => (_jsxs(Button, { variant: "secondary", type: "submit", disabled: processing, "aria-describedby": "regenerate-warning", children: [_jsx(RefreshCw, {}), " Regenerate codes"] })) }))] }), _jsx("div", { id: "recovery-codes-section", className: `relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`, "aria-hidden": !codesAreVisible, children: _jsx("div", { className: "mt-3 space-y-3", children: errors?.length ? (_jsx(AlertError, { errors: errors })) : (_jsxs(_Fragment, { children: [_jsx("div", { ref: codesSectionRef, className: "grid gap-1 rounded-none bg-muted p-4 font-mono text-sm", role: "list", "aria-label": "Recovery codes", children: recoveryCodesList.length ? (recoveryCodesList.map((code, index) => (_jsx("div", { role: "listitem", className: "select-text", children: code }, index)))) : (_jsx("div", { className: "space-y-2", "aria-label": "Loading recovery codes", children: Array.from({ length: 8 }, (_, index) => (_jsx("div", { className: "h-4 animate-pulse rounded bg-muted-foreground/20", "aria-hidden": "true" }, index))) })) }), _jsx("div", { className: "text-xs text-muted-foreground select-none", children: _jsxs("p", { id: "regenerate-warning", children: ["Each recovery code can be used once to access your account and will be removed after use. If you need more, click", ' ', _jsx("span", { className: "font-bold", children: "Regenerate codes" }), ' ', "above."] }) })] })) }) })] })] }));
}
