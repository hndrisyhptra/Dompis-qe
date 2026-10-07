# Paket Handover Dompis QE

Terakhir diaudit: **8 Oktober 2026**
Repository: `hndrisyhptra/dompis-qe`
Branch aktif: `collab/import-LOP-import-BOQ`
HEAD dasar saat audit terbaru: `e17e6d3` (`feat: upload surat permintaan`)

Dokumen ini adalah titik awal untuk melanjutkan Dompis QE dari akun Codex lain. Kondisi Git, database, dan server tetap harus diperiksa ulang pada awal setiap sesi karena dapat berubah setelah tanggal audit.

## 1. Ringkasan Eksekutif

Dompis QE adalah aplikasi operasional Quality Enhancement berbasis Laravel untuk mengelola siklus hidup LOP dari input, penugasan teknisi, reservasi material, evidence lapangan, approval, hingga dashboard dan laporan nilai pekerjaan.

Alur utama saat ini:

```text
Input/import LOP
    -> BOQ snapshot opsional
    -> assign teknisi
    -> pickup
    -> reservasi material
    -> evidence material tiba dan pra
    -> evidence progress
    -> evidence after + rekap aktual
    -> waiting approval
    -> review evidence
    -> completed atau rejected
```

Stack utama:

- PHP `^8.2`, Laravel `^12.0`.
- MySQL untuk runtime lokal/produksi; SQLite in-memory untuk test.
- Blade, Tailwind CSS v4, Alpine.js, Vite.
- `maatwebsite/excel` 4 untuk file spreadsheet.
- Queue database untuk import produksi; queue `sync` saat test.
- `browser-image-compression` untuk kompresi evidence di browser.

## 2. Kondisi Repository Saat Handover

### 2.1 Branch dan sinkronisasi remote

- Branch lokal dan remote kerja: `collab/import-LOP-import-BOQ`.
- HEAD lokal saat implementasi terbaru berada di commit `e17e6d3`; periksa ulang posisi remote sebelum pull/push.
- Jangan berpindah ke `main` untuk melanjutkan pekerjaan sebelum memastikan commit branch tersebut sudah di-merge atau memang sengaja ditinggalkan.

### 2.2 Perubahan lokal yang belum di-commit

Saat audit terbaru terdapat perubahan kerja belum di-commit untuk memisahkan BOQ Plan, BOQ Actual, dan Sisa Material per LOP serta memasukkan pasangan jasa otomatis untuk reservasi QE Recovery. Tidak ada perubahan schema pada pekerjaan ini.

### 2.3 Status migration lokal

Migration redundan untuk tabel `qe_boq_plans`, relasi `boq_plan_id`, dan kolom `file_hash` sudah dikeluarkan dari perubahan kerja. Runtime kembali memakai schema existing tanpa migration baru. Migration `2026_10_02_000001_expand_boq_item_name_columns.php` tetap penting untuk uraian BOQ panjang.

Status ini hanya berlaku untuk database lokal `dompis_qe`. Jangan menganggap server produksi sama; periksa `migrate:status` di server setelah pull.

## 3. Arsitektur Aplikasi

### 3.1 Alur request

```text
Route + middleware
    -> Form Request / Policy / Gate
    -> Controller sebagai orkestrator
    -> Service untuk aturan bisnis
    -> Eloquent Model / query aggregate
    -> Database, storage, queue, notification
    -> Blade + Alpine.js
```

Aturan bisnis lintas halaman seharusnya berada di service, bukan diduplikasi di controller atau Blade.

### 3.2 Folder utama

| Lokasi | Fungsi |
| --- | --- |
| `app/Enums` | Status, role, program, scope, evidence, segment, budget |
| `app/Http/Controllers` | Endpoint web dan orkestrasi request |
| `app/Http/Requests` | Validasi dan sebagian authorization input |
| `app/Jobs` | Job antrean import LOP dan BOQ |
| `app/Models` | Entity Eloquent dan relasi |
| `app/Policies` | Authorization LOP, evidence, user |
| `app/Services` | Workflow dan aturan bisnis utama |
| `app/Support` | Helper scope/lokasi dan aturan datek |
| `database/migrations` | Schema dan perubahan data terkontrol |
| `database/seeders` | Area, region, branch, service area, tipe/kategori designator |
| `resources/views` | Blade admin, approval, import, laporan, dan mobile teknisi |
| `resources/js/app.js` | Alpine, antrean upload evidence, progress/retry, kompresi |
| `resources/css/app.css` | Tailwind v4, token brand/ink, dark mode, print report |
| `routes/web.php` | Seluruh route operasional |
| `tests/Feature` | Regression test workflow, permission, import, dashboard |

