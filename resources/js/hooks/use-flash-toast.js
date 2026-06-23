import { useAppToast } from '@/contexts/app-toast-context';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Show session flash messages once per unique payload (avoids toast loops).
 */
export function useFlashToast() {
    const { flash } = usePage().props;
    const { success, error, warning, info } = useAppToast();
    const shownKeyRef = useRef(null);

    useEffect(() => {
        const key = [
            flash?.success ?? '',
            flash?.error ?? '',
            flash?.warning ?? '',
            flash?.info ?? '',
        ].join('\0');

        if (!key.replace(/\0/g, '')) {
            shownKeyRef.current = null;

            return;
        }

        if (shownKeyRef.current === key) {
            return;
        }

        shownKeyRef.current = key;

        if (flash?.success) {
            success(flash.success);
        }

        if (flash?.error) {
            error(flash.error);
        }

        if (flash?.warning) {
            warning(flash.warning);
        }

        if (flash?.info) {
            info(flash.info);
        }
    }, [flash?.success, flash?.error, flash?.warning, flash?.info, success, error, warning, info]);
}
