/**
 * Permintaan JSON ke server Laravel (di luar navigasi Inertia), lengkap
 * dengan token CSRF dari cookie XSRF-TOKEN.
 */
export function readCookie(name) {
    const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export function jsonHeaders() {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
    };
}

export async function getJson(url) {
    const response = await fetch(url, { credentials: 'same-origin', headers: jsonHeaders() });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

export async function postJson(url, body) {
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: jsonHeaders(), body: JSON.stringify(body) });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}