### 3.3 Entity inti

- `qe_lops`: sumber utama identitas dan status pekerjaan.
- `qe_lop_assignments`: histori assignment; teknisi aktif tidak disimpan langsung pada `qe_lops`.
- `qe_lop_histories`: audit transisi status dan kejadian LOP.
- `qe_material_reservations` dan `qe_material_reservation_items`: material rencana teknisi serta `qty_actual`.
- `qe_surveys`: lokasi survey per LOP.
- `qe_evidences`: semua evidence generik, status review, metadata, dan path file.
- `qe_boqs`, `qe_boq_items`, `qe_boq_histories`: BOQ plan/snapshot nilai.
- `qe_import_batches`, `qe_import_rows`: antrean, progress, dan hasil import.
- `designators`, `designator_types`, `designator_categories`: master item.
- `packages`, `designator_package_prices`: paket KHS dan harga per designator.
- `areas`, `regions`, `branches`, `service_areas`: hirarki lokasi.
- `users`, `roles`, `permissions`, `role_permissions`, `user_service_areas`: identitas, RBAC, dan scope admin.

## 4. Fitur yang Sudah Tersedia

### 4.1 Authentication, RBAC, dan user management

- Login memakai NIK/username dan password Laravel Hash.
- Akun inactive ditolak; login throttling, session regeneration, dan logout tersedia.
- Role aktif: `SUPER_ADMIN`, `ADMIN`, `TEKNISI`, `MANAGER`, `APPROVER`.
- Permission granular tersimpan di database.
- User management mendukung tambah, edit, detail, aktif/nonaktif, soft delete, restore, dan histori.
- Admin dapat memiliki scope `area`, `region`, `branch`, atau satu/banyak `service_area`.

Catatan: redirect khusus `MANAGER` dan `APPROVER` belum dibuat; keduanya masih menuju `lop.index` melalui TODO di `app/Enums/UserRole.php`.

### 4.2 Scope lokasi admin

`LopVisibilityService` adalah sumber utama pembatasan data ADMIN:

- Admin Area: semua branch dan service area dalam area.
- Admin Region: semua branch dalam region.
- Admin Branch: semua service area dalam branch.
- Admin Service Area: satu atau beberapa service area dari pivot `user_service_areas`.
- Data lama tetap didukung melalui fallback nama `branch` dan `sto` bila foreign key belum terisi.
- SUPER_ADMIN tidak dibatasi oleh service ini.

Dashboard, program, master LOP/BOQ, revenue, dan approval harus memakai scope yang sama. Jangan membuat filter scope baru di Blade.

### 4.3 Input dan pengelolaan LOP

- Input manual Incident, STO/service area, branch, area, segmen, program/WBS, budget type, deskripsi, IHLD, dan nama LOP.
- Lookup ticket dan parsing datek.
- Incident manual terformat otomatis.
- Nama LOP dapat digenerate dari template dan formatnya dapat diatur.
- Segment mendukung multi-select JSON.
- Program: QE Recovery, QE Preventive, QE Relok Utilitas.
- `status_project`: Preventive/Relok memakai `usulan` lalu `on_going` saat assignment; Recovery menggunakan `null`.
- Data LOP mendukung detail, edit, delete terkonfirmasi, tracking, assignment/reassignment, dan laporan.

### 4.4 Assignment dan workflow teknisi

Lifecycle canonical:

```text
draft -> assigned -> picked_up -> survey -> progress
      -> waiting_approval -> completed
      -> rejected -> progress / waiting_approval
```

Workflow mobile teknisi aktual terdiri dari lima langkah:

1. Reservasi material.
2. Evidence material tiba.
3. Evidence pra: Surat Permintaan opsional untuk Preventive/Relok, lokasi, foto kondisi awal, dan capture ticket Insera.
4. Evidence progress per designator reservasi.
5. Evidence after per designator, evidence slot/port, dan `qty_actual`.

LOP baru dapat diajukan bila semua checklist wajib lengkap. Rejected evidence dapat diganti pada record yang sama, bukan membuat evidence baru.

### 4.5 BOQ dan reservasi material

