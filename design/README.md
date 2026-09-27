# Desain (referensi)

Folder ini berisi desain awal aplikasi absensi. Isinya hanya referensi dan **tidak ikut dijalankan** oleh aplikasi Laravel.

| File | Isi |
|---|---|
| `Attendance.dc.html` | Prototipe 6 layar: login kelas, login staf, scan QR, dashboard hari ini, siswa & kartu QR, rekap absensi. Buka langsung di browser. |
| `support.js` | Runtime pendukung prototipe (wajib ada di samping file HTML). |
| `.thumbnail` | Gambar pratinjau desain (WebP). |
| `_ds/modernist-…/` | Design system "Modernist": `styles.css` (token warna, font, spasi, dan komponen) beserta panduannya di `readme.md`. |

## Penyesuaian yang sudah disepakati

Desain aslinya dibuat untuk SD di Inggris. Saat diimplementasikan, beberapa hal diubah:

- Konteks diganti menjadi SMP Indonesia: kelas VII–IX, NIS/NISN, dan seluruh teks berbahasa Indonesia.
- Status absensi menjadi **Hadir, Terlambat, Sakit, Izin, Alpa**.
- Scan dilakukan oleh guru jam pertama di HP-nya sendiri, setelah login memakai **kode kelas** buatan admin. Karena itu layar scan dibuat *mobile-first*.
- Navigasi dipisah per role: **Admin**, **Guru Piket**, **Wali Kelas**.
- Warna aksen biru (`#2360e8`) menimpa aksen merah bawaan design system.
