import { createContext, useCallback, useContext, useMemo, useState } from 'react';

const ToastContext = createContext(null);

let idSeq = 0;

/**
 * @typedef {{ id: number; type: 'success' | 'info' | 'warning' | 'error'; message: string }} AppToastEntry
 */

export function AppToastProvider({ children }) {
    const [toasts, setToasts] = useState(/** @type {AppToastEntry[]} */ ([]));

    const dismiss = useCallback((id) => {
        setToasts((t) => t.filter((x) => x.id !== id));
    }, []);

    const push = useCallback(
        ({ type = 'info', message, duration = 4500 }) => {
            const id = ++idSeq;
            const entry = { id, type, message };

            setToasts((t) => [...t, entry]);

            if (duration > 0) {
                window.setTimeout(() => {
                    dismiss(id);
                }, duration);
            }

            return id;
        },
        [dismiss],
    );

    const value = useMemo(
        () => ({
            push,
            dismiss,
            toasts,
            success: (message, options) => push({ type: 'success', message, ...options }),
            info: (message, options) => push({ type: 'info', message, ...options }),
            warning: (message, options) => push({ type: 'warning', message, ...options }),
            error: (message, options) => push({ type: 'error', message, ...options }),
        }),
        [push, dismiss, toasts],
    );

    return <ToastContext.Provider value={value}>{children}</ToastContext.Provider>;
}

export function useAppToast() {
    const ctx = useContext(ToastContext);

    if (!ctx) {
        throw new Error('useAppToast must be used within AppToastProvider');
    }

    return ctx;
}