- BOQ satu per LOP di `qe_boqs`, memiliki paket dan item snapshot di `qe_boq_items`.
- BOQ dapat dibuat lewat import atau dikelola dari Master Data.
- Item BOQ dapat dicari, ditambah, diedit, dan dihapus selama aturan status mengizinkan.
- Saat assign, item material BOQ mengisi `qe_material_reservation_items.qty` sebagai kuantitas rencana.
- Teknisi mengisi `qe_material_reservation_items.qty_actual` sebagai kuantitas realisasi; tidak ada tabel BOQ Plan terpisah.
- Jika BOQ tidak ada, reservasi teknisi menjadi fallback Data BOQ dan perhitungan nilai sesuai aturan service.
- Detail LOP dan Detail Data BOQ memisahkan tiga proyeksi: BOQ Plan dari snapshot import, BOQ Actual dari `qty_actual`, dan Sisa Material dari selisih reservasi dengan aktual.
- QE Recovery tidak memiliki BOQ Plan. Teknisi hanya memilih item `MATERIAL`; pasangan `JASA` dicari otomatis memakai pola kode `M-...` -> `J-...` untuk perhitungan dan detail lengkap.
- Pasangan jasa virtual tidak disimpan ke `qe_material_reservation_items`, sehingga UI reservasi dan evidence tetap hanya per item material.
- Qty aplikasi diperlakukan sebagai bilangan bulat meskipun beberapa kolom database masih decimal untuk kompatibilitas.
- Harga disimpan pada snapshot BOQ; jangan menghitung ulang histori BOQ dari harga master terbaru.

Pemilihan paket reservasi otomatis:

- Region JATIM dan JATENG DIY -> `Paket-5`.
- Region BALNUS -> `Paket-10`.
- BOQ yang sudah memiliki paket tetap lebih diprioritaskan untuk workflow teknisi.
- Implementasi ada di `RegionalPackageResolver` dan `TechnicianWorkflowService::reservationPackage()`.

### 4.6 Bulk Import LOP dan Import BOQ

- Upload berjalan melalui queue job database.
- UI menampilkan upload progress, progress job, hasil sukses/gagal, dan lima histori terakhir.
- Bulk LOP menerima CSV/XLSX dan menggenerate nama LOP bila kosong.
- BOQ membaca nama project/LOP, header Paket-5/Paket-10, harga material/jasa, serta volume.
- Hanya item dengan volume lebih dari nol yang disimpan/ditampilkan.
- Import BOQ dapat membuat master designator yang belum tersedia jika data wajib lengkap.
- Form Import BOQ hanya memiliki satu pilihan upload. Semua program disimpan ke `qe_boqs/qe_boq_items` sebagai snapshot rencana.
- Unggah ulang membuat batch baru lalu memperbarui BOQ terkait; alur tidak memakai tabel `qe_boq_plans`, deduplikasi `file_hash`, radio target, atau checkbox replace.
- Job `ProcessBulkLopImport` dan `ProcessBoqImport` mempunyai timeout 600 detik dan satu kali attempt.
- Worker produksi harus berjalan; setelah deploy gunakan `php artisan queue:restart`.

### 4.7 Evidence dan approval

- Satu tabel `qe_evidences` untuk seluruh kategori evidence.
- Upload teknisi bersifat async per file, maksimal dua upload paralel, memiliki progress dan retry.
- File asli dikompresi tanpa mengganti nama, MIME, atau ekstensi; thumbnail terpisah boleh WebP.
- File ditampilkan melalui route terautentikasi, bukan mengandalkan URL storage publik langsung.
- Preview dan hapus sebelum upload tersedia.
- QE Preventive/Relok mempunyai dokumen pendukung multi-file `request_letter` (PDF/foto). Admin mengelolanya dari aksi LOP; teknisi melihatnya pada Step 3 dan dapat upload sendiri hanya bila admin belum menyediakan file.
- Surat Permintaan tetap disimpan di `qe_evidences`, tetapi dikecualikan dari progres dan antrean approval karena bukan evidence hasil pekerjaan.
- Galeri menampilkan pending, approved, rejected, serta alasan reject.
- Approval dikelompokkan per LOP, step, evidence group, dan item designator.
- Reviewer dapat approve, reject, reset, dan bulk approve per accordion/group.
- LOP selesai hanya bila semua evidence workflow yang dapat direview sudah approved.
- Admin Area/Region/Branch dapat mereview seluruh evidence dalam scope lokasi. Admin Service Area tetap dibatasi scope service area, bukan hanya pembuat LOP.

