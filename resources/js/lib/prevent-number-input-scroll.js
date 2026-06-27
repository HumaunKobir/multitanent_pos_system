/**
 * Prevent mouse wheel from incrementing/decrementing focused number inputs.
 */
export function preventNumberInputScroll() {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener(
        'wheel',
        (event) => {
            const active = document.activeElement;

            if (active instanceof HTMLInputElement && active.type === 'number') {
                event.preventDefault();
            }
        },
        { passive: false },
    );
}
