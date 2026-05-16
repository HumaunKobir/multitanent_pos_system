/** @param {Date} d */
export function startOfMonth(d) {
    return new Date(d.getFullYear(), d.getMonth(), 1);
}

/** @param {Date} d @param {number} n */
export function addMonthsDate(d, n) {
    return new Date(d.getFullYear(), d.getMonth() + n, 1);
}

/** Local calendar date as yyyy-mm-dd (for inputs). */
export function toISODateLocal(d) {
    if (!(d instanceof Date) || Number.isNaN(d.getTime())) {
        return '';
    }

    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/** @param {string | null | undefined} s */
export function parseISODate(s) {
    if (!s || typeof s !== 'string') {
        return null;
    }

    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s.trim());

    if (!m) {
        return null;
    }

    const y = Number(m[1]);
    const mo = Number(m[2]) - 1;
    const day = Number(m[3]);
    const d = new Date(y, mo, day);

    if (d.getFullYear() !== y || d.getMonth() !== mo || d.getDate() !== day) {
        return null;
    }

    return d;
}

/** @param {Date | null} a @param {Date | null} b */
export function isSameDay(a, b) {
    if (!a || !b) {
        return false;
    }

    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

/** @param {Date | null} a @param {Date | null} b */
export function compareDay(a, b) {
    if (!a || !b) {
        return 0;
    }

    const ta = new Date(a.getFullYear(), a.getMonth(), a.getDate()).getTime();
    const tb = new Date(b.getFullYear(), b.getMonth(), b.getDate()).getTime();

    return ta < tb ? -1 : ta > tb ? 1 : 0;
}

/**
 * @param {number} year
 * @param {number} month 0–11
 * @param {0 | 1} weekStartsOn
 * @returns {(Date | null)[]}
 */
export function buildMonthCells(year, month, weekStartsOn) {
    const first = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0).getDate();
    let pad = first.getDay() - weekStartsOn;

    if (pad < 0) {
        pad += 7;
    }

    /** @type {(Date | null)[]} */
    const cells = [];

    for (let i = 0; i < pad; i++) {
        cells.push(null);
    }

    for (let day = 1; day <= lastDay; day++) {
        cells.push(new Date(year, month, day));
    }

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    while (cells.length < 42) {
        cells.push(null);
    }

    return cells;
}

/**
 * Full 6-row grid including leading/trailing days from adjacent months (picker style).
 *
 * @param {number} year
 * @param {number} month 0–11
 * @param {0 | 1} weekStartsOn
 * @returns {{ date: Date; inMonth: boolean }[]}
 */
export function buildMonthMatrix(year, month, weekStartsOn) {
    const first = new Date(year, month, 1);
    const daysThisMonth = new Date(year, month + 1, 0).getDate();
    let pad = first.getDay() - weekStartsOn;

    if (pad < 0) {
        pad += 7;
    }

    const prevMonthLast = new Date(year, month, 0);
    const prevDays = prevMonthLast.getDate();

    /** @type {{ date: Date; inMonth: boolean }[]} */
    const result = [];

    for (let i = 0; i < pad; i++) {
        const d = prevDays - pad + i + 1;

        result.push({ date: new Date(year, month - 1, d), inMonth: false });
    }

    for (let d = 1; d <= daysThisMonth; d++) {
        result.push({ date: new Date(year, month, d), inMonth: true });
    }

    let nextDay = 1;

    while (result.length % 7 !== 0) {
        result.push({ date: new Date(year, month + 1, nextDay++), inMonth: false });
    }

    while (result.length < 42) {
        result.push({ date: new Date(year, month + 1, nextDay++), inMonth: false });
    }

    return result;
}

/** @param {string} iso */
export function formatDisplaySingle(iso) {
    const d = parseISODate(iso);

    if (!d) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(d);
}

/** @param {string} fromIso @param {string} toIso */
export function formatDisplayRange(fromIso, toIso) {
    if (!fromIso || !toIso) {
        return '';
    }

    const a = parseISODate(fromIso);
    const b = parseISODate(toIso);

    if (!a || !b) {
        return '';
    }

    if (a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth()) {
        const monthName = new Intl.DateTimeFormat(undefined, { month: 'long' }).format(a);

        return `${monthName} ${a.getDate()} – ${b.getDate()}, ${a.getFullYear()}`;
    }

    return `${formatDisplaySingle(fromIso)} – ${formatDisplaySingle(toIso)}`;
}

export function isDayInClosedRange(d, fromStr, toStr) {
    if (!d || !fromStr) {
        return false;
    }

    const f = parseISODate(fromStr);

    if (!f) {
        return false;
    }

    if (!toStr) {
        return isSameDay(d, f);
    }

    const t = parseISODate(toStr);

    if (!t) {
        return isSameDay(d, f);
    }

    const lo = compareDay(f, t) <= 0 ? f : t;
    const hi = compareDay(f, t) <= 0 ? t : f;

    return compareDay(d, lo) >= 0 && compareDay(d, hi) <= 0;
}

/** @returns {Date} */
export function todayDate() {
    const n = new Date();

    return new Date(n.getFullYear(), n.getMonth(), n.getDate());
}

