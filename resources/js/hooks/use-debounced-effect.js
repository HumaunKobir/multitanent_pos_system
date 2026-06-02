import { useEffect, useRef } from 'react';

/**
 * Runs an effect after `delay` ms since the last dependency change.
 * Useful for live search / filters without a submit button.
 */
export function useDebouncedEffect(effect, deps, delay = 350, options = {}) {
    const { skipFirstRun = false } = options;
    const didRunOnce = useRef(false);

    useEffect(() => {
        if (skipFirstRun && !didRunOnce.current) {
            didRunOnce.current = true;
            return;
        }

        const handle = setTimeout(() => {
            effect();
        }, delay);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [...deps, delay]);
}

