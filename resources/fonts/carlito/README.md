# Carlito untuk PDF BA Bongkar

Font ini disertakan agar PDF dapat dirender konsisten tanpa bergantung pada font sistem server.

- Sumber: https://github.com/googlefonts/carlito
- Commit: `3a810cab78ebd6e2e4eed42af9e8453c4f9b850a`
- Berkas asli: `fonts/ttf/Carlito-Regular.ttf`, `Carlito-Bold.ttf`, `Carlito-Italic.ttf`.
- Lisensi: SIL Open Font License 1.1, lihat `OFL.txt`. Font tidak dimodifikasi.

Dompdf membaca font dari folder ini dan menulis cache ke disk local privat pada `ba-bongkar/fonts`, bukan ke direktori vendor. Akses remote PDF tetap dinonaktifkan.