### 4.8 Dashboard, program, dan Revenue Overview

- Dashboard ADMIN/SUPER_ADMIN memiliki KPI, pipeline, matrix program/WBS, filter, dan drill-down list LOP.
- Matrix SUPER_ADMIN dapat dibuka per region/branch; ADMIN mengikuti scope lokasi.
- Program Preventive dan Relok mempunyai tab Usulan/On Going; Recovery tidak memakai `status_project`.
- Revenue Overview menyediakan filter region/branch/service area/program/status, KPI plan/realisasi/gap/rasio, chart per program/branch, matrix branch per program, dan trend segmen.
- Plan hanya dihitung untuk Preventive dan Relok.
- Realisasi hanya dihitung untuk LOP `completed`.
- QE Recovery tidak mempunyai plan; hanya menambah realisasi.
- Nilai plan mengambil BOQ snapshot; data penggunaan material memakai reservasi `qty` dan `qty_actual`, dengan fallback legacy yang dijaga oleh service nilai.

### 4.9 Laporan dan master data

- BOQ Actual dan Sisa Material: halaman, filter, per LOP/rekap, CSV/XLSX.
- Master Area, Region, Branch, Service Area.
- Master Designator, Type, Category, Paket KHS, dan harga paket.
- Ticket Segment Mapping.
- Dark/light mode dan sidebar responsive.

## 5. File Utama per Domain

### 5.1 LOP, assignment, status, scope

- `routes/web.php`
- `app/Models/QeLop.php`
- `app/Enums/LopStatus.php`
- `app/Services/LopService.php`
- `app/Services/LopVisibilityService.php`
- `app/Http/Controllers/LopController.php`
- `app/Http/Controllers/LopAssignmentController.php`
- `app/Policies/QeLopPolicy.php`
- `resources/views/lop/index.blade.php`
- `resources/views/program/show.blade.php`

### 5.2 Teknisi dan progress

- `app/Services/TechnicianWorkflowService.php`
- `app/Services/TechnicianDashboardService.php`
- `app/Services/ProjectProgressService.php`
- `app/Http/Controllers/TechnicianController.php`
- `app/Http/Controllers/TechnicianWorkflowController.php`
- `resources/views/layouts/technician.blade.php`
- `resources/views/technician/project.blade.php`
- `resources/views/components/technician-evidence-uploader.blade.php`
- `resources/js/app.js`

### 5.3 Evidence dan approval

- `app/Models/QeEvidence.php`
- `app/Services/EvidenceService.php`
- `app/Services/EvidenceApprovalService.php`
- `app/Http/Controllers/EvidenceController.php`
- `app/Http/Controllers/EvidenceApprovalController.php`
- `app/Policies/EvidencePolicy.php`
- `resources/views/evidence-approval/index.blade.php`
- `resources/views/evidence-approval/lop-review.blade.php`
- `resources/views/components/approval-evidence-group.blade.php`

### 5.4 BOQ, import, dan queue

- `app/Services/BoqImportService.php`
- `app/Services/BulkLopImportService.php`
- `app/Services/BoqService.php`
- `app/Services/SpreadsheetReader.php`
- `app/Services/ImportBatchService.php`
- `app/Services/ImportRowWriter.php`
- `app/Jobs/ProcessBoqImport.php`
- `app/Jobs/ProcessBulkLopImport.php`
- `app/Http/Controllers/BoqImportController.php`
- `app/Http/Controllers/BulkLopImportController.php`
- `resources/views/imports`
- `resources/views/data-boqs/index.blade.php`

### 5.5 Dashboard dan nilai

- `app/Services/AdminDashboardService.php`
- `app/Services/RevenueService.php`
- `app/Services/LopBoqValueService.php`
- `app/Services/RegionalPackageResolver.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/RevenueController.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/revenue/index.blade.php`

### 5.6 User dan scope

- `app/Models/User.php`
- `app/Enums/AdminScopeType.php`
- `app/Services/UserService.php`
- `app/Http/Controllers/UserController.php`
- `app/Policies/UserPolicy.php`
- `resources/views/users/_access-scope.blade.php`
- `resources/views/users/_edit-modal.blade.php`

## 6. Invariant Bisnis yang Tidak Boleh Dilanggar

