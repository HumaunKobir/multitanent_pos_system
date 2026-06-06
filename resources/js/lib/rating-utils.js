export function formatRating(value) {
    const rounded = Math.round(Number(value) * 10) / 10;

    return rounded % 1 === 0 ? String(Math.round(rounded)) : rounded.toFixed(1);
}
