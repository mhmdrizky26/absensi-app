/**
 * Siapkan tabel supaya di layar sempit setiap baris tampil sebagai kartu:
 * kolom utama (nama/siswa/kelas) jadi judul kartu, kolom lain berpasangan
 * dua-dua dengan label kecil di atasnya, dan tombol aksi di baris paling bawah.
 * Tabel dengan kelas .no-stack (misalnya grid rekap) dilewati.
 */
const PRIMARY_HEADERS = /^(siswa|kelas)$/i;
const LONG_TEXT = 48;

/** Teks judul kolom tanpa keterangan kecil (.cell-sub) di dalamnya. */
function headerLabel(th) {
    const copy = th.cloneNode(true);
    copy.querySelectorAll('.cell-sub').forEach((element) => element.remove());

    return copy.textContent.replace(/\s+/g, ' ').trim();
}

function setFlag(cell, name, on) {
    if (on && !cell.hasAttribute(name)) {
        cell.setAttribute(name, '');
    } else if (!on && cell.hasAttribute(name)) {
        cell.removeAttribute(name);
    }
}

export function labelStackedTables(root) {
    if (!root) {
        return;
    }

    for (const table of root.querySelectorAll('table.table:not(.no-stack)')) {
        const headers = [];

        for (const th of table.querySelectorAll('thead tr:first-child > th')) {
            const span = Number(th.getAttribute('colspan') ?? 1);

            for (let i = 0; i < span; i++) {
                headers.push(headerLabel(th));
            }
        }

        // Judul kartu: kolom Nama, kalau tidak ada kolom Kelas/Siswa paling kiri, kalau tidak ada kolom pertama yang berjudul.
        const candidates = [
            headers.findIndex((label) => /^nama$/i.test(label)),
            headers.findIndex((label) => PRIMARY_HEADERS.test(label)),
            headers.findIndex((label) => label !== '' && label.toLowerCase() !== 'no'),
        ];
        const primary = candidates.find((index) => index !== -1) ?? -1;

        for (const row of table.querySelectorAll('tbody tr, tfoot tr')) {
            let column = 0;

            for (const cell of row.children) {
                const label = headers[column] ?? '';

                if (cell.getAttribute('data-label') !== label) {
                    cell.setAttribute('data-label', label);
                }

                const span = Number(cell.getAttribute('colspan') ?? 1);
                const isPrimary = row.children.length > 1 && primary >= column && primary < column + span;
                const isSelect = label === '' && cell.querySelector('input[type=checkbox]') !== null;
                const isActions = label === '' && !isSelect && cell.querySelector('button, a') !== null;

                setFlag(cell, 'data-primary', isPrimary || span > 1);
                setFlag(cell, 'data-select', isSelect);
                setFlag(cell, 'data-actions', isActions);
                setFlag(cell, 'data-hide', span === 1 && label.toLowerCase() === 'no');
                setFlag(cell, 'data-wide', !isPrimary && (cell.querySelector('.bar-cell') !== null || cell.textContent.trim().length > LONG_TEXT));

                column += span;
            }
        }

        table.classList.add('is-stackable');
    }
}
