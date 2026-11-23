/**
 * Long lists in the live map sidebar and drawer render a window of rows that grows on demand,
 * instead of every driver, vehicle and place at once: a few hundred rows with dropdowns cost
 * seconds of rendering on each page load, while only the first screenful is ever seen.
 */
export const SIDEBAR_RENDER_STEP = 40;
export const DRAWER_RENDER_STEP = 25;

/** The first `limit` rows, or the rows themselves when they already fit. */
export function renderWindow(rows, limit) {
    if (!rows) {
        return [];
    }

    const length = rows.length ?? rows.size ?? 0;
    if (length <= limit) {
        return rows;
    }

    return typeof rows.slice === 'function' ? rows.slice(0, limit) : Array.from(rows).slice(0, limit);
}

/** How many rows the window leaves out. */
export function hiddenRowCount(rows, limit) {
    return Math.max(0, (rows?.length ?? rows?.size ?? 0) - limit);
}

/** True when a scrolled list is within `threshold` pixels of its end. */
export function isNearScrollEnd(element, threshold = 160) {
    if (!element) {
        return false;
    }

    return element.scrollTop + element.clientHeight >= element.scrollHeight - threshold;
}