1. Assignment aktif berada pada `qe_lop_assignments`; jangan menambah `technician_id` ke `qe_lops`.
2. Semua transisi status melewati `LopService` dan dicatat di `qe_lop_histories`.
3. Jangan mengubah lifecycle tanpa memperbarui `LopStatus::transitions()` dan test workflow.
4. Evidence tetap generik di `qe_evidences`; kategori/step bukan tabel terpisah.
5. BEFORE, PROGRESS, dan AFTER harus memakai designator yang ada pada reservasi LOP.
6. Evidence rejected tidak dihitung sebagai kelengkapan workflow sampai diganti dan kembali pending/approved.
7. Completion approval memerlukan seluruh evidence workflow yang dapat direview approved; kategori `request_letter` adalah dokumen pendukung dan tidak ikut agregat approval/progres.
8. Scope ADMIN harus melalui `LopVisibilityService`; fallback legacy `branch`/`sto` tetap dipertahankan sampai backfill selesai.
9. QE Recovery tidak menggunakan `status_project`; Preventive/Relok mulai sebagai `usulan` dan menjadi `on_going` saat assignment.
10. BOQ menyimpan snapshot harga dan uraian. Jangan mengganti histori BOQ dengan join harga master terkini.
11. Realisasi Revenue hanya berasal dari LOP `completed`.
12. JATIM/JATENG DIY menggunakan Paket-5 dan BALNUS Paket-10 untuk fallback reservasi/nilai berbasis wilayah.
13. File evidence asli harus mempertahankan ekstensi/MIME; thumbnail boleh WebP.
14. Import multi-tabel harus transactional agar kegagalan tidak meninggalkan BOQ atau LOP parsial.

## 7. Perubahan Terakhir

### 7.1 Commit `98f39c9` — 1 Oktober 2026

- Paket reservasi otomatis berdasarkan region.
- Nilai BOQ aktual dapat berasal dari reservasi teknisi bila BOQ plan tidak ada.
- Revenue diperbaiki untuk nilai plan/realisasi dan scope admin.
- Bulk approval evidence per group.
- Data LOP dan Data BOQ menampilkan fallback reservasi teknisi.

### 7.2 Commit `2d74d4d` — 30 September 2026

- Approval evidence diperluas untuk Admin Area/Region/Branch/Service Area sesuai scope.
- Policy LOP/evidence dan regression test diperbarui.

### 7.3 Commit `0b2c404` — 30 September 2026

- Financial Overview pertama kali ditambahkan, kemudian dinamai Revenue Overview.
- Dashboard matrix dan filter nilai berdasarkan scope.

### 7.4 Commit `da3f78d` — 29 September 2026

- Parser Import BOQ diperbaiki agar volume template aktual terbaca.

### 7.5 Commit `974ac9d`, `42cd160`, `6666976` — 25 September 2026

- Evidence mobile mempertahankan ekstensi file asli dan memperbaiki upload.
- Import LOP/BOQ dioptimalkan, row writer dan index runtime ditambahkan.
- Upload import/evidence memakai URL origin agar tidak terkena mixed-content/proxy mismatch.

### 7.6 Commit `975b40f` — 25 September 2026

- Fondasi bulk import, BOQ master, queue, progress, admin scope, project status, service area, dan UI terkait.

### 7.7 Perubahan yang dibawa saat handover awal — 2 sampai 7 Oktober 2026

- Migration dan regression test `item_name` panjang sudah masuk ke riwayat branch melalui commit `d669288` dan merge `4b3bfcf`.
- Kotak metode Revenue Overview sedang disembunyikan menggunakan komentar Blade/HTML.

### 7.8 Perubahan kerja setelah merge `4b3bfcf` — 7 Oktober 2026

- Import BOQ dikembalikan menjadi satu form upload tanpa radio BOQ Plan/Actual dan tanpa konfirmasi replace.
- Ketergantungan runtime pada `qe_import_batches.file_hash` dihapus untuk memperbaiki error `Unknown column 'file_hash'`.
- Seluruh Import BOQ kembali memakai `qe_boqs/qe_boq_items`; tabel `qe_boq_plans` hasil merge dihapus karena redundan dan belum diterapkan pada database lokal.
- Item material BOQ disalin ke reservasi sebagai `qty`, sedangkan realisasi tetap dicatat pada `qty_actual` oleh teknisi.
- Paket hasil pembacaan file tersimpan pada BOQ existing dan digunakan pada reservasi teknisi.
- Regression test mencakup upload sederhana, reimport, Import BOQ Preventive menggunakan schema existing, deskripsi panjang, serta workflow teknisi.

