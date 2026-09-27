/**
 * Beri setiap sel tabel data-label berisi judul kolomnya, supaya CSS bisa
 * menampilkan baris tabel sebagai kartu bertumpuk di layar sempit.
 * Tabel dengan kelas .no-stack (misalnya grid rekap) dilewati.
 */
export function labelStackedTables(root) {
    if (!root) {
        return;
    }

    for (const table of root.querySelectorAll('table.table:not(.no-stack)')) {
        const headers = [];

        for (const th of table.querySelectorAll('thead tr:first-child > th')) {
            const span = Number(th.getAttribute('colspan') ?? 1);

            for (let i = 0; i < span; i++) {
                headers.push(th.textContent.trim());
            }
        }

        for (const row of table.querySelectorAll('tbody tr, tfoot tr')) {
            let column = 0;

            for (const cell of row.children) {
                const label = headers[column] ?? '';

                if (cell.getAttribute('data-label') !== label) {
                    cell.setAttribute('data-label', label);
                }

                column += Number(cell.getAttribute('colspan') ?? 1);
            }
        }

        table.classList.add('is-stackable');
    }
}
