# Petunjuk Kerja Codex — Dompis QE

Instruksi ini berlaku untuk seluruh repository.

## 1. Orientasi wajib

Sebelum mengubah code:

1. Baca `HANDOVER_DOMPIS_QE.md` secara lengkap.
2. Baca `codex-skills/dompis-qe/SKILL.md` untuk mode kerja yang sesuai.
3. Baca `CLAUDE.md` dan `.claude/skills/dompis-qe-development/SKILL.md` sebagai histori requirement. Bila berbeda dengan code atau handover terbaru, audit code dan minta klarifikasi hanya jika perbedaannya mengubah hasil.
4. Periksa `git status --short`, branch, commit terbaru, dan diff file yang sudah berubah.
5. Pertahankan perubahan user. Jangan reset, checkout, clean, menghapus, atau menimpa perubahan yang tidak terkait.

Gunakan Bahasa Indonesia untuk komunikasi, kecuali user meminta bahasa lain.

Permintaan eksplisit seperti “perbaiki”, “implementasikan”, atau “lanjutkan” sudah merupakan izin mengubah code dalam scope task. Tidak perlu meminta approval kedua setelah analisis, tetapi jelaskan dampak database sebelum menjalankan operasi database.

## 2. Invariant domain

- Status LOP canonical berada di `qe_lops.status_lop` dan transisinya di `App\Enums\LopStatus`.
- Semua transisi status harus melalui `LopService` agar `qe_lop_histories` tetap tercatat.
- Assignment teknisi berada di `qe_lop_assignments`; jangan menyimpan teknisi aktif langsung di `qe_lops`.
- Evidence tetap generik di `qe_evidences` dengan `step`, `category`, `type`, dan `designator_id` opsional.
- BEFORE, PROGRESS, dan AFTER hanya boleh menunjuk designator dalam reservasi LOP.
- Evidence rejected tidak memenuhi checklist sampai diganti dan direview ulang.
- LOP hanya `completed` setelah semua evidence approved.
- `status_project` hanya untuk Preventive/Relok: `usulan` sebelum assignment dan `on_going` setelah assignment. Recovery memakai `null`.
- Scope ADMIN wajib memakai `LopVisibilityService`: Area, Region, Branch, atau multi Service Area.
- Pertahankan fallback legacy `qe_lops.branch` dan `qe_lops.sto` selama data foreign key belum seluruhnya dibackfill.
- BOQ adalah snapshot. Jangan menghitung ulang histori BOQ memakai harga master terbaru.
- Revenue realisasi hanya menghitung LOP `completed`; Recovery tidak memiliki plan.
- Paket regional: JATIM/JATENG DIY -> Paket-5, BALNUS -> Paket-10.
- File evidence asli mempertahankan nama/MIME/ekstensi; thumbnail boleh WebP.

## 3. Pola implementasi

- Route memakai middleware yang sesuai; authorization backend tetap wajib melalui Form Request, Policy, atau Gate.
- Controller mengorkestrasi request. Aturan lintas halaman ditempatkan di service.
- Gunakan transaction untuk perubahan beberapa tabel dan cleanup file bila operasi gagal.
- Hindari query database dalam loop Blade. Gunakan eager load, aggregate query, atau service.
- Jangan menggandakan logika progress, revenue, scope, atau paket regional di view.
- Untuk upload, validasi ukuran, MIME/extension, ownership LOP, status workflow, dan designator.
- Untuk queue import, pertahankan progress batch/row dan pastikan exception mengubah status menjadi failed.
- Tambahkan regression test untuk bug sebelum atau bersamaan dengan perbaikan.
- Gunakan `apply_patch` untuk edit manual dan formatter hanya untuk perubahan mekanis.

## 4. Database dan migration

- Jangan menjalankan `migrate`, seeder, rollback, `migrate:fresh`, `db:wipe`, atau write query tanpa izin eksplisit user.
- Audit read-only dimulai dengan `php artisan migrate:status` dan `php artisan db:table <table>`.
- Jangan menganggap database lokal sama dengan produksi.
- Sebelum migration produksi: pastikan file sudah di-commit, backup/tag tersedia, target branch benar, dan rollback realistis.
- Migration `2026_10_02_000001_expand_boq_item_name_columns.php` penting untuk BOQ dengan uraian lebih dari 255 karakter.
- Jangan menampilkan `.env`, password, token, DSN, atau credential di output.

## 5. UI dan UX

- Gunakan Blade + Tailwind + Alpine dan komponen reusable yang sudah ada.
- Pertahankan tampilan clean, corporate, responsive, tanpa gradient berlebihan.
- Sidebar light berwarna putih/natural; dark mode harus mempunyai kontras yang terbaca.
- Mobile teknisi adalah target utama untuk workflow lapangan.
- Tombol ikon wajib memiliki tooltip, `title`, atau `aria-label`.
- Form harus memiliki border/focus state yang jelas, label, error, loading, empty, dan success state.
- Untuk daftar evidence/material besar, gunakan card ringkas, grid thumbnail, accordion per group/item, bukan accordion per foto.
- Jangan melakukan redesign luas bila task hanya menyentuh logika.

## 6. Verifikasi minimum

Pilih test sesuai risiko:

- LOP/status/assignment: `LopWorkflowTest`, `LopPermissionTest`.
- Admin scope: `AdminScopeAndProjectStatusTest`.
- Teknisi mobile: `TechnicianMobileWorkflowTest`.
- Evidence: `EvidenceWorkflowTest`, `EvidenceAsyncUploadTest`, `EvidencePermissionTest`.
- Import/BOQ: `BulkImportAndBoqTest`, `LopExcelImportTest`.
- Dashboard: `AdminDashboardTest`.
- Revenue: `RevenueDashboardTest`.
- User: `UserManagementWorkflowTest`, `UserPermissionTest`.

Perintah umum:

```bash
vendor/bin/pint --test
php artisan test --filter=TestTerkait
php artisan view:cache
npm run build
git diff --check
```

Gunakan full suite untuk perubahan lintas modul. Test memakai SQLite dan queue sync; lakukan verifikasi environment terpisah untuk MySQL, worker, Nginx, dan storage.

## 7. Selesai kerja

- Periksa diff hanya berisi perubahan yang diminta dan perubahan user tetap utuh.
- Laporkan perilaku yang berubah, file utama, test, dampak database, dan risiko tersisa.
- Jangan mengklaim migration/deployment/queue produksi berhasil tanpa bukti environment tersebut.
- Perbarui `HANDOVER_DOMPIS_QE.md` bila task mengubah arsitektur, schema, lifecycle, scope role, import, evidence, revenue, deployment, atau status masalah prioritas.