### 7.9 Surat Permintaan Preventive/Relok — 8 Oktober 2026

- Aksi LOP pada Inbox, halaman Program, dan Master Data LOP dapat membuka modal kelola Surat Permintaan.
- Admin dapat memilih, mereview, menghapus dari daftar, lalu mengupload maksimal 12 PDF/foto per aksi.
- Teknisi melihat accordion Surat Permintaan pada Step 3 Evidence Pra dan dapat mengupload banyak file bila admin belum menyediakan dokumen.
- File memakai kategori `request_letter` pada `qe_evidences`; foto/PDF mempertahankan format aslinya dan ditampilkan melalui route file terautentikasi.
- Surat Permintaan merupakan dokumen pendukung, bukan evidence approval, sehingga tidak mengubah progres, status review, atau syarat completion.
- Tidak ada migration atau tabel baru untuk fitur ini.

### 7.10 Proyeksi BOQ Plan/Actual/Sisa — 8 Oktober 2026

- `LopBoqProjectionService` menjadi sumber data per LOP untuk membedakan BOQ Plan, BOQ Actual, dan Sisa Material.
- Preventive/Relok menampilkan snapshot import sebagai Plan; qty aktual teknisi diterapkan ke baris material beserta jasa pasangannya sebagai Actual.
- Recovery tetap tanpa Plan. Reservasi hanya menyimpan material, sedangkan designator jasa pasangan masuk otomatis ke nilai Actual berdasarkan paket wilayah/LOP.
- Detail LOP dan Detail Data BOQ menampilkan kategori MATERIAL/JASA; Sisa Material hanya menampilkan MATERIAL.
- Route JSON per LOP `reports.lop.boq-plan` ditambahkan. Tidak ada migration atau perubahan database.
- Regression test utama: `LopBoqProjectionTest`.

## 8. Status Masalah dan Pekerjaan Belum Selesai

### Prioritas tinggi

- Pastikan migration perluasan panjang uraian sudah dijalankan pada tiap environment setelah mendapat izin dan backup/tag siap.
- Jangan menambahkan kembali tabel `qe_boq_plans` atau kolom `file_hash`; keduanya tidak diperlukan oleh alur Import BOQ canonical.
- Pastikan worker queue produksi hidup. Batch berhenti di `queued` bila worker tidak berjalan.

### Prioritas menengah

- Putuskan apakah kotak metode Revenue Overview memang harus dihapus; saat ini hanya dikomentari dan belum di-commit.
- `MANAGER` dan `APPROVER` belum mempunyai dashboard/landing page khusus.
- README masih didominasi boilerplate Laravel dan kalimat upload menyebut foto menjadi WebP. Implementasi aktual mempertahankan format file asli dan hanya thumbnail yang WebP.
- Dokumentasi `CLAUDE.md` memuat beberapa rancangan awal yang sudah berubah, misalnya VIEWER versus MANAGER serta istilah step evidence lama. Gunakan code + handover ini sebagai sumber yang lebih baru.

### TODO eksplisit dalam code

- `app/Enums/UserRole.php`: dashboard monitoring untuk MANAGER.
- `app/Enums/UserRole.php`: landing Approval Evidence untuk APPROVER.

### Hal yang belum diverifikasi dari environment produksi

- Status migration server.
- Konfigurasi dan proses worker queue/supervisor.
- `storage:link`, permission `storage`/`bootstrap/cache`, batas Nginx/PHP upload.
- Konsistensi `APP_URL`, proxy HTTPS, dan `EVIDENCE_DISK`.

## 9. Status Pengujian

Baseline terakhir yang diverifikasi sebelum dokumen ini dibuat:

```text
230 tests passed
1139 assertions
```

Test memakai SQLite `:memory:` dan queue `sync`, sehingga tidak membuktikan konfigurasi MySQL, Nginx, storage permission, atau worker produksi.

Pemetaan test utama:

