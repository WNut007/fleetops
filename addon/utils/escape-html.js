/**
 * Text from records (names, addresses, tracking numbers) that goes into an HTML string, such as
 * the calendar's label and event content, escaped so it stays text.
 */
export default function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
