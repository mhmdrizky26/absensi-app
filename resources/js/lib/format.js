const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

/**
 * "2026-07-13" → "13 Jul 2026". Tanggal dibaca sebagai tanggal lokal, bukan UTC.
 */
export function formatDate(value) {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    return dateFormatter.format(new Date(year, month - 1, day));
}

export function formatDateTime(value) {
    return value ? dateTimeFormatter.format(new Date(value)) : '—';
}