- LOP dan permission: `LopWorkflowTest`, `LopPermissionTest`, `LopManualInputTest`.
- Scope admin/project status: `AdminScopeAndProjectStatusTest`.
- Teknisi mobile: `TechnicianMobileWorkflowTest`.
- Evidence/storage: `EvidenceWorkflowTest`, `EvidenceAsyncUploadTest`, `EvidencePermissionTest`.
- Import/BOQ: `BulkImportAndBoqTest`, `LopExcelImportTest`, `DesignatorCsvImportTest`.
- Dashboard: `AdminDashboardTest`.
- Revenue: `RevenueDashboardTest`.
- Reporting: `MaterialReportTest`.
- User management: `UserManagementWorkflowTest`, `UserPermissionTest`.

## 10. Panduan Audit Sebelum Memperbaiki atau Menambah Fitur

### Tahap A — snapshot

1. Baca `AGENTS.md`, dokumen ini, dan `codex-skills/dompis-qe/SKILL.md`.
2. Jalankan `git status --short`, `git branch --show-current`, dan `git log -10 --oneline`.
3. Baca diff file yang sudah berubah dan jangan menimpanya.
4. Tentukan apakah task meminta analisis saja atau implementasi.

### Tahap B — trace end-to-end

1. Cari route dan middleware.
2. Periksa Form Request, Policy/Gate, dan controller.
3. Temukan service canonical yang memegang aturan domain.
4. Periksa model, relation, migration, dan fallback data legacy.
5. Periksa Blade/JavaScript untuk desktop dan mobile.
6. Temukan test terdekat sebelum membuat perubahan.

### Tahap C — database dan data

1. Gunakan `php artisan migrate:status` dan `php artisan db:table <table>` secara read-only.
2. Bandingkan migration ledger, schema fisik, dan kebutuhan code.
3. Jangan menjalankan migration, seeder, write query, rollback, fresh, atau wipe tanpa izin eksplisit user.
4. Untuk perubahan schema produksi, siapkan backup/tag, migration spesifik, rollback realistis, dan verifikasi setelah deploy.

### Tahap D — implementasi

1. Tambahkan regression test untuk bug atau aturan bisnis baru.
2. Perbaiki service/policy canonical, bukan hanya tampilan.
3. Pertahankan authorization backend dan scope lokasi.
4. Gunakan transaction untuk perubahan multi-tabel/file.
5. Hindari query database dalam loop Blade dan eager-load hanya data yang diperlukan.

### Tahap E — verifikasi

Minimal sesuai dampak:

```bash
vendor/bin/pint --test
php artisan test --filter=TestTerkait
php artisan view:cache
npm run build
git diff --check
```

Jalankan `php artisan test` penuh bila perubahan menyentuh lifecycle, scope, import, evidence, BOQ, atau perhitungan revenue lintas modul.

## 11. Deployment Ringkas

Contoh urutan setelah branch dan commit target sudah dipastikan:

```bash
cd /www/wwwroot/dompis-qe

git status
git fetch origin
git log --oneline HEAD..origin/collab/import-LOP-import-BOQ

php artisan down
git pull --ff-only origin collab/import-LOP-import-BOQ

composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart

chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan up
```

Sebelum deploy, buat tag/backup sesuai prosedur server. Jangan pull jika `git status` menunjukkan perubahan tracked yang belum dipahami. Folder server seperti `.well-known/` dapat tetap untracked selama tidak ikut `git clean` atau penghapusan massal.

## 12. Panduan Pindah ke Akun Codex Baru

1. Pastikan seluruh perubahan penting sudah di-commit dan di-push ke branch yang benar.
2. Buka repository dari root agar `AGENTS.md` terbaca otomatis.
3. Skill project tersedia di `codex-skills/dompis-qe`.
4. Agar dapat dipanggil sebagai `$dompis-qe` di luar aturan repository, salin folder skill tersebut ke direktori skills akun Codex baru.
5. Pada sesi pertama, minta Codex membaca `AGENTS.md`, handover ini, status Git, dan diff sebelum mengubah file.
6. Jangan menyalin `.env`, token, credential database, atau key produksi ke percakapan maupun repository.

Prompt awal yang disarankan:

```text
Gunakan panduan repository Dompis QE. Baca AGENTS.md, HANDOVER_DOMPIS_QE.md,
dan codex-skills/dompis-qe/SKILL.md secara lengkap. Setelah itu audit git status,
branch, commit terbaru, dan diff yang belum di-commit. Jangan mengubah file dulu.
Berikan ringkasan kondisi aktual, risiko aktif, dan file yang perlu disentuh untuk
permintaan saya berikutnya.
```
