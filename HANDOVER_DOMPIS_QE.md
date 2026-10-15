# Paket Handover Dompis QE

Terakhir diaudit: **15 Oktober 2026**
Repository: `hndrisyhptra/dompis-qe`
Branch aktif: `collab/import-LOP-import-BOQ`
HEAD dasar saat audit terbaru: `1ed5291` (`fix: Capture insera & Slot port opsional`)

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
- HEAD lokal saat task Matrix Program per scope berada di commit `afee345`; periksa ulang posisi remote sebelum pull/push.
- Jangan berpindah ke `main` untuk melanjutkan pekerjaan sebelum memastikan commit branch tersebut sudah di-merge atau memang sengaja ditinggalkan.

### 2.2 Perubahan lokal yang belum di-commit

Proyeksi BOQ dan pasangan jasa Recovery sudah masuk commit `c4cf256`; dashboard Summary berada pada `c57d6ea`, pencarian KHS pada `d307b4b`, halaman Review BOQ/stepper teknisi pada `ed123e0`. Review BOQ mobile, QE Relok Tiang, budget otomatis, Bulk Import User, dan diagnosis worker masuk commit `4cc8cf4`. Lima segmen, template Bulk LOP terbaru, hasil INCIDENT duplikat, dan artifact MainActivity masuk commit `87470d3`; guard login/logout dan pemulihan 419 sudah masuk `0840dd1`. Edit BOQ aktual, pembukaan Waiting Approval/reset Completed, dan navigasi asal sudah masuk commit `1845c0f`. Approval Saya, reminder login, badge pending, dan konfirmasi logout sudah masuk `4a1f6d8`. Password akun sendiri, scope topbar, pembatalan LOP semua status, dan Active LOP per pembuat sudah masuk `afee345`. Worktree bersih saat memulai task Matrix Program per scope. Perubahan terbaru yang belum di-commit: matrix admin per Region/Branch/STO/Program sesuai level scope, dropdown program pada tab Program, kolom Unassign serta drill-down lokasi. Tidak mengubah schema, workflow, import, atau source Android.

### 2.3 Status migration lokal

Migration redundan untuk tabel `qe_boq_plans`, relasi `boq_plan_id`, dan kolom `file_hash` sudah dikeluarkan dari perubahan kerja. Migration `2026_10_02_000001_expand_boq_item_name_columns.php` tetap penting untuk uraian BOQ panjang.

Audit read-only 8 Oktober 2026 (`migrate:status`, `db:table qe_lops`) memastikan MySQL lokal masih memakai enum tiga program. Migration baru `2026_10_08_000001_add_relok_tiang_program_to_qe_lops.php` berstatus **Pending**, belum dijalankan. QE Relok Tiang memerlukan migration tersebut sebelum disimpan pada MySQL. Tidak ada backfill budget atau perubahan data operasional yang dijalankan.

Pembaruan audit read-only 12 Oktober 2026: migration Relok Tiang kini tercatat **Ran batch 17** dan `qe_lops.program_type` sudah memuat empat nilai. Eksekusi migration tersebut dilakukan di luar sesi Bulk Import User ini. Struktur `users` beserta empat level scope dan `qe_import_batches.type` (varchar) tersedia; Bulk Import User tidak membutuhkan migration tambahan. Sesi ini tidak mengimport akun nyata atau mengubah database operasional.

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
- Form login/logout memeriksa context session same-origin sebelum satu POST asli, mengambil token terbaru khusus form tersebut, dan menolak logout dari tab yang membawa identitas akun lain. Halaman cache back/forward dimuat ulang. 419 HTML pada autentikasi menuju halaman segar dengan peringatan; form operasional/JSON tetap ditolak tanpa replay otomatis.
- Akun inactive ditolak; login throttling, session regeneration, dan logout tersedia.
- Role aktif: `SUPER_ADMIN`, `ADMIN`, `TEKNISI`, `MANAGER`, `APPROVER`.
- Permission granular tersimpan di database.
- User management mendukung tambah, edit, detail, aktif/nonaktif, soft delete, restore, dan histori.
- Admin dapat memiliki scope `area`, `region`, `branch`, atau satu/banyak `service_area`.
- Bulk Import User menerima XLSX/XLS/CSV melalui permission `manage_users`, sama dengan input manual. Template Excel/CSV dapat dipilih berdasarkan scope dan didownload dari sebelah Tambah User. Template Excel menyertakan panduan dan referensi lokasi aktif.
- Baris valid dibuat lewat `UserService`, termasuk hashing password, normalisasi FK/pivot scope, dan histori. Duplikasi username/NIK/email (termasuk soft-deleted) tidak ditimpa; hasil dan alasan gagal tersedia per baris.

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
- Program: QE Recovery, QE Preventive, QE Relok Utilitas, QE Relok Tiang. Kode nama LOP masing-masing `QEREC`, `QEPREV`, `QEREL`, `QERELTIANG`.
- Semua program mendukung 1–5 segmen pada input manual, edit, Bulk LOP, dan preview import legacy. `LopSegment::MAX_SELECTIONS` menjadi batas backend; `normalizeInput()` menerima string legacy/array serta pemisah `|`, koma, titik koma, dan label Mini OLT. Duplikasi segmen dibuang dengan urutan tetap; pilihan keenam ditolak, bukan dipotong diam-diam.
- Budget ditentukan `ProgramType::budgetType()`: Relok Utilitas CAPEX, semua program lainnya OPEX. Request/service mengabaikan budget kiriman yang bertentangan. Rule berlaku saat create/update/import LOP; record lama tidak diubah massal.
- Bulk LOP memakai `ProgramType::fromInput()` untuk variasi huruf, spasi, underscore, hyphen, kode program, dan teks yang mengandung satu nama program. Nama tidak dikenal atau mengandung beberapa program berbeda tetap ditolak per baris.
- `status_project`: Preventive dan kedua Relok memakai `usulan` lalu `on_going` saat assignment; Recovery menggunakan `null`.
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
3. Evidence pra: Surat Permintaan opsional untuk Preventive/Relok, lokasi dan foto kondisi awal wajib, Capture Ticket Insera opsional.
4. Evidence progress per designator reservasi.
5. Evidence after per designator dan `qty_actual` wajib; Capture Logic Slot Port (Screenshot Detail Slot Port OLT) opsional.

LOP baru dapat diajukan bila semua checklist wajib lengkap. Rejected evidence dapat diganti pada record yang sama, bukan membuat evidence baru.

Saat Waiting Approval, Completed, atau Rejected, seluruh lima tahap tetap dapat dibuka dalam mode review. Form reservasi/lokasi/rekap tidak ditampilkan; evidence tetap dikelompokkan menurut tahap dan item. Tahap dengan reject memiliki penanda merah, dan default halaman membuka reject pertama. Penggantian evidence tetap tersedia pada project Rejected melalui policy existing.

### 4.5 BOQ dan reservasi material

- BOQ satu per LOP di `qe_boqs`, memiliki paket dan item snapshot di `qe_boq_items`.
- BOQ dapat dibuat lewat import atau dikelola dari Master Data.
- Item BOQ dapat dicari, ditambah, diedit, dan dihapus selama aturan status mengizinkan.
- Card BOQ Aktual dari Reservasi Teknisi mempunyai tombol Edit BOQ Aktual per LOP. Admin sesuai scope/Super Admin dapat menambah material, menghapus item yang belum punya evidence/rekap, dan mengubah qty reservasi melalui modal dengan search designator dan konfirmasi. Qty actual tetap milik rekap teknisi; tidak membuat BOQ Plan palsu atau mengganti harga paket.
- Waiting Approval boleh diedit oleh admin; jika menambah material baru, LOP kembali Progress agar evidence/rekap item baru dilengkapi dan diajukan ulang. Completed menampilkan ikon gembok ber-tooltip Terkunci serta tombol reset terkonfirmasi. Reset membuka status Progress tanpa menghapus evidence, keputusan review, penugasan, qty_actual, atau snapshot BOQ; nilai realisasi keluar sementara sampai LOP Completed lagi.
- Saat assign, item material BOQ mengisi `qe_material_reservation_items.qty` sebagai kuantitas rencana.
- Teknisi mengisi `qe_material_reservation_items.qty_actual` sebagai kuantitas realisasi; tidak ada tabel BOQ Plan terpisah.
- Jika BOQ tidak ada, reservasi teknisi menjadi fallback Data BOQ dan perhitungan nilai sesuai aturan service.
- Detail LOP dan Detail Data BOQ memisahkan tiga proyeksi: BOQ Plan dari snapshot import, BOQ Actual dari `qty_actual`, dan Sisa Material dari selisih reservasi dengan aktual.
- QE Recovery tidak memiliki BOQ Plan. Recovery, Preventive, dan Relok Tiang hanya menghitung/menampilkan `MATERIAL`; hanya Relok Utilitas boleh `MATERIAL` dan `JASA`. Aturan kategori canonical berada pada `ProgramType::allowsBoqType()` / `includesBoqServices()`.
- Pasangan `JASA` otomatis berdasarkan kode `M-...` -> `J-...` hanya berlaku untuk fallback reservasi/tambahan material Relok Utilitas. Snapshot import tetap memakai item dan harga tersimpan.
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
- Unduh Template LOP memakai Excel dengan sheet LOP, Panduan, Referensi Program, Segmen, Lokasi, dan Paket. CSV tetap tersedia. Referensi STO/Branch/Area mengikuti scope akun; nama/header sheet LOP dipertahankan dan dibaca meskipun pengguna menyimpan file saat sheet Panduan aktif.
- Budget tetap otomatis: Relok Utilitas CAPEX, program lain OPEX. STO wajib; Branch/Area kosong mengikuti master STO, tetapi jika diisi harus sesuai. INCIDENT duplikat melaporkan nama LOP existing/arsip, atau baris pembuatnya untuk duplikasi dalam file; identitas LOP di luar scope admin tidak ditampilkan.
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
- Capture Ticket Insera dan Capture Logic Slot Port opsional pada semua program. Card kosong menyediakan Upload jika ada/Tidak Ada; pilihan Tidak Ada hanya presentasi tanpa evidence dummy atau penanda wajib di database. File yang sudah diupload tetap mengikuti pending/approve/reject dan harus direview sebelum Completed.
- QE Preventive/Relok mempunyai dokumen pendukung multi-file `request_letter` (PDF/foto). Admin mengelolanya dari aksi LOP; teknisi melihatnya pada Step 3 dan dapat upload sendiri hanya bila admin belum menyediakan file.
- Surat Permintaan tetap disimpan di `qe_evidences`, tetapi dikecualikan dari progres dan antrean approval karena bukan evidence hasil pekerjaan.
- Galeri menampilkan pending, approved, rejected, serta alasan reject.
- Approval dikelompokkan per LOP, step, evidence group, dan item designator.
- Reviewer dapat approve, reject, reset, dan bulk approve per accordion/group.
- LOP selesai hanya bila semua evidence workflow yang dapat direview sudah approved.
- Inbox Approval Saya memonitor LOP dengan assignment aktif oleh admin login, diiriskan dengan scope lokasi dan permission approve_evidence. Popup login sekali per login menampilkan pending serta LOP Waiting Approval yang seluruh evidence sudah approved namun belum Completed; tanpa polling/notifikasi terjadwal. Menu Approval Evidence umum tetap memuat seluruh scope admin, bukan hanya assignment miliknya.
- Admin Area/Region/Branch dapat mereview seluruh evidence dalam scope lokasi. Admin Service Area tetap dibatasi scope service area, bukan hanya pembuat LOP.

### 4.8 Dashboard, program, dan Revenue Overview

- Dashboard ADMIN/SUPER_ADMIN memiliki KPI serta tab Program (matrix program), Status LOP (matrix pipeline), dan Summary (monitoring harian per branch).
- Summary menyediakan tanggal monitoring WIB, enam KPI aktivitas, filter pergerakan/region/pencarian branch, accordion rincian LOP, pencarian Incident/nama LOP, dan pagination 20 LOP. Scope diterapkan kembali pada endpoint rincian.
- Card Approval Evidence, Kesiapan Data & Penugasan, dan Prioritas Operasional dihapus dari dashboard; sudut card dashboard menggunakan `rounded-md`.
- Matrix SUPER_ADMIN dapat dibuka per region/branch; ADMIN mengikuti scope lokasi.
- Matrix Program ADMIN: Area per Region di Area tersebut, Region per Branch, Branch per Service Area/STO, Service Area (termasuk multi-STO) per Program. Dropdown empat program/Semua Program berada pada tab Program; filter program berlaku juga pada KPI, Matrix Status LOP, dan Summary. Assign menghitung status Assigned; Unassign setelahnya menghitung Draft/Unassigned.
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
- `resources/views/technician/boq-review.blade.php`
- `resources/views/technician/partials/boq-review.blade.php`
- `resources/views/components/technician-evidence-uploader.blade.php`
- `resources/js/app.js`

### 5.3 Evidence dan approval

- `app/Models/QeEvidence.php`
- `app/Services/EvidenceService.php`
- `app/Services/EvidenceApprovalService.php`
- `app/Services/MyApprovalService.php`
- `app/Http/Controllers/EvidenceController.php`
- `app/Http/Controllers/EvidenceApprovalController.php`
- `app/Http/Controllers/MyApprovalController.php`
- `app/Policies/EvidencePolicy.php`
- `resources/views/evidence-approval/index.blade.php`
- `resources/views/evidence-approval/lop-review.blade.php`
- `resources/views/my-approvals/index.blade.php`
- `resources/views/my-approvals/_login-reminder.blade.php`
- `resources/views/components/approval-evidence-group.blade.php`

### 5.4 BOQ, import, dan queue

- `app/Services/BoqImportService.php`
- `app/Services/BulkLopImportService.php`
- `app/Services/BulkLopTemplateService.php`
- `app/Services/BoqService.php`
- `app/Services/ReservationBoqService.php`
- `app/Support/MaterialReservationRules.php`
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
- `app/Services/DashboardProgramMatrixService.php`
- `app/Support/DashboardMatrixMetrics.php`
- `app/Services/DashboardMonitoringService.php`
- `app/Services/RevenueService.php`
- `app/Services/LopBoqValueService.php`
- `app/Services/RegionalPackageResolver.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/RevenueController.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/dashboard/partials/program-matrix.blade.php`
- `resources/views/dashboard/partials/monitoring.blade.php`
- `resources/views/revenue/index.blade.php`

### 5.6 User dan scope

- `app/Models/User.php`
- `app/Enums/AdminScopeType.php`
- `app/Services/UserService.php`
- `app/Services/UserImportService.php`
- `app/Services/UserImportTemplateService.php`
- `app/Jobs/ProcessUserImport.php`
- `app/Http/Controllers/UserImportController.php`
- `app/Http/Requests/StoreUserImportRequest.php`
- `app/Http/Controllers/UserController.php`
- `app/Policies/UserPolicy.php`
- `resources/views/users/_access-scope.blade.php`
- `resources/views/users/_edit-modal.blade.php`
- `resources/views/users/_bulk-import.blade.php`

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
15. BOQ Plan/Actual, ekspor, dan Revenue hanya MATERIAL untuk Recovery/Preventive/Relok Tiang; Relok Utilitas boleh MATERIAL + JASA. Data jasa historis tidak dihapus massal.
16. Capture Insera dan Capture Logic Slot Port tidak wajib untuk kelengkapan Pra/After. Jika diupload, keduanya tetap evidence reviewable, bukan dokumen exempt seperti Surat Permintaan.

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

### 7.11 Tab Dashboard dan Summary harian — 8 Oktober 2026

- Matrix Program dan Status LOP dipisahkan ke tab. Tab aktif disimpan di URL dan dipertahankan saat mengganti filter/tanggal.
- `DashboardMonitoringService` menghitung aktivitas harian dengan rentang waktu WIB inklusif awal hari dan eksklusif awal hari berikutnya; query mengikuti timezone penyimpanan `config('app.timezone')`.
- Aktivitas berasal dari `qe_lop_histories`, pembuatan LOP (fallback bila tidak ada event created), tanggal upload/review di `qe_evidences`, dan `qe_boq_histories`. Aktor dan LOP bergerak dihitung unik. Tidak menganggap setiap perubahan `updated_at` sebagai event.
- Review evidence memakai `reviewed_at` terakhir yang tersedia; reset/replacement tanpa histori audit tersendiri tidak direkonstruksi sebagai seluruh rangkaian event. Summary adalah monitoring aktivitas tercatat, bukan snapshot historis lengkap.
- Total LOP mencakup record yang masih tersedia dan dibuat sebelum akhir tanggal monitoring; status/teknisi pada rincian mengikuti kondisi terkini. Branch tanpa LOP tetap muncul bila masuk scope.
- Endpoint `dashboard.monitoring-lops` memuat maksimum 20 LOP per halaman dengan authorization role dan scope Area/Region/Branch/multi-Service Area di server.
- Rincian branch berbentuk card LOP dengan accordion riwayat aktivitas harian, bukan tabel. Branch Bergerak otomatis membuka daftar dengan filter LOP Bergerak; filter dapat diganti ke semua/belum bergerak.
- Last update branch/LOP menampilkan aktivitas terakhir dan identitas pelaku dalam format Nama — Role (contoh Budi — Admin, Adi — Teknisi), bukan teknisi assignment. Nama/role dibaca dari akun tersimpan saat ini karena histori lama tidak menyimpan snapshot identitas.
- Endpoint `dashboard.monitoring-activities` memuat riwayat harian per LOP saat accordion dibuka, dipaginasi 20 event dan dibatasi scope backend. Waktu yang sama diurutkan stabil berdasarkan prioritas sumber dan ID event; review didahulukan dari upload.
- Matrix dan filter branch memprioritaskan FK lokasi, tetap mendukung fallback nama branch legacy. Query prioritas dan agregat approval lama dihapus karena card tidak dipakai lagi.
- Tidak ada migration, perubahan data operasional, atau tes browser. Regression test: `DashboardMonitoringTest` dan `AdminDashboardTest`.

### 7.12 Stepper review dan BOQ teknisi — 8 Oktober 2026

- Card gabungan Review Evidence Pekerjaan dihapus. Review per tahap tetap tersedia saat Waiting Approval/Completed/Rejected tanpa membuka kembali form pekerjaan.
- `TechnicianWorkflowService::state()` menyediakan `rejectedSteps` sebagai pemetaan tunggal kategori ke tahap untuk controller dan stepper. Penguncian tahap saat workflow aktif tetap dipertahankan.
- Dokumen request_letter tidak masuk penanda reject workflow. Evidence lama/global di luar item reservasi saat ini tetap terlihat pada tahap sesuai kategori.
- Stepper ditempatkan paling atas tanpa sticky/freeze, mengikuti scroll normal. Nama LOP, Branch, dan STO tetap diringkas dalam satu card di bawah stepper; lokasi memakai FK dengan fallback legacy. Desain mobile kembali ke gaya awal: card identitas gelap, sudut rounded-2xl/3xl, tombol brand besar, dan spacing yang nyaman. Perubahan ini hanya presentasi, bukan workflow/perhitungan.
- Tombol Review BOQ hanya tersedia setelah seluruh qty_actual reservasi disimpan pada Step After (`materialUsageComplete`); qty 0 valid, qty null menyembunyikan tombol. Proyeksi BOQ hanya dimuat saat membuka halaman review, bukan pada halaman workflow.
- Tombol membuka halaman khusus `technician.projects.boq-review` (bukan modal), berisi tabel perbandingan Plan vs Actual untuk Preventive/Relok dan hanya BOQ Actual untuk Recovery, termasuk jasa pasangan. Total nilai berada di footer tabel. Tabel mobile memakai kolom Item/Plan/Actual dengan qty dan nilai ditumpuk, tanpa minimum lebar/horizontal scroll; uraian panjang disembunyikan dalam details per item.
- Tombol Kembali menuju LOP dan step asal melalui parameter step yang dibatasi 1–5 (default After). Route baru memakai middleware login/role TEKNISI serta policy view LOP; akses langsung saat actual belum lengkap diarahkan kembali ke pekerjaan dengan pesan validasi.
- Proyeksi mengambil `LopBoqProjectionService`; qty/nilai Plan selalu dari snapshot import, bukan qty reservasi yang sudah diubah. Nilai Actual dari qty_actual, dengan peringatan bila belum direkap atau harga belum tersedia.
- Form pekerjaan dinonaktifkan pada mode review; Completed/Waiting Approval tidak menampilkan upload baru atau penggantian evidence. Tidak ada perubahan lifecycle, schema, migration, atau data operasional.
- Regression test: `TechnicianProjectReviewTest`, `TechnicianMobileWorkflowTest`, `LopBoqProjectionTest`.

### 7.13 Review BOQ ringkas dan penjelasan data kosong — 8 Oktober 2026

- User memastikan tanda — di server disebabkan harga yang belum tersedia dan meminta hanya revisi UI. Tidak ada perubahan service/perhitungan, migration, atau write database operasional.
- Item tanpa Plan diberi keterangan Tidak ada di Plan; Actual tanpa harga diberi Harga belum tersedia; quantity null diberi Belum direkap. Qty/harga 0 yang valid tetap ditampilkan sebagai angka, bukan data kosong.
- Footer memakai Total tersedia bila harga/rekap belum lengkap. Bila tidak satu pun item memiliki nilai actual, footer menampilkan —, bukan Rp 0. Nilai subtotal yang ada tetap memakai hasil service existing.
- Audit database lokal read-only: kolom BOQ/reservasi tersedia; contoh Recovery memiliki quantity actual dan harga material, tetapi dua harga jasa belum tersedia. Ini bukan verifikasi database atau cache server produksi.
- Regression test tambahan mencakup layout mobile, uraian panjang, harga kosong seluruh/sebagian, serta nilai 0 existing. Pengujian tidak melalui browser.

### 7.14 Program Relok Tiang dan budget otomatis — 8 Oktober 2026

- `ProgramType` menjadi sumber empat program, budget otomatis, dukungan multi-segmen, serta normalisasi label import. Menu/filter/matrix/dashboard/revenue memakai enum yang sama; layout chart Revenue menyesuaikan empat program.
- Form tambah/edit LOP menampilkan budget otomatis tanpa radio CAPEX/OPEX. Kedua Relok dapat memakai multi-select segmen. Nama dan Incident otomatis mendukung kode/urutan program baru (Relok Tiang urutan 4).
- Template Bulk LOP mempertahankan header existing, dengan empat contoh program dan kombinasi `tiang|odp`. `budget_type` boleh kosong dan selalu mengikuti program. Variasi seperti `qe recovery`, `qe_recovery`, `QE RECOVERY`, dan label dengan kata tambahan diterima jika tidak ambigu.
- Parser nama project BOQ dan preview import legacy mengenali Relok Tiang, bukan salah memetakannya ke Relok Utilitas. Auto-create LOP dari BOQ mengikuti budget program, bukan threshold nominal lama.
- Migration additive memperluas `qe_lops.program_type` menjadi empat nilai. Rollback ditolak bila ada LOP Relok Tiang, termasuk soft-deleted, agar tidak menghilangkan data. **Belum ada izin/eksekusi migration MySQL lokal atau produksi.**
- Regression `LopProgramRulesTest` mencakup normalisasi/ambiguity, input manual/edit, bulk import, budget otomatis, multi-segmen, assignment/usulan/on_going, dashboard/revenue, deteksi project BOQ, dan guard rollback. Scope/approval/evidence serta snapshot harga tidak diubah.

### 7.15 Bulk Import User sesuai scope — 12 Oktober 2026

- Tombol Bulk Import User dan Download Template berada di sebelah Tambah User. Upload memakai modal dengan panduan kolom scope, loading state, dan error bag `userImport` terpisah dari form manual/edit.
- Header template: `name,username,nik,email,phone,password,role,status,admin_scope_type,area,region,branch,service_areas`. Wajib: `name,username,password,role`; Admin juga wajib scope dan kolom lokasi sesuai levelnya. Password minimal delapan karakter, NIK/email/phone opsional, status kosong berarti active.
- Area/Region/Branch memakai kode atau nama master aktif, Service Area memakai `workzone`/kode STO. Role/scope/lokasi menerima variasi huruf besar-kecil; parent yang diisi harus konsisten. Kolom level di bawah scope Area/Region/Branch wajib dikosongkan agar tidak ada akses yang menyesatkan.
- Admin Service Area dapat memakai banyak kode STO dipisah `|` (juga menerima comma/semicolon), maksimal 100, termasuk lintas branch. Parent opsional harus cocok dengan semua STO. Role non-Admin hanya memakai branch opsional, mengikuti `UserService` existing.
- Template dapat dipilih all/area/region/branch/service_area/non_admin dan format XLSX/CSV. Baris CONTOH memakai password kosong dan status inactive; user wajib mengganti contoh dan mengisi password sebelum import. XLSX menyimpan identifier sebagai text dan menyertakan sheet Panduan/Referensi Lokasi; sheet Users tetap dibaca walaupun sheet lain aktif saat file disimpan.
- `ProcessUserImport` memakai queue existing, satu attempt, timeout 600 detik, `failOnTimeout`, dan lock per batch. Pengunggah harus tetap aktif serta berizin saat worker mulai. Batas upload 5 MB/1000 user; file kosong, rusak, atau terlalu besar ditolak sebelum membuat akun.
- Batch type `users` memakai `qe_import_batches/qe_import_rows` existing. Setiap create user + hasil success satu transaction; baris gagal tidak membatalkan baris valid. Hasil tersimpan secara durable per baris, progress agregat diperbarui setiap 25 baris/akhir file, dan batch finished tidak diproses ulang.
- File berisi password disimpan terenkripsi pada local private disk. Saat parsing, salinan sementara privat dibuat lalu dihapus bersama file sumber pada completion/failure callback. Payload hasil memakai whitelist tanpa password/NIK/email/phone, serta exception SQL tidak ditampilkan. Bila worker dibunuh paksa sehingga failure callback tidak berjalan, periksa batch processing yang stale dan cleanup salinan `.working.*` privat; jangan mengekspos isi file/log.
- Hasil/status tersedia pada `users.imports.show/status` dan dibatasi UserPolicy. Jalur import-results generic juga memeriksa permission user untuk batch jenis users. UI menampilkan lima upload terakhir dengan pengunggah, waktu WIB, status, dan tautan hasil.
- `SpreadsheetReader::table()` mendapat opsi sheetName/maxRows khusus fitur baru, dengan default existing tetap sama untuk LOP/BOQ. Tidak ada migration baru atau akun nyata yang diimport. Worker produksi dan limit server belum diuji; restart worker setelah deploy dan pastikan retry_after lebih besar dari timeout job.
- Regression: `UserBulkImportTest` (scope/permission/hashing/leading zero/duplikasi/XLSX/CSV/template/queue/security/cleanup), ditambah full suite existing. Tanpa tes browser.

### 7.16 Diagnosis import user dan restart worker lokal — 12 Oktober 2026

- File user yang dilaporkan gagal diperiksa read-only memakai reader aplikasi terbaru serta validator/master lokal: 16 baris role TEKNISI valid, tanpa kesalahan header, scope, lokasi, atau field wajib. Tidak ada akun nyata yang dibuat, tidak ada nilai password yang ditampilkan, dan file sumber user tidak diubah.
- Tiga batch terakhir gagal sebelum total_rows terisi. Worker dompis-qe masih berjalan sejak 7 Oktober, sebelum penambahan argumen sheetName/maxRows pada SpreadsheetReader. Worker lama dapat melempar Unknown named parameter, yang sebelumnya tertutup pesan template generik. Pemeriksaan ulang permission folder upload memastikan pemiliknya sama dengan worker lokal; file sumber batch gagal memang sudah dihapus.
- `SpreadsheetReadException` membawa alasan parser yang aman (headers_missing, too_many_rows, file_unreadable), tetap merupakan RuntimeException untuk kompatibilitas import existing. UserImportService membedakan worker_outdated, dekripsi, storage, pembacaan master, dan kegagalan umum, serta mencatat error_code/error_stage tanpa pesan SQL atau nilai credential.
- Sesudah persetujuan user dan verifikasi antrean kosong, queue:restart dijalankan dan worker dompis-qe lama dipastikan berhenti. Worker baru dijalankan dengan queue:work --tries=1 --timeout=600. Worker proyek dompis-cons tidak dihentikan. Batch lama tidak diretry/reimport; user harus upload ulang lewat UI.
- Worker baru aktif dalam sesi terminal Codex lokal; jalankan worker melalui terminal/Supervisor yang tetap hidup bila sesi ini ditutup. Restart worker setelah perubahan script; optimize:clear saja tidak memuat ulang class pada worker long-running.
- Regression tambahan mensimulasikan signature worker lama dan memastikan error parser spesifik tanpa membocorkan password. Full suite lulus; hasil import file nyata melalui worker baru masih menunggu percobaan ulang oleh user.

### 7.17 File chooser Android WebView — 12 Oktober 2026

- Source MainActivity dari attachment user memakai package `com.area3.dompisqe` dan URL Dompis QE; keduanya dipertahankan, walaupun pembahasan awal menyebut APK Dompis Cons. File lengkap hasil perbaikan tersedia di `Claude outputs/MainActivity-fixed.kt`; gunakan untuk mengganti isi MainActivity.kt pada proyek Android, bukan menambahkan class MainActivity kedua.
- Bug: acceptTypes digabung dengan comma lalu dipasang pada Intent.type. Ini bukan satu MIME valid, sehingga sebagian pemilih file Android menonaktifkan foto. Normalisasi kini mendukung array MIME, daftar comma, huruf besar/kecil, wildcard, serta ekstensi seperti .jpg/.png/.pdf/.xlsx; beberapa tipe memakai Intent.EXTRA_MIME_TYPES.
- Pilihan satu/banyak file tetap mengikuti mode WebView. Kamera hanya ditawarkan jika input menerima JPEG; capture langsung memakai intent kamera jika tersedia. Kamera/FileProvider yang gagal tidak menghalangi picker galeri. URI kamera mendapat ClipData serta grant read/write.
- Hasil upload dibatasi URI content yang dapat dibaca; picker pihak ketiga tidak dapat menunjuk URI privat aplikasi kecuali URI kamera yang sedang disiapkan. Callback dibatalkan/ditutup dengan aman. File kamera kosong tidak diteruskan, file sementara yang tidak dipilih dibersihkan, dan foto yang dipilih tetap disimpan agar WebView sempat membacanya saat upload.
- Fitur login/cookie, geolocation/GPS/native bridge, download, navigasi/back, dan konfigurasi URL tidak direfactor. Tidak ada perubahan Laravel/database/migration/permission storage baru. Konfigurasi Manifest/FileProvider dan resource layout existing tetap dibutuhkan.
- Verifikasi: type-check seluruh source memakai Kotlin 2.2.20, Android SDK 35, dependency AndroidX cached, dan stub nama resource R; berhasil dengan warning deprecated existing. Helper actual diuji di JVM dengan stub Android: 16 skenario MIME dan 32 konfigurasi single/multiple berhasil. Ini bukan build APK lengkap, instrumented test, atau verifikasi device OEM.
- Setelah mengganti source, build/reinstall APK dan uji perangkat bermasalah (foto Telegram/galeri, kamera, multi-select, cancel, serta PDF jika diterima input). Perubahan server saja tidak memperbarui wrapper APK.

### 7.18 Bulk LOP terbaru dan lima segmen semua program — 12 Oktober 2026

- `LopSegment` menjadi sumber batas lima pilihan dan normalisasi segmen. Request tambah/edit, `LopService`, Bulk LOP, serta preview import legacy memakai aturan tersebut; input satu segmen lama tetap diterima. Form manual/edit memakai combobox multi-pilih untuk semua program, pencarian segmen dan penghitung 0/5, blok pilihan keenam, serta nama LOP berdasarkan seluruh pilihan. Pergantian program mempertahankan pilihan.
- `BulkLopTemplateService` menghasilkan workbook enam sheet dengan empat contoh program, budget otomatis, contoh lima segmen, panduan kolom opsional/wajib, serta referensi scope lokasi dan paket. Header existing dipertahankan. Endpoint template default XLSX dan opsi `?format=csv`; sheet LOP diprioritaskan saat parsing. Contoh harus diganti/dihapus sebelum upload. Identifier ditulis sebagai Text dan CSV memakai BOM UTF-8.
- Duplikasi INCIDENT diprefetch tanpa query tambahan per baris normal, termasuk soft-deleted. Nama LOP hanya ditampilkan bila masuk scope admin; SUPER_ADMIN dapat melihat seluruh identitas. Duplikat dalam file menyebut nama hasil dan nomor baris pertama yang berhasil dibuat. Unique constraint saat batch lain mendahului ditangani dengan pesan identitas aman, bukan SQL.
- Menu Program QE Relok Tiang sudah dibentuk dari `ProgramType::cases()`, jadi tidak ditambahkan menu hardcoded/duplikat. Regression memverifikasi filter program dan scope, serta tab Usulan/On Going existing. Tidak ada perubahan scope, workflow approval, snapshot BOQ, atau perhitungan Revenue.
- Audit MySQL lokal read-only memastikan enum empat program dan kolom segment JSON/longtext tersedia; tidak perlu migration baru. Referensi Paket memakai record non-soft-deleted, **bukan is_active**: kolom tersebut tidak ada di tabel packages canonical. Export template melalui service aplikasi dengan master lokal berhasil (392 STO untuk scope SUPER_ADMIN), lalu workbook diperiksa dan seluruh sheet dirender untuk QA. Tidak ada import nyata atau write database operasional.
- Test baru: `BulkLopLatestRulesTest` (batas lima/enam, budget/nama/status, duplikasi aktif/arsip/file/race, referensi scope, unduh Excel/CSV, sheet Panduan aktif, permission, dan menu program); frontend `node --test tests/Frontend/lop-form.test.mjs` mencakup lima skenario combobox. Update regression lama agar batas keenam ditolak dan request template CSV eksplisit.
- Verifikasi akhir: 273 test Laravel/1942 assertions dan 5 test frontend lulus; build Vite, kompilasi Blade, dan diff check lulus. Pint pada seluruh file PHP task lulus. Pint global masih melaporkan format existing pada 14 file di luar task (termasuk ProgramController, migration lama, User, dan config Excel); file-file itu tidak direformat agar perubahan tetap terarah.
- Setelah deploy, build asset, clear/rebuild view cache sesuai prosedur, dan **restart worker** agar kode import terbaru dipakai. Worker lokal/produksi tidak direstart pada task ini; batch lama tidak diulang. MySQL produksi, worker nyata, dan browser belum diverifikasi.

### 7.19 Login/logout dan pemulihan session 419 — 12 Oktober 2026

- Audit code: session ID diregenerate setelah login; logout menginvalidate session dan meregenerate CSRF token. Alur keamanan existing sudah benar, tetapi form dari halaman lama/tab lain dapat membawa token sebelum rotasi. Browser back/forward cache tidak ditangani frontend dan 419 hanya menjadi error default. Pemicu stale token direproduksi lewat regression dengan CSRF benar-benar aktif; ini bukan bukti bahwa seluruh kejadian server produksi disebabkan faktor yang sama.
- `AuthenticatedSessionController::context()` dan GET `auth/session` memberikan token, ID akun saat ini, dan tujuan role, tanpa detail profil/credential. Respons no-store, Vary Cookie, throttle 120/min, dan Sec-Fetch-Site cross-site ditolak; tidak menambahkan CORS/JSONP. `resources/js/auth-session.js` memeriksa context hanya saat submit login/logout, dengan batas waktu 15 detik dan pencegahan klik ganda. Token hanya diperbarui pada form autentikasi, bukan form pekerjaan atau global header XHR.
- Form login/logout memakai URL relative same-origin, menghindari perpindahan host/protokol dari action URL. Jika akun aktif berbeda dengan ID pada halaman logout, tidak mengirim POST logout dan membuka workspace akun saat ini; halaman login lama juga tidak menimpa session akun yang sudah aktif. Gangguan jaringan menampilkan pesan, memulihkan tombol, dan mempertahankan input di DOM saja. Password tidak disimpan di browser storage/server untuk replay.
- Listener pageshow persisted memuat ulang halaman dari back/forward cache, bukan setiap pageshow normal. Tidak ada polling/keepalive, perubahan lifetime, APP_KEY, cookie, driver session, atau pengecualian CSRF.
- `SessionExpiredResponse` diregistrasikan melalui render callback HttpException 419 (Laravel sudah mengonversi TokenMismatchException). HTML login/logout diarahkan ke login/workspace segar dengan auth_warning; hanya username boleh di-flash untuk login guest. Permintaan JSON tetap 419 dengan code session_expired dan URL pemulihan. Form pekerjaan HTML mendapat halaman 419 ringkas; tindakan/file tidak pernah dikirim ulang otomatis atau ditampilkan dalam error.
- Test backend `SessionExpiryTest` memakai subclass ValidateCsrfToken dengan bypass test dimatikan: stale login/logout, password tidak diflash, JSON 419, context/cross-site/cache, tiga siklus login–logout antar role memakai cookie aktual, rotasi session ID/token, dan blocking form pekerjaan. Frontend auth-session.test.mjs menguji sepuluh skenario dengan DOM/fetch stub, termasuk akun berubah, offline, origin, cache, dan klik ganda; bukan browser E2E.
- Audit konfigurasi lokal hanya membaca whitelist: session database, lifetime 120 menit, cookie dompis-qe-session, path /, domain null, HttpOnly true, SameSite lax, konfigurasi tidak dicache. Tidak menampilkan token/cookie value/credential, mengubah .env, menjalankan migration, menghapus session, atau login akun nyata. Status cookie HTTPS/proxy/cache/session storage server aaPanel belum diverifikasi; jika 419 masih muncul, minta environment, urutan kejadian, host/protokol, serta bukti aman tanpa password/cookie/token.
- Setelah deploy: build asset baru dan clear/rebuild view/route/config cache sesuai prosedur. Jangan jalankan key:generate atau hapus seluruh session sebagai perbaikan default. Session/driver/lifetime existing dipertahankan. Scope, workflow, import, BOQ, dan Revenue tidak berubah.

### 7.20 Edit BOQ Aktual dari Reservasi Teknisi — 12 Oktober 2026

- Card fallback Data BOQ memakai qe_material_reservations/items, bukan qe_boqs. Aksi edit baru ditempatkan per LOP pada kolom Aksi, memakai modal responsive rounded-md, search di dropdown, tambah/hapus material, qty reservasi integer, qty aktual readonly, serta konfirmasi Ya/Tidak saat hapus dan simpan. Designator existing tetap terikat record; untuk mengganti item yang belum terpakai, hapus lalu tambah item baru. Minimal satu/maksimal 100 item, sama dengan reservasi teknisi.
- PUT master-data/boqs/reservations/{qe_lop} melalui UpdateReservationBoqRequest dan ReservationBoqService. Role admin-level tetap dikunci middleware; policy update dan LopVisibilityService memeriksa scope di backend, termasuk Area/Region/Branch/multi-Service Area. SUPER_ADMIN dapat mengelola semua LOP. Index menggunakan scope query existing, eager-load ID evidence, dan menyiapkan payload di controller tanpa query policy per baris Blade.
- Service memakai transaction dan lock LOP/reservasi/items. SHA-256 versi state reservasi mencegah simpan dari editor lama setelah qty/rekap/penugasan berubah. Current ID item dan qty_actual dipertahankan; qty tidak boleh lebih rendah dari pemakaian. Item dengan evidence aktif atau qty_actual terisi (termasuk 0) tidak boleh dihapus. Item baru harus master MATERIAL non-soft-deleted; JASA virtual Recovery tetap dihitung oleh service proyeksi existing, bukan dimasukkan ke reservasi.
- Aturan awal mengunci Waiting Approval/Completed; aturan ini **direvisi pada 7.21**. LOP yang sudah mempunyai BOQ Plan tetap ditolak oleh jalur reservasi fallback dan harus memakai editor BOQ Plan existing. Status_project, harga/snapshot/paket BOQ, dan qty_actual tidak diubah. Reservasi kembali draft setelah perubahan; teknisi merekap actual item baru sebelum completion.
- MaterialReservationRules menyatukan batas status, qty minimum pemakaian, dan proteksi hapus untuk jalur BOQ Plan serta BOQ Aktual. Partial picker designator digunakan bersama kedua editor. Opsi material-only/max item dan proteksi UI ditambahkan pada boqEditor tanpa mengubah perilaku pencarian/konfirmasi Plan existing.
- LopService mencatat event reservation_updated (status sebelum/sesudah tetap sama) dengan actor dan snapshot before/after di qe_lop_histories.meta. Summary dashboard menamai event Update BOQ Aktual / Reservasi; tidak membuat qe_boqs/qe_boq_items atau histori BOQ Plan untuk reservasi fallback.
- Audit schema MySQL lokal read-only: seluruh migration Ran, qty dan qty_actual decimal(15,3), unique (reservation_id,designator_id) tersedia. Tidak ada migration baru, import, penghapusan item nyata, atau write data operasional. Batas integer aplikasi dipertahankan tanpa mengubah schema.
- Test baru ReservationBoqManagementTest mencakup tambah/hapus/edit qty tanpa kehilangan actual/ID, rollback item protected (actual 0/nonzero/evidence), konfirmasi/validasi/type/archive, stale version, scope semua level/role, status terkunci, guard BOQ Plan, histori, pasangan jasa Recovery, dan tampilan item baru di teknisi. Frontend boq-editor.test.mjs menambah 4 skenario material-only/search, konfirmasi/proteksi, batas/duplikasi, dan batal/open ulang. Full suite 286 tests/2113 assertions dan 19 test frontend lulus; build, Blade, Pint file task, dan diff check lulus. Browser dan konkurensi worker/teknisi nyata belum diuji.

### 7.21 Edit Waiting Approval, reset Completed, dan navigasi asal — 13 Oktober 2026

- MaterialReservationRules mengizinkan Waiting Approval untuk editor BOQ Plan dan BOQ Aktual. Completed tetap ditolak oleh kedua service walaupun request editor lama dikirim langsung. Card menampilkan tombol ikon gembok dengan tooltip Terkunci dan ikon Reset LOP untuk edit BOQ; modal mempunyai konfirmasi Ya, reset/Tidak, batal serta penjelasan dampak Revenue.
- POST master-data/boqs/lops/{qe_lop}/reset memakai middleware admin-level, ResetBoqRequest (confirmed accepted), policy scope update/resetBoq, dan LopService::resetCompletedForBoq. Service mengunci record lalu melakukan transisi Completed -> Progress dan mencatat actor/note/status di histori. Reservasi kembali draft tanpa menghapus item atau qty_actual. Evidence, file, keputusan review, assignment aktif, paket, BOQ snapshot, dan status_project tetap utuh. Reset ulang dari halaman stale ditolak. Endpoint transisi umum tidak boleh membuka Completed tanpa aksi reset tersebut.
- Penambahan designator MATERIAL saat Waiting Approval melalui editor Plan/Actual membuka Waiting Approval -> Progress melalui LopService, tanpa membuat reject palsu atau menghapus persetujuan foto lama. Teknisi perlu mengisi evidence dan qty_actual item baru kemudian submit ulang. Koreksi qty/harga tanpa material baru tidak otomatis mengubah status Waiting Approval. Proteksi hapus item dengan evidence/actual dan minimum qty pemakaian tetap berlaku.
- Enum transisi diperbarui, dengan authorization khusus admin untuk kedua jalur buka ulang. Save BOQ Plan dan completeReview mengunci serta membaca ulang status LOP dalam transaction agar request lama tidak menimpa reset/penambahan material. SQLite regression memeriksa state stale, tetapi tidak menggantikan uji konkurensi MySQL.
- Simpan LOP baru kembali ke form Input LOP Baru yang kosong, bukan Inbox/Approval Evidence. Import preview legacy kembali ke form import. Link Inbox/History lama milik Super Admin menuju Master Data LOP (History memakai status Completed), bukan Approval Evidence; menu Inbox Super Admin tetap tidak ditambahkan.
- LopReturnDestination memvalidasi origin/URL dan whitelist halaman GET internal, menolak URL eksternal, action, halaman tidak dikenal, fragment, dan karakter kontrol. Edit LOP, assign/unassign, transisi, upload/hapus evidence legacy, dan selesai review mempertahankan halaman asal beserta filter. Link edit/assign membawa return_url; stepper review, tombol kembali, dan form selesai review meneruskannya sampai selesai. Masuk langsung dari menu Approval tetap kembali ke daftar Approval/filter asal. Tombol Review Evidence tetap berfungsi dan tidak dihapus.
- Regression ReservationBoqManagementTest diperluas untuk Waiting Approval, lock/reset Completed, konfirmasi, scope semua level/role, preservasi actual/evidence/Plan/assignment, Revenue sebelum/sesudah reset dan completion, material tambahan, serta stale completion. LopReturnNavigationTest memverifikasi admin/Super Admin, form create, edit/query filters, assign/unassign, review lintas step, dan pencegahan open redirect. Assertion redirect legacy pada LoginTest/LopPermissionTest/LopExcelImportTest diperbarui sesuai perilaku baru.
- Verifikasi: **295 tests / 2250 assertions** Laravel dan **19 frontend tests** lulus; Vite build, view:cache, Pint file implementasi, dan git diff --check lulus. Tidak ada migration/schema baru, write database operasional, reset LOP nyata, tes browser, commit/push, atau deployment pada sesi ini. Perubahan lokal sebelumnya dipertahankan. Test memakai SQLite memory; server produksi belum diverifikasi.

### 7.22 Reminder login admin dan Inbox Approval Saya — 13 Oktober 2026

- Menu Inbox dipindahkan tepat setelah Revenue Overview. Approval Saya berada di dalam Inbox, khusus ADMIN dengan permission approve_evidence; SUPER_ADMIN tetap tanpa Inbox sesuai aturan sebelumnya. Menu Approval Evidence umum, scope review, lifecycle, BOQ, dan Revenue tidak dipersempit atau diubah.
- GET lop/my-approvals memakai middleware auth/role ADMIN dan Gate view-my-approvals. MyApprovalService memilih activeAssignment.assigned_by = ID admin login lalu menerapkan LopVisibilityService. Creator LOP bukan penentu. Assignment replaced, dibatalkan, LOP arsip, scope luar, dan LOP aktif yang di-reassign oleh admin lain tidak masuk; completed dengan assignment aktif tetap tersedia untuk monitoring.
- Halaman rounded-md menyediakan lima KPI, tab Perlu tindakan/Pending/Siap diselesaikan/Perbaikan/Completed/Belum ada evidence/Semua, pencarian Incident/nama/teknisi/Branch/STO, filter empat program/status LOP, pagination 15 LOP, hitungan pending/approved/rejected, persentase approval/review, identitas teknisi/waktu assignment, dan tombol Review langsung per LOP. KPI merangkum seluruh penugasan admin, sedangkan tabel mengikuti filter.
- Perlu tindakan = LOP non-Completed dengan evidence pending, atau Waiting Approval dengan minimal satu evidence reviewable dan seluruhnya approved. Rejected-only menunggu teknisi dan tidak memicu reminder. Pending pada Completed yang inkonsisten tidak menjadi antrean kerja. Request_letter dan evidence soft-deleted dikecualikan dari seluruh hitungan. Waktu pending memakai updated_at evidence pending (termasuk upload ulang/reset); aktivitas terakhir memakai updated_at evidence reviewable. Bukan rekonstruksi histori approval lengkap.
- Login sukses admin menyimpan approval_reminder_user_id dalam session. View composer layouts.app mengonsumsinya sekali, memvalidasi identitas/role/permission lagi, lalu membaca data scoped terbaru. Popup native dialog + Alpine menampilkan jumlah pending/LOP siap selesai, prioritas pending terlama, Buka Approval Saya, Review LOP, dan Nanti/tutup. Refresh/navigasi tidak mengulang popup; login berikutnya mengecek ulang. Halaman intended yang memakai layout admin tetap mendapatkan reminder, bukan harus Dashboard. Session mismatch/role lain/no actionable work tidak menampilkan data. Logout/rotasi session/CSRF existing dipertahankan.
- EvidenceApprovalService.withSummaryCounts/onlyReviewable dipakai bersama menu umum dan service baru. summary tidak melakukan lazy query seluruh foto jika empat agregat tersedia. Query agregat/count dan eager loading dibatasi ke metadata/15 LOP; tidak memuat foto ke tabel atau melakukan query dalam loop Blade. Index assigned_by/status/lop dan evidence lop/status sudah disediakan migration runtime existing; tidak menambah migration.
- Link review meneruskan return_url ke Approval Saya dengan filter. LopReturnDestination mengizinkan route baru melalui whitelist internal; selesai review/kembali tidak mengalihkan ke daftar Approval umum secara paksa. Siap diselesaikan dan Completed membuka Step 5; pending memakai suggestedStep existing. Review/approve/reject tetap melalui policy dan endpoint existing, tidak auto-approve dari popup.
- MyApprovalTest mencakup ownership vs creator, histori/reassignment/cancel/archive, empat scope/multi-STO/akun tanpa scope, permission/non-admin/Super Admin, exclusion dokumen/soft-delete/Completed, KPI/progres, filter/pagination/return setelah completion, urutan menu, popup once-per-login/intended page/no work/account mismatch/failed login. **301 tests / 2359 assertions** Laravel dan **19 frontend tests** lulus; build Vite, view:cache, Pint file task, route registration, serta diff check lulus. Tidak menjalankan migration, login/import/review akun nyata, queue restart, commit/push, atau deployment. Popup/browser/device dan MySQL produksi belum diverifikasi.

### 7.23 Badge Approval Saya dan konfirmasi logout putih — 13 Oktober 2026

- Menu Approval Saya menampilkan badge blok merah dengan angka jumlah LOP non-Completed yang mempunyai evidence pending (bukan jumlah foto), termasuk 0 bila antrean kosong. Counter mengikuti assigner aktif, scope lokasi, permission, dan exclusion Request Letter/soft-delete existing. LOP seluruh evidence approved namun masih menunggu penyelesaian tidak masuk badge pending; tetap ada pada tab Siap Diselesaikan/reminder.
- MyApprovalService.pendingLopCount memakai COUNT LOP + EXISTS evidence untuk satu aggregate ringan tanpa mengambil seluruh foto. Composer layout memakai stats halaman Approval Saya jika tersedia, atau query count pada halaman admin lain; tidak menghitung ulang seluruh KPI untuk badge. Counter diperbarui ketika halaman/navigasi/redirect setelah review dimuat, tanpa polling/cache lintas akun.
- Komponen logout-confirmation digunakan topbar layout app dan halaman Profile Teknisi. Klik Logout hanya membuka dialog putih rounded-md dengan identitas akun, teks pastikan pekerjaan disimpan, Batal (autofocus), serta Ya, Logout. Pemicu/Batal/tutup memakai type button; hanya Ya memakai submit POST asli dengan token dan atribut session guard existing. Popup tetap putih dengan teks gelap pada dark mode sesuai permintaan user.
- Session guard hanya diperluas agar tombol data-auth-session-cancel ikut disabled selama pemeriksaan session, lalu pulih bila jaringan gagal. Escape/backdrop diblokir selama aria-busy; loading/error terlihat di dialog. Refresh token same-origin, pencegahan klik ganda, proteksi logout akun lain dari tab lama, dan larangan replay form pekerjaan tidak diubah. Tidak menambah endpoint atau pengecualian CSRF.
- Regression badge memastikan 2 foto pending pada satu LOP dihitung 1, angka turun setelah approve terakhir, dokumen/Completed/assignment replaced/admin lain tidak masuk, dan Super Admin tanpa badge. LogoutConfirmationTest memeriksa struktur DOM kelima role, desain putih, non-submit pemicu/Batal, identitas/token/URL form, serta satu tombol POST. Frontend guard menambah skenario disable/restore tombol batal ketika koneksi gagal.
- Verifikasi terakhir: **303 tests / 2492 assertions** Laravel, **20 frontend tests**, build Vite, view:cache, Pint file PHP task, dan diff check lulus. Tidak ada migration, write data operasional, logout akun nyata, commit/push, atau deployment. Render interaktif browser/device belum diuji; perubahan task sebelumnya tetap dipertahankan.

### 7.24 Password akun sendiri, scope topbar, pembatalan LOP, dan Active LOP — 13 Oktober 2026

- Seluruh role aktif mempunyai GET/PUT account/password (auth; update throttle 6/menit). Link nama/ikon kunci pada topbar dan tombol Profile Teknisi membuka halaman rounded-md dengan password saat ini, password baru, konfirmasi, show/hide, error, loading, success, dan Kembali. Form tidak menyimpan/menampilkan ulang password. Password baru minimal 8 karakter, berbeda dari lama, maksimal 72 byte untuk bcrypt, dan tanpa null byte. Tidak menerima target user lain.
- UpdateOwnPasswordRequest memvalidasi current_password:web. UserService.changeOwnPassword memuat ulang akun sendiri dengan row lock, mengecek status aktif/password saat ini lagi, menyimpan melalui cast hashed, merotasi remember_token, dan mencatat event password_changed pada user_histories tanpa password/hash. Role, lokasi, dan pivot Service Area tidak disentuh. Controller mempertahankan login perangkat ini, meregenerasi session ID/CSRF, dan menghapus cookie remember perangkat ini.
- Middleware web auth.session mengikat sesi pada fingerprint password Laravel. Sesi lain yang telah memiliki fingerprint lama ditolak pada request berikutnya; cookie remember lama juga tidak valid setelah token dirotasi. Sesi legacy sebelum middleware ini aktif yang belum menyimpan fingerprint mengambil fingerprint saat request pertama: jangan mengklaim seluruh sesi produksi lama telah langsung ditutup saat deployment. Jika dibutuhkan logout massal legacy, rencanakan invalidasi sesi tersendiri dengan izin operasional. Pemulihan 419 existing tetap berjalan dan form password tidak direplay otomatis.
- AccountScopeService menggunakan LopVisibilityService.label untuk ADMIN Area/Region/Branch/multi Service Area (termasuk fallback service_area_id legacy). Super Admin = seluruh wilayah; Teknisi = LOP penugasan; Manager/Approver = visibilitas monitoring/review sesuai izin existing. Topbar menampilkan nama, lalu scope akses di bawahnya; label panjang dipotong dengan tooltip. Label bukan aturan authorization baru.
- Klarifikasi eksplisit user: admin boleh menghapus LOP kapan saja jika dianggap cancel. Policy delete sekarang sama dengan update (SUPER_ADMIN semua; ADMIN dalam scope lokasi), tidak dibatasi Draft dan tidak memaknai status Rejected sebagai Cancelled. Tidak menambah enum/tabel/kolom baru. Popup native dialog meminta alasan min 5/max 1000 karakter, ketik Incident persis, checkbox konfirmasi, Batal/Ya Hapus, dan peringatan dampak Revenue. DeleteLopRequest mengharuskan verifikasi di backend, bukan hanya UI.
- LopService.delete memuat/lock parent fresh, authorize lagi, menutup assignment aktif sebagai replaced + unassigned_at, mencatat event deleted beserta alasan/aktor/status terakhir, lalu soft-delete qe_lops dalam satu transaction. Status terakhir, histories, BOQ snapshot/item, reservasi/qty_actual, serta evidence/file asli tetap tersimpan. LOP hilang dari daftar/query aktif dan nilai Completed tidak lagi masuk Revenue. Tidak menyediakan restore UI pada task ini; pembatalan memerlukan keputusan admin, bukan auto-delete ketika evidence rejected. Redirect kembali ke daftar/filter asal melalui LopReturnDestination.
- Data BOQ hanya memilih parent LOP aktif, sehingga soft-delete tidak menghasilkan lop null/error 500 untuk Super Admin. URL BOQ lama ditolak untuk edit/hapus; EvidencePolicy menolak akses/review/replace/delete jika parent diarsipkan. EvidenceService mengunci parent aktif sebelum upload, review, bulk approval, reset, replace, dan delete agar request stale tidak mengubah file/data arsip. Upload baru dibersihkan jika replace gagal; file asli tetap aman. Lock riil/concurrency MySQL belum diuji; test SQLite memverifikasi stale parent dan rollback/cleanup, bukan simulasi concurrent transaksi MySQL.
- Active LOP khusus ADMIN menerapkan created_by = ID login setelah scope lokasi dan sebelum clone query KPI/filter. LOP import yang dibuat admin login mengikuti aturan yang sama. History, Dashboard, Program, Master Data, Approval Evidence dan Approval Saya tidak dipersempit; Approval Saya tetap berdasarkan assigner aktif. Super Admin tetap tanpa Inbox.
- AccountPasswordTest (15 cases) dan LopCancellationTest (14 cases) memeriksa semua role/status, invalid password/CSRF/throttle, audit tanpa rahasia, rotasi session/remember token, scope/pivot tetap, verifikasi hapus, deny scope luar/role lain, soft-delete/child/file retention, akses lama, stale evidence actions, Revenue, serta creator-only list/KPI. Full suite **332 tests / 3006 assertions** dan **20 frontend tests** lulus; build Vite, view:cache, Pint file task dan diff check lulus. Tidak menjalankan migration, mengubah password/menghapus LOP nyata, restart worker, commit/push, atau deployment. Browser interaktif/mobile dan produksi belum diuji.

### 7.25 Matrix Program sesuai scope admin — 13 Oktober 2026

- Tab Program sekarang menampilkan level berikut: ADMIN Area = seluruh Region dalam Area akun, ADMIN Region = seluruh Branch di Region akun, ADMIN Branch = seluruh Service Area/STO di Branch akun, ADMIN Service Area = per Program dari gabungan STO yang diizinkan. ADMIN legacy branch_id tanpa admin_scope_type tetap memakai rincian STO. Wilayah master non-soft-deleted dengan 0 LOP tetap ditampilkan, termasuk Region/Branch yang belum mempunyai LOP; scope tidak diperluas menjadi seluruh wilayah aplikasi.
- DashboardProgramMatrixService menerima query yang sudah memakai LopVisibilityService dan filter dashboard. SQL mengagregasi hanya program/status/dimensi lokasi yang diperlukan, tanpa memuat seluruh LOP/evidence/BOQ dan tanpa query dalam loop Blade. FK diprioritaskan; fallback branch/sto lama tetap dipakai. Branch dengan LOP tanpa STO master menampilkan baris Service Area belum terdata, bukan membuangnya dari total. LOP soft-deleted tidak dihitung.
- DashboardMatrixMetrics digunakan bersama matrix baru dan matrix Region/Branch existing. Assign = status assigned; Unassign = status draft (label Unassigned), bukan seluruh riwayat assignment atau ketiadaan assignment historis. In Review = waiting_approval, Complete = completed. Total mencakup seluruh delapan status; persentase footer memakai Complete gabungan / Total gabungan, bukan rata-rata persentase baris. Tidak mengubah status atau lifecycle.
- Kolom Unassign berada tepat setelah Assign pada matrix admin dan accordion matrix Super Admin. Seluruh angka, termasuk Total Scope dan persentase, membuka modal rincian existing. Endpoint dashboard.matrix-lops menerima region_id/branch_id/service_area_id serta service_area_unmapped untuk kasus data STO belum lengkap; parameter ID dan string divalidasi. Filter diterapkan sebagai irisan setelah scope canonical, sehingga manipulasi ID wilayah luar tidak memperluas akses. Fallback lokasi legacy dipertahankan pada rincian.
- Pilih Program (empat enum + Semua Program) dipindahkan ke tab Program, untuk ADMIN dan SUPER_ADMIN. Form GET auto-submit dengan loading/fallback tombol Tampilkan mempertahankan region/branch/status Super Admin dan tanggal monitoring, lalu kembali ke tab Program. Filter program kini diterima untuk ADMIN juga; program pilihan memfilter seluruh dashboard (KPI, kedua matrix, Summary dan rincian). Badge program aktif di header memperjelas filter saat berpindah ke Status/Summary. Filter lokasi/status tambahan dashboard ADMIN tetap diabaikan seperti sebelumnya; scope tidak bergantung pada input browser.
- Tampilan matrix admin berupa tabel ringkas rounded-md, baris metadata lokasi, warna per metrik, sticky kolom identitas saat horizontal scroll mobile, footer Total Scope, dan empty state. Matrix Status LOP serta accordion Region/Branch/Program Super Admin tetap mengikuti desain existing. Tidak mengubah menu Program, Revenue, Inbox creator-only, Approval Saya per assigner, maupun approval umum.
- Regression baru DashboardProgramScopeMatrixTest (9 cases) mencakup empat scope, multi-STO lintas Branch, legacy branch/STO dan scope legacy, seluruh status, weighted percentage, empat dropdown program, lokasi nol, archived LOP, query ID/program luar/malformed, baris STO belum terdata, rincian Unassign, preservasi tanggal/filter, serta Super Admin. **341 tests / 3196 assertions** Laravel dan **20 frontend tests** lulus; view:cache, build Vite, Pint seluruh file PHP task, serta diff check lulus. Tidak ada migration/write data operasional, commit/push, tes browser, restart worker, atau deployment. MySQL produksi belum diuji.

### 7.26 Pemulihan build setelah konflik JavaScript — 14 Oktober 2026

- Commit `6b82630` menyimpan tujuh blok konflik `Updated upstream` / `Stashed changes` pada `resources/js/app.js`; build Vite dan sembilan test frontend LOP/BOQ gagal karena sintaks tersebut. Resolusi mempertahankan guard sesi, budget otomatis, lima segmen semua program, dukungan Relok Tiang, serta batas item BOQ dari implementasi terbaru, dengan format kode lokal tetap dipertahankan.
- Regression baru `tests/Frontend/app.test.mjs` memeriksa sintaks seluruh entry point sebagai modul JavaScript; gagal sebelum resolusi dan lulus sesudahnya. Verifikasi lokal: `npm run build`, seluruh **21 test frontend**, dan `git diff --check` lulus; tidak ada penanda konflik tersisa pada resources/tests.
- Tidak ada perubahan schema, migration, database operasional, atau deployment. Suite Laravel dan browser/device tidak dijalankan pada perbaikan sintaks ini.

### 7.27 Download Excel seluruh Data BOQ sesuai scope — 14 Oktober 2026

- Tombol Download Excel ditempatkan di sebelah Import BOQ pada `master-data/boqs`. GET `master-data/boqs/export` memakai middleware auth/role ADMIN,SUPER_ADMIN serta Gate `export-data-boqs` di controller. ADMIN mengikuti `LopVisibilityService` untuk Area/Region/Branch/multi-Service Area dan fallback branch/STO legacy; SUPER_ADMIN mencakup seluruh wilayah. Query browser tidak memperluas scope.
- File XLSX satu sheet Data BOQ mempunyai kolom Incident, Nama LOP, Designator, Item, Kategori, Reservasi, Aktual, Harga, Total. Seluruh LOP aktif dengan BOQ atau reservasi beritem diekspor tanpa batas pagination/pencarian. LOP/BOQ/item yang soft-deleted tidak diekspor. Setiap baris mengulang Incident/Nama LOP; teks disimpan eksplisit sebagai text untuk menjaga leading zero dan mencegah formula, sedangkan kuantitas/harga/total tetap numerik. Header dilengkapi filter dan freeze pane.
- `DataBoqExportService` memakai eager loading dan pembacaan LOP per 100 record melalui generator. `LopBoqProjectionService::masterDataLines()` menggunakan aturan proyeksi existing: BOQ tersimpan, termasuk import Recovery, mempertahankan snapshot uraian/kategori/harga; Reservasi/Aktual mengikuti reservasi bila tersedia, dengan fallback qty BOQ sebelum reservasi. Tanpa BOQ, paket wilayah/referensi serta pasangan jasa virtual mengikuti laporan aktual existing. Total = Aktual × Harga; aktual/harga/total yang belum tersedia kosong, angka 0 tetap 0. Ini ekspor data operasional semua status, bukan nilai Revenue yang hanya menghitung Completed. Lifecycle dan aturan Recovery tanpa Plan di laporan existing tidak berubah.
- Regression `DataBoqExportTest` memeriksa workbook XLSX nyata, header/tipe sel/leading zero/formula/zero/null, harga snapshot vs harga master terbaru, Recovery import tanpa reservasi, pasangan jasa/paket BALNUS, seluruh level scope dan legacy, manipulasi query, data lebih dari satu chunk, arsip/BOQ dihapus, dataset kosong, tombol UI, serta Gate tanpa middleware role. **347 tests / 3261 assertions** Laravel dan **21 frontend tests** lulus; Vite build, view:cache, Pint seluruh file PHP task, route registration, dan git diff --check lulus.
- Tidak ada migration/schema/write database operasional, queue restart, commit/push, atau deployment. Perbaikan JavaScript dari task sebelumnya dipertahankan. Pengujian memakai SQLite memory; browser/device, volume produksi, dan MySQL produksi belum diverifikasi.

### 7.28 Validasi incident sesuai program pada input dan Bulk LOP — 14 Oktober 2026

- Input LOP Baru dan Bulk Import LOP memeriksa incident yang diisi terhadap `ticket.jenis_tiket_2` pada koneksi eksternal read-only `mysql_dompis`. Recovery/Preventive wajib mengandung GAMAS tanpa membedakan huruf besar/kecil; Relok Tiang wajib mengandung frasa NON NUMBERING, dengan normalisasi spasi/underscore/hyphen. Relok Utilitas tidak mensyaratkan jenis tiket. Aturan canonical berada di `ProgramType::incidentTicketType()` dan `IncidentTicketValidationService`.
- Semua program dapat mengosongkan incident untuk menghasilkan INP otomatis. Input manual menggunakan tombol Generate Ticket Manual atau generate sebelum submit agar preview nama LOP memuat INP; backend `LopService::create()` juga menghasilkan INP setelah validasi lokasi ketika request dikirim tanpa JavaScript. INP manual tetap dikecualikan dari pemeriksaan jenis tiket eksternal; uniqueness, scope lokasi, histori, budget, dan lifecycle existing dipertahankan. Form edit dan Import BOQ tidak mendapat pemeriksaan jenis tiket baru pada task ini.
- `StoreLopRequest` memvalidasi jenis tiket di server setelah validasi field/lokasi, tanpa mempercayai ticket_summary atau jenis tiket kiriman client. Incident yang tidak ditemukan, jenisnya kosong/tidak sesuai, atau sumber tiket tidak tersedia ditolak dengan pesan incident. Nomor yang belum tersedia dapat dikosongkan/diganti INP oleh user; incident yang salah tidak diganti diam-diam. Relok Utilitas/INP/incident kosong tidak memerlukan sumber tiket untuk disimpan.
- Endpoint lookup menerima program_type opsional dan mengembalikan incident_validation; form menampilkan requirement/hasil, memeriksa ulang saat program berubah, memblokir submit hasil tidak valid, dan mengabaikan respons incident/program lama. Bulk import memprefetch jenis tiket per 500 incident unik, mencatat gagal per baris, serta mempertahankan progress, partial/failed, dan pesan duplikasi existing. Panduan halaman dan template Excel diperbarui tanpa mengubah header import.
- `IncidentProgramValidationTest` (7 tests) memakai SQLite terpisah sebagai sumber ticket: GAMAS/NON NUMBERING/variasi label, tipe kosong/salah/ticket hilang, spoofed summary, semua program dengan incident kosong, INP existing/duplikat, lookup per program, gangguan sumber, partial import dan prefetch. Fixture regression lama dilengkapi sumber tiket melalui `InteractsWithIncidentTickets`; bukan bypass validasi. Lima regression frontend baru mencakup pergantian program, respons lama, generate sebelum submit/nama LOP, retry/invalid, serta preservasi edit.
- Verifikasi lokal: **354 tests / 3345 assertions** Laravel dan **26 frontend tests** lulus; build Vite, view:cache, Pint file PHP task, serta git diff --check lulus. Tidak ada migration/schema/write database operasional, import nyata, queue restart, commit/push, atau deployment. Database tiket MySQL produksi dan browser/device belum diverifikasi; restart worker setelah deploy agar aturan import terbaru digunakan.

### 7.29 Kunci Simpan LOP sampai incident tervalidasi — 14 Oktober 2026

- Guard sebelumnya hanya memblokir incident_validation.valid = false, sehingga hasil kosong/belum dicek dapat mengaktifkan Simpan LOP. `lopForm.incidentValid` sekarang mensyaratkan hasil valid = true, ticket found, tanpa error, dan validationFor yang cocok dengan pasangan incident/program terkini untuk incident eksternal pada Recovery/Preventive/Relok Tiang. `canSubmitLop` dipakai bersama tombol dan submit handler; loading, duplikasi, program kosong, perubahan incident/program, payload tanpa validasi, dan gangguan jaringan tetap mengunci Simpan.
- Tombol create mempunyai atribut disabled sejak HTML awal sampai Alpine memeriksa state; halaman edit tetap mengikuti perilaku existing. Hasil lookup sebelum memilih program ditampilkan sebagai Validasi belum selesai, bukan sukses hijau. Pengecualian incident kosong yang menghasilkan INP sebelum submit, INP manual, dan Relok Utilitas dipertahankan.
- Regression memakai contoh user `INC47206386` dengan jenis REG: lookup tanpa program dapat menemukan tiket, tetapi memilih Recovery tetap menolak melalui UI dan backend. Empat test frontend baru memeriksa state tombol, validasi sesuai pasangan incident/program, request pending, respons tanpa validasi, kegagalan HTTP/jaringan, serta pengecualian INP/Utilitas. Ini fixture SQLite/DOM stub, bukan pemeriksaan incident produksi.
- Verifikasi: **45 test Laravel terkait / 220 assertions**, **30 test frontend**, build Vite, view:cache, Pint file PHP test, dan git diff --check lulus. Full suite Laravel terakhir tetap baseline pada 7.28; tidak dijalankan ulang pada perbaikan guard UI ini. Tidak ada migration/write data operasional/deployment; browser dan sumber tiket produksi belum diverifikasi. Asset lokal sudah dibuild ulang.

### 7.30 Pesan incident lebih ringkas — 14 Oktober 2026

- Form memakai satu petunjuk bahwa incident kosong menghasilkan INP; label requirement yang berulang dihapus. Pesan mismatch menjadi Gunakan tiket GAMAS untuk QE Preventive. Tiket ini berjenis REG. Peringatan pemetaan segmen disembunyikan selama jenis tiket tidak valid; saat diperlukan pesannya diringkas menjadi Segmen tiket ... belum terpetakan. Pilih segmen secara manual. Aturan validasi dan guard Simpan tidak berubah.
- Verifikasi: 21 test Laravel terkait / 130 assertions, build Vite, view:cache, Pint file PHP task, dan git diff --check lulus. Tidak ada perubahan schema, write database operasional, atau deployment.

### 7.31 Download evidence dari Master Data LOP — 14 Oktober 2026

- Kolom Aksi pada `master-data/lops` menampilkan ikon Download evidence (.zip) hanya untuk status Completed, memakai komponen table-action dengan tooltip/title/aria-label dan route `lop.evidence-archive` existing. Daftar mengikuti scope `LopVisibilityService`; endpoint tetap memeriksa policy view serta status Completed. Tidak ada query authorization tambahan untuk tombol baru dalam loop Blade.
- Unduhan memakai `EvidenceArchiveService` existing: arsip foto evidence per LOP dalam format ZIP; PDF tidak masuk arsip, dan LOP tanpa foto menghasilkan pesan 404 existing. Tidak mengubah file asli, layanan arsip, permission, status, atau schema.
- Dua regression tambahan pada `EvidenceArchiveTest` memeriksa tombol pada delapan status, akses Admin sesuai lokasi, ZIP dalam scope dan penolakan di luar scope. Verifikasi: **16 tests / 69 assertions** (`EvidenceArchiveTest`, `LopPermissionTest`), build Vite, view:cache, Pint file test, dan git diff --check lulus. Tidak ada migration/write data operasional/deployment; browser/produksi belum diuji. Worktree bersih pada awal task, HEAD `787f3e6`.

### 7.32 BOQ AISS per LOP Completed dari template — 14 Oktober 2026

Implementasi awal berbasis file di bagian ini telah diganti dengan workbook dari kode pada bagian 7.35; file template tidak lagi diperlukan saat ekspor.

- File `plans/Format AISS BOQ AWAL.xlsx` diperiksa: satu sheet bernama PELIMPAHAN, header ID IHLD, DESIGNATOR, URAIAN PEKERJAAN, SATUAN, QTY, HARGA, TOTAL, PAKET, STO, WITEL, NAMA_PROYEK, BRANCH. `AissBoqExportService` memuat template asli, mempertahankan header/style/lebar kolom, menghapus baris contoh, dan mengisi satu baris per designator tanpa mengubah file sumber.
- Kolom Aksi Master Data LOP menambahkan Download BOQ AISS (.xlsx) di sebelah Download Evidence, hanya untuk Completed. GET `master-data/lops/{qe_lop}/aiss-boq` memakai middleware auth/role ADMIN,SUPER_ADMIN, policy view yang menerapkan scope canonical, serta pemeriksaan Completed di backend; parent arsip tidak dapat dibuka. Unduhan AISS-{incident}-{nama_lop}.xlsx bersifat private/no-store. Spasi/karakter yang tidak aman pada nama LOP diganti tanda hubung; bila nama LOP kosong, filename tetap AISS-{incident}.xlsx.
- QTY memakai qty_actual dan TOTAL memakai qty_actual × harga. Ini asumsi awal yang disampaikan kepada user; pilihan QTY aktual/reservasi ditanyakan melalui pertanyaan opsional dan belum mendapat jawaban saat implementasi. ID IHLD berasal dari ihld_id, NAMA_PROYEK dari nama_lop, STO dari master workzone dengan fallback legacy. WITEL dan BRANCH sama-sama memakai nama Branch, mengikuti contoh template dan definisi Branch sebagai cabang/witel di aplikasi. Paket-5/Paket-10 ditulis sebagai 5/10; kode paket lain dipertahankan.
- `LopBoqProjectionService::masterData()` mengembalikan paket dan lines bersama; masterDataLines existing tetap menjadi wrapper dengan hasil yang sama. BOQ tersimpan mempertahankan snapshot uraian/satuan/harga, termasuk import Recovery; reservasi tanpa BOQ mengikuti paket regional/referensi dan jasa pasangan existing. Tidak mengubah lifecycle Recovery tanpa Plan pada laporan existing. Kuantitas/harga/total yang belum tersedia kosong; 0 tetap numerik 0. Identifier dan teks ditulis sebagai string untuk menjaga leading zero dan mencegah formula.
- `AissBoqExportTest` (6 tests) membaca XLSX hasil unduhan dan memeriksa header/sheet/style, harga snapshot vs master, actual vs reservasi, pasangan jasa Recovery, paket regional, null/zero/leading zero/formula, fallback lokasi legacy, semua scope admin, role lain, delapan status, arsip, dan dataset kosong tanpa baris contoh. Hash template diverifikasi tetap sama. **363 tests / 3466 assertions** Laravel dan **30 frontend tests** lulus; build Vite, view:cache, Pint file PHP task, route registration, dan git diff --check lulus.
- Tidak ada migration/schema/write database operasional, queue restart, commit/push, atau deployment. Perubahan Download Evidence dari task sebelumnya tetap dipertahankan. Penerimaan file oleh AISS, browser/device, dan data/MySQL produksi belum diverifikasi; field yang belum tersedia tidak diisi memakai nilai contoh.

### 7.33 Recovery/Preventive menerima jenis tiket UNKNOWN — 14 Oktober 2026

- Sesuai koreksi user, aturan 7.28 diperluas: Recovery dan Preventive menerima jenis tiket yang mengandung GAMAS atau persis UNKNOWN setelah trim/normalisasi huruf. UNKNOWN adalah nilai jenis_tiket_2 pada ticket yang ditemukan; jenis kosong, incident tidak ditemukan, sumber gagal, REG, dan label lain tetap ditolak. Relok Tiang tetap NON NUMBERING; pengecualian INP/incident kosong/Relok Utilitas dipertahankan.
- `IncidentTicketValidationService` memilih aturan menurut enum program, bukan teks label requirement. `ProgramType::incidentTicketType()` serta pesan/panduan bulk/template menyebut GAMAS atau UNKNOWN. Berlaku untuk lookup, simpan create, dan bulk import. Guard Simpan tetap membutuhkan hasil true untuk incident/program terkini; UNKNOWN tidak membuat tombol aktif sebelum dicek.
- Dua regression backend baru mencakup lookup/simpan Recovery/Preventive, huruf campuran/spasi, penolakan UNKNOWN untuk Relok Tiang/UNKNOWN CUSTOMER, serta partial/progress bulk import. Regression frontend memeriksa tombol UNKNOWN tetap terkunci sebelum hasil valid. **365 tests / 3489 assertions** Laravel dan **31 frontend tests** lulus; build Vite, view:cache, Pint file PHP task, dan git diff --check lulus.
- Tidak ada migration/schema/write database operasional, import nyata, queue restart, commit/push, atau deployment. Worktree bersih pada awal task; browser/device dan sumber tiket MySQL produksi belum diverifikasi. Restart worker setelah deploy agar validasi import terbaru digunakan.

### 7.34 ID IHLD pada kolom pertama ekspor Data BOQ — 14 Oktober 2026

- Download Excel pada `master-data/boqs` kini berurutan ID IHLD, Incident, Nama LOP, Designator, Item, Kategori, Reservasi, Aktual, Harga, Total. ID IHLD mengambil `qe_lops.ihld_id`, diulang pada setiap baris item LOP, dan dibiarkan kosong jika belum tersedia. Disimpan sebagai teks agar nol depan tidak hilang. Format angka, lebar kolom, wrap text, dan auto-filter digeser mengikuti sepuluh kolom; scope dan perhitungan existing dipertahankan.
- Regression `DataBoqExportTest` memeriksa ID pada seluruh item, null tanpa ID, leading zero, header/kolom numerik/filter yang bergeser, dan scope existing. **6 tests / 71 assertions**, Pint file PHP task, dan git diff --check lulus. Tidak ada perubahan UI/schema, migration, write database operasional, atau deployment; full suite dan browser/produksi tidak dijalankan ulang untuk penambahan kolom ini.

### 7.35 Ekspor AISS tanpa ketergantungan file template — 14 Oktober 2026

- User melaporkan endpoint AISS produksi gagal 500 karena `plans/Format AISS BOQ AWAL.xlsx` tidak ada atau tidak dapat dibaca. Regression HTTP dengan base path sementara tanpa file tersebut mereproduksi Reader Exception yang sama sebelum perbaikan. File lokal tersedia dan tracked; penyebab file tidak tersedia/permission di server belum diaudit langsung.
- `AissBoqExportService` sekarang membuat `Spreadsheet` baru, bukan memuat file. Nama sheet PELIMPAHAN, dua belas header AISS, font Aptos Narrow 11, serta lebar kolom A–E diambil dari template yang diperiksa. Kolom lainnya memakai layout default seperti sumber. Format tanggal yang tidak sesuai pada kolom SATUAN di template dinormalkan menjadi General; nilai satuan tetap string. File contoh asli dipertahankan sebagai referensi dan tidak dibutuhkan di produksi.
- Data, nama unduhan AISS-{incident}-{nama_lop}.xlsx, snapshot harga, actual/null/zero, paket regional, pasangan jasa, status Completed, role, serta scope lokasi tidak berubah. Test format memakai kontrak header/lebar/font literal, bukan membaca template. Regression baru membuktikan unduhan XLSX dapat dibaca kembali saat template tidak tersedia, tanpa memindahkan/menghapus file asli.
- **24 tests / 277 assertions** (`AissBoqExportTest` 7 tests, `DataBoqExportTest`, `LopBoqProjectionTest`, `LopPermissionTest`), Pint kedua file PHP task, dan git diff --check lulus. Full suite, build frontend, dan browser/AISS produksi tidak dijalankan ulang karena perubahan hanya pada pembentukan workbook, bukan service domain bersama atau UI.
- Tidak ada migration/schema/write database operasional, perubahan dependency, commit/push, atau deployment. Setelah kode baru di-deploy, jalankan `php artisan optimize:clear` dan uji endpoint AISS pada LOP Completed sesuai scope; tidak perlu mengunggah template atau menjalankan migration. Worktree bersih saat mulai pada HEAD e9687fc; perbaikan ini masih lokal hingga di-deploy.

### 7.36 Preventive menerima jenis tiket INFRACARE — 14 Oktober 2026

- Sesuai permintaan user, Preventive sekarang menerima jenis tiket yang mengandung GAMAS, persis UNKNOWN, atau persis INFRACARE setelah trim/normalisasi huruf. Recovery tetap GAMAS/UNKNOWN; Relok Tiang tetap NON NUMBERING. Pengecualian incident kosong/INP/Relok Utilitas dipertahankan. INFRACARE CUSTOMER tidak dianggap INFRACARE.
- `IncidentTicketValidationService` dan label `ProgramType::incidentTicketType()` diperbarui; aturan berlaku bersama pada lookup, create melalui StoreLopRequest, serta Bulk LOP. Panduan halaman/template bulk ikut diperbarui. Guard Simpan tetap membutuhkan hasil valid dari server untuk pasangan incident/program terkini.
- Tiga regression backend memeriksa lookup/simpan Preventive, variasi huruf/spasi, status Draft/Usulan dan histori create, penolakan Recovery/Relok Tiang/label mirip/spoofed jenis client, serta partial/progress bulk import. Regression frontend memastikan INFRACARE membuka Simpan setelah validasi Preventive dan kembali terkunci saat beralih ke Recovery. Test penerimaan INFRACARE gagal sebelum perbaikan dan lulus sesudahnya.
- Verifikasi: **369 tests / 3586 assertions** Laravel, **32 tests frontend**, build Vite, view:cache, Pint keempat file PHP task, dan git diff --check lulus. Worktree bersih saat mulai pada HEAD b1a1fc2. Tidak ada migration/schema/write data operasional, import nyata, queue restart, commit/push, atau deployment. Pengujian memakai SQLite memory dan DOM stub; browser/device serta sumber tiket MySQL produksi belum diverifikasi. Restart worker setelah deploy agar validasi bulk terbaru digunakan.

### 7.37 Verifikasi validasi ticket pada upload Bulk LOP — 14 Oktober 2026

- Audit pada HEAD `13a66f7` memastikan Bulk LOP sudah memakai `IncidentTicketValidationService` yang sama dengan create LOP. Tidak ada perubahan aturan runtime: Recovery menerima GAMAS/UNKNOWN, Preventive juga INFRACARE, Relok Tiang NON NUMBERING; incident kosong/INP/Relok Utilitas mengikuti pengecualian existing.
- Regression tambahan `IncidentProgramValidationTest` menguji upload HTTP CSV/XLSX -> dispatch `ProcessBulkLopImport` -> handle job. Excel tetap membaca sheet LOP saat Panduan aktif. Jenis ticket dibaca dari sumber server ketika job berjalan, termasuk perubahan GAMAS menjadi REG setelah upload; field jenis/summary kiriman client tidak memengaruhi keputusan. Baris tidak valid gagal tanpa LOP/histori, baris valid tetap dibuat dengan histori, status batch partial dan progress 100%.
- Perubahan hanya pada test dan handover; tidak ada migration/schema/write data operasional, import nyata, perubahan UI, restart worker, atau deployment. Verifikasi: **371 tests / 3636 assertions** Laravel, Pint file test, dan git diff --check lulus. Database ticket memakai SQLite sementara, Queue fake dengan eksekusi handle job; worker/MySQL produksi belum diuji. Worker perlu direstart sesudah deployment kode validasi terbaru agar tidak memakai class lama.

### 7.38 Kategori BOQ per program dan Revenue — 14 Oktober 2026

- Requirement terbaru menggantikan aturan pasangan jasa Recovery pada histori sebelumnya: Recovery, Preventive, dan Relok Tiang hanya MATERIAL; Relok Utilitas tetap MATERIAL + JASA. `ProgramType` menjadi sumber aturan bersama untuk import/save/proyeksi/nilai dan SQL Revenue.
- Import BOQ melewati baris JASA untuk program material-only dengan keterangan result yang jelas; qty/harga/duplikasi pada baris yang dikecualikan tidak menggagalkan material valid. Simpan manual menolak JASA pada program tersebut; dropdown editor hanya menawarkan material. Import legacy mengikuti aturan yang sama. Tidak menghapus atau mengubah massal snapshot lama.
- Data BOQ, laporan/review per LOP dan teknisi, ekspor seluruh Data BOQ serta AISS per LOP memfilter kategori yang diizinkan dan mempertahankan harga snapshot. Recovery tidak menampilkan tab Plan pada detail BOQ lama. Sisa Material tetap hanya material.
- Revenue pada semua scope tetap memakai filter/visibility existing, Recovery plan = 0, realisasi hanya Completed. Plan dihitung dari item snapshot yang diizinkan; realisasi memakai qty_actual × harga snapshot, ditambah material di luar snapshot dan jasa pasangan hanya untuk Utilitas. Qty actual terisi 0 tetap 0. Untuk data Completed legacy yang actual-nya null, fallback qty snapshot/reservasi existing dipertahankan.
- Header grand_total lama tidak dipercaya jika ada rincian item (termasuk saat semua item dikecualikan). BOQ lama tanpa rincian pada program material-only hanya memakai metadata total material yang eksplisit; total gabungan yang tidak bisa dipisahkan tidak dihitung dan memunculkan peringatan Revenue. Lengkapi/import ulang rincian setelah membuka status jika Completed, jangan mengasumsikan total gabungan = material.
- Tidak memerlukan migration, perubahan schema, backfill, atau write database operasional. Belum diuji di browser/MySQL/worker produksi. Setelah deploy, restart worker agar import memuat rule terbaru.
- Regression `BoqProgramCategoryRulesTest` mencakup empat program, harga snapshot vs master, actual 0, material tambahan, paket regional, scope Area/Region/Branch/Service Area, total legacy tak terklasifikasi, save/import, serta kedua ekspor. Test existing diperbarui sesuai requirement kategori baru.
- Verifikasi akhir: **386 tests / 3746 assertions** Laravel dan **32 tests frontend** lulus; Pint file PHP task, Vite build, Blade view:cache, dan git diff --check lulus. Perubahan rule ini belum di-commit saat handover diperbarui.

### 7.39 Capture Insera dan Logic Slot Port opsional — 14 Oktober 2026

- `TechnicianWorkflowService` dan `ProjectProgressService` tidak lagi mensyaratkan kategori insera/slot_port untuk kelengkapan Pra/After. Lokasi, foto kondisi awal, material tiba, Progress/After per designator, dan rekap actual tetap wajib. Progress dapat 100% tanpa dua capture tersebut.
- Enum `EvidenceCategory::isOptionalCapture()` menandai dua card opsional; label slot_port menjadi Capture Logic Slot Port. UI aktif/review teknisi memakai keterangan Screenshot Detail Slot Port OLT. ID kategori/step dan file lama tetap sama; admin approval/notifikasi mengikuti label enum baru.
- Uploader memberi badge Opsional, empty state netral, serta tombol Upload jika ada/Tidak Ada pada card kosong. Tidak Ada hanya menyembunyikan picker di sesi halaman, bukan menyimpan flag atau file kosong. Tanpa file tetap opsional sesudah reload. Antrean dengan file dipilih/processing/error/done memblokir Tidak Ada; foto tersimpan/ditolak tetap terlihat dan tidak dapat dihapus/dilewati lewat pilihan tersebut. Preview, hapus sebelum upload, multi-file, retry, dan reupload existing dipertahankan.
- File opsional yang diupload tetap masuk approval; pending/rejected tidak boleh diselesaikan sebagai Completed. Request Letter tetap satu-satunya kategori pendukung exempt existing. Tidak ada migration/schema atau perubahan database operasional/file nyata.
- Regression diperbarui dan direproduksi gagal sebelum perubahan: Pra tanpa Insera, After tanpa slot_port, dan alur submit. Full workflow ke Completed tanpa capture diuji pada empat program; test lain melindungi mandatory actual, visibility/reupload reject, serta completion guard. Frontend menguji pilihan Tidak Ada, proteksi antrean, dan kembali memilih foto.
- Verifikasi: **390 tests / 3841 assertions** Laravel dan **35 frontend tests** lulus; Pint file PHP task, Vite build, Blade view:cache, dan git diff --check lulus. Tidak ada commit/push/deployment atau uji browser/device/produksi. Worktree bersih pada awal task; rule BOQ sebelumnya sudah di-commit pada 4f76633.

### 7.40 Monitoring Incident per jenis tiket — 14 Oktober 2026

- Halaman baru GET `/incident-monitoring` (`incident-monitoring.index`) tersedia di sidebar setelah Dashboard untuk ADMIN/SUPER_ADMIN. `IncidentMonitoringRequest` memeriksa Gate `view-incident-monitoring` selain middleware role. ADMIN mengikuti `LopVisibilityService` untuk Area/Region/Branch/multi-Service Area, termasuk fallback Branch/STO legacy; Super Admin melihat seluruh wilayah. Halaman mencakup semua LOP aktif dalam scope, bukan hanya pembuat atau assigner akun. Arsip dan tiket sumber yang belum dipakai LOP tidak ditampilkan.
- `IncidentMonitoringService` menjadi sumber bersama empat KPI (Total LOP, Completed, Waiting Approval, Belum Ada IHLD), grafik batang horizontal jumlah LOP per jenis tiket, serta tabel/detail mobile. Filter Region/Branch/STO/program/jenis tiket/status, pencarian Incident/nama LOP/IHLD, dan hanya IHLD kosong berlaku pada seluruh angka serta rincian. Klik batang memasang filter jenis tiket, chip dapat dihapus, dan pagination 25 baris mempertahankan filter. Urutan rincian terbaru memakai created_at lalu ID; status berasal dari status_lop terkini, bukan status tiket eksternal atau snapshot historis.
- Jenis tiket diambil dari `TicketLookupService::lookupTypes()` pada sumber read-only mysql_dompis, sesudah pembatasan scope/filter lokal, per batch maksimum 500. Cache hanya hasil ditemukan dengan TTL 10 menit, dibaca/ditulis per batch; status/IHLD LOP tetap dibaca langsung. Waktu pengecekan terlama dalam hasil yang digunakan ditampilkan dalam WIB. INP menjadi Tiket Manual; UNKNOWN tetap kategori sumber yang sah. Incident kosong, tiket tidak ditemukan, jenis kosong, dan koneksi gagal menjadi kategori terpisah. Error tidak dicache; banner Coba kembali membaca ulang sumber. Cache tetap menggunakan store aplikasi existing, tidak memerlukan tabel baru.
- Blade/Tailwind/Alpine mengikuti token dan komponen existing, tanpa library chart tambahan: empat ringkasan netral, grafik satu warna, tabel sekitar 14 px, dan daftar mobile tanpa horizontal scroll halaman. Nomor Incident/IHLD dapat disalin melalui komponen copyable-value; nol depan dipertahankan dan kegagalan clipboard menyediakan feedback untuk salin manual. Menu aktif baru memakai warna dark mode yang tersedia agar kontras terbaca. Detail menuju route lop.show existing.
- `IncidentMonitoringTest` (13 tests / 155 assertions) mencakup authorization termasuk tanpa middleware role, seluruh scope/legacy, filter lokasi luar/bertentangan, kategori sumber/INP/UNKNOWN, arsip, semua status, filter gabungan/pencarian, pagination, cache kedaluwarsa, gangguan/retry, lookup sesudah scope, lebih dari satu batch, leading zero, dan escaping. Tiga test frontend baru memeriksa clipboard berhasil/gagal/klik ganda/retry.
- Verifikasi: full suite **403 tests / 3996 assertions** Laravel dan **38 tests frontend** lulus. Regression monitoring diulang setelah optimasi Cache::putMany. Build Vite, Blade view:cache, Pint file PHP task, dan git diff --check lulus. Pratinjau Chrome headless dengan fixture SQLite terpisah memeriksa desktop 1440 px, mobile 390 px, dark mode, buka/tutup filter, URL drill-down, salin IHLD, serta tidak ada overflow halaman/error JavaScript. Pratinjau bukan browser end-to-end terhadap data atau database produksi.
- Worktree bersih pada awal task di HEAD 1ed5291. Tidak ada migration/schema/write data operasional, queue restart, commit/push, atau deployment. MySQL lokal/produksi, sumber tiket produksi, dan volume produksi belum diuji untuk halaman baru ini. Perubahan fitur masih lokal dan perlu deployment/build asset sesuai prosedur existing.

### 7.41 Tiket Dompis yang belum memiliki LOP — 15 Oktober 2026

- Sidebar Monitoring Incident sekarang membuka GET `/incident-monitoring/tickets` (`incident-monitoring.tickets`), default pilihan Belum memiliki LOP. Tab Tiket Dompis membaca seluruh tiket sumber yang berjenis persis INFRACARE atau mengandung GAMAS setelah trim/normalisasi huruf. UNKNOWN, REG, NON NUMBERING tanpa GAMAS, dan INFRACARE CUSTOMER tidak termasuk. Tab Incident pada LOP mempertahankan halaman `/incident-monitoring` beserta INP, filter, scope, dan cache existing pada 7.40.
- `TicketCoverageRequest` mewarisi authorization Gate monitoring dan memvalidasi filter wilayah, kind (gamas/infracare), usage (all/without_lop/with_lop/archived), pencarian Incident/workzone, dan pagination. `TicketCoverageService` menyediakan empat KPI Total tiket/Belum memiliki LOP/Sudah memiliki LOP/LOP diarsipkan, grafik dua kelompok, dan rincian 25 tiket per halaman menurut ID sumber menurun. Ringkasan/grafik menghitung semua penggunaan sesuai wilayah/jenis/pencarian; pilihan penggunaan hanya menyaring daftar. Saat memilih daftar yang sudah dipakai, identitas LOP, IHLD, status_lop terkini, dan detail existing tersedia sesuai scope. Tampilan default belum dipakai memakai tabel empat kolom tanpa kolom LOP kosong; mobile memakai daftar ringkas.
- ADMIN hanya membaca tiket dengan workzone yang terpetakan ke Service Area dalam `LopVisibilityService::accessibleServiceAreaIds()`. Filter Region/Branch/STO menjadi irisan scope, FK region diprioritaskan, dan manipulasi filter wilayah tidak memperluas akses. Super Admin tanpa filter wilayah juga melihat tiket dengan workzone kosong/belum terpetakan; admin tidak melihatnya. Workzone dan incident dicocokkan setelah trim/normalisasi huruf. Detail LOP tetap melalui `LopVisibilityService::apply()`, termasuk fallback branch/STO legacy.
- Pencocokan pemakaian menggunakan metadata incident/ID/deleted_at seluruh `qe_lops` termasuk arsip. Tiket yang LOP-nya di luar scope tidak salah masuk daftar Belum memiliki LOP; halaman hanya menyatakan sudah dipakai/arsip tanpa nama, ID, IHLD, status, atau tautan LOP di luar akses. LOP arsip masuk kelompok tersendiri dan tidak mendapat link detail aktif, karena incident-nya tetap terikat unique constraint. Tidak membuat LOP otomatis atau mengubah aturan create/uniqueness/workflow.
- Sumber memakai koneksi read-only `mysql_dompis`. Kandidat jenis tiket dan workzone dibaca lewat DISTINCT pada index existing, dinormalisasi di aplikasi, lalu dipakai sebagai whereIn agar predicate sumber dapat menggunakan index. Jumlah penggunaan dan grafik dihitung bersama dalam satu query GROUP BY; rincian hanya SELECT id_ticket/incident/jenis_tiket_2/workzone dengan LIMIT 25. Tidak memuat seluruh tiket ke memori, tidak mengandalkan cross-database JOIN, dan tidak menambah cache/snapshot/tabel baru. Status pemakaian dibaca ulang setiap request. Jika sumber gagal, KPI menampilkan — dan pesan coba kembali, bukan nol atau daftar belum memiliki LOP yang palsu. Scope kosong ditolak sebelum query sumber.
- Audit MySQL read-only dimulai dari migrate:status dan db:table qe_lops/ticket; migration lokal tercatat Ran, sumber mempunyai unique incident, primary id_ticket, dan index jenis_tiket_2/workzone. Sumber sekitar 1,7 GB. Pemeriksaan service dengan aktor Super Admin di memori pada koneksi environment sesi ini membaca **53.311 tiket** (44.921 INFRACARE, 8.390 GAMAS), **53.310 belum memiliki LOP**, **1 sudah memiliki LOP**, **0 arsip**, dan hanya memuat 25 rincian. EXPLAIN memakai idx_jenis_tiket_2 serta PRIMARY untuk rincian. Satu sampel sesudah query awal sekitar 0,72 detik dengan 3 query sumber; ini bukan benchmark beban produksi atau bukti bahwa database QE environment lain mempunyai pemakaian yang sama.
- Regression `TicketCoverageTest` (13 tests / 139 assertions) mencakup eligibility jenis, pemisahan belum/sudah/arsip, seluruh scope dan multi-STO, workzone/incident ternormalisasi, LOP luar scope dan legacy, filter bertentangan/luar scope, workzone belum terpetakan, pagination/pencarian 0, parameter pemakaian besar, batas 25 rincian, outage/pemulihan, Gate tanpa middleware role, kompatibilitas monitoring LOP, escaping, dan validasi. Full suite **416 tests / 4135 assertions** Laravel dan **38 tests frontend** lulus. Sesudah penggabungan agregasi serta penyesuaian Blade, kedua regression monitoring lulus **26 tests / 294 assertions**. Build Vite, view:cache, Pint file PHP task, dan git diff --check lulus. Chrome headless dengan fixture SQLite terpisah memeriksa desktop 1440 px, mobile 390 px, dark mode, tabel belum/sudah dipakai, buka/tutup filter, URL jenis tiket, clipboard leading zero, serta tidak ada overflow halaman/error JavaScript.
- Perubahan lokal sebelumnya dipertahankan; HEAD tetap 1ed5291. Tidak ada migration/schema/write data operasional, akun nyata yang dibuat/login, queue restart, commit/push, atau deployment. Pembacaan MySQL di atas menggunakan koneksi yang dikonfigurasi environment sesi ini; browser pada data autentikasi nyata, environment produksi, dan pengujian beban/concurrency produksi belum diverifikasi.

### 7.42 Filter reported date dan penghapusan pilihan arsip — 15 Oktober 2026

- Tiket Dompis menambahkan filter opsional `date_from`/`date_to` berformat YYYY-MM-DD melalui dua input tanggal. Tanggal mulai/akhir inklusif, boleh hanya satu batas, dan tanpa tanggal tetap semua periode. `TicketCoverageRequest` menolak tanggal kalender tidak valid serta akhir sebelum awal; min tanggal akhir di browser mengikuti perubahan tanggal mulai. Rentang dipertahankan pada pagination, tautan jenis/penggunaan, dan pencarian; Reset mengosongkannya. Filter berlaku pada KPI, grafik, dan daftar. Tanggal laporan tiap tiket tampil di bawah Incident dalam format DD/MM/YYYY.
- Sumber canonical adalah `ticket.reported_date` (varchar), bukan `reported_date_dt`, created_at, atau tanggal LOP. Audit read-only sumber menemukan 52.809 nilai berformat ISO dan 502 berformat DD/MM/YYYY; contoh reported_date_dt mempunyai waktu berbeda dari nilai aslinya. `TicketCoverageService::reportedDateExpression()` mengekstrak hari ISO atau day-first slash dengan DATE/STR_TO_DATE pada MySQL, serta DATE/PRINTF/SUBSTR pada SQLite test. Tidak menggeser zona waktu. Tanggal kosong/tidak terbaca tetap terlihat bila tanpa filter, tetapi tidak masuk rentang tanggal yang dipilih.
- Kartu, pilihan, label, dan parameter usage arsip pada 7.41 dihapus. Tiga KPI sekarang Total tiket, Belum memiliki LOP, dan Sudah memiliki LOP; usage valid hanya all/without_lop/with_lop. Metadata pemakaian tetap mencakup LOP soft-deleted untuk menjaga incident yang terikat unique constraint tidak dianggap bebas. Pemakaian lama tersebut ikut Sudah memiliki LOP, tetapi detail hanya memuat LOP aktif dalam scope. Referensi yang tidak tersedia (arsip/di luar scope) tidak menampilkan nama, IHLD, status, atau link LOP; cukup Detail LOP tidak tersedia. Tidak menghapus record LOP maupun histori.
- Fixture sumber SQLite pada `InteractsWithIncidentTickets` menambahkan reported_date/reported_date_dt, tanpa migration aplikasi. `TicketCoverageTest` menjadi **17 tests / 187 assertions**: batas tengah malam/akhir hari, kedua format, day-first dengan digit tunggal, penggunaan kolom asli saat reported_date_dt berbeda hari, satu batas/reset/tanggal kosong atau tidak terbaca, validasi rentang, preservasi pagination, scope admin, penggabungan pemakaian arsip dan penghapusan UI/parameter arsip. Full suite **420 tests / 4183 assertions** lulus; Pint file PHP task, build Vite, view:cache, dan git diff --check lulus.
- Pemeriksaan read-only MySQL pada koneksi environment sesi ini untuk 16 Mei 2026 membaca **36 tiket**, seluruhnya INFRACARE/belum memiliki LOP, dengan 25 rincian per halaman yang menampilkan 16/05/2026. EXPLAIN tetap memakai index jenis tiket dan PRIMARY; satu sampel rentang historis sekitar 2,72 detik (bukan benchmark produksi). Chrome headless pada fixture terpisah memeriksa desktop/mobile, input tanggal dan min dinamis, hilangnya pilihan arsip, dark mode, clipboard, serta tidak ada overflow/error JavaScript. Belum ada tes browser pada data autentikasi nyata atau deployment produksi.
- Perubahan sebelumnya dipertahankan. Tidak ada migration/schema/write data operasional, penghapusan LOP nyata, queue restart, commit/push, atau deployment.

## 8. Status Masalah dan Pekerjaan Belum Selesai

### Prioritas tinggi

- Pastikan migration perluasan panjang uraian sudah dijalankan pada tiap environment setelah mendapat izin dan backup/tag siap.
- Migration Relok Tiang sudah terverifikasi pada lokal (batch 17, enum empat nilai); status server produksi belum diverifikasi.
- Jangan menambahkan kembali tabel `qe_boq_plans` atau kolom `file_hash`; keduanya tidak diperlukan oleh alur Import BOQ canonical.
- Pastikan worker queue produksi hidup. Batch berhenti di `queued` bila worker tidak berjalan.
- Deploy dan verifikasi perbaikan ekspor AISS tanpa template (7.35); keberhasilan di server belum dikonfirmasi.

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
390 tests passed
3841 assertions
```

Test memakai SQLite `:memory:` dan queue `sync`, sehingga tidak membuktikan konfigurasi MySQL, Nginx, storage permission, atau worker produksi.

Pemetaan test utama:

- BOQ AISS per Completed: `AissBoqExportTest` (7 tests, ekspor tanpa template/header/font/lebar/actual/snapshot/paket/role/scope/legacy/null/zero/arsip).
- Validasi incident/program: `IncidentProgramValidationTest` (15 tests, termasuk INFRACARE khusus Preventive dan upload CSV/XLSX melalui job); frontend `lop-form.test.mjs` (16 tests).
- Download Data BOQ: `DataBoqExportTest` (6 tests, XLSX/snapshot/aktual/null/zero/role/scope/legacy/pagination/arsip).
- LOP dan permission: `LopWorkflowTest`, `LopPermissionTest`, `LopManualInputTest`.
- Autentikasi/session: `LoginTest`, `SessionExpiryTest` (CSRF diaktifkan pada regression), `LogoutConfirmationTest`, `AccountPasswordTest`; frontend auth-session.test.mjs (11 passed), seluruh frontend (20 passed).
- Pembatalan/arsip LOP semua status dan Active LOP pembuat: `LopCancellationTest` (termasuk retensi BOQ/evidence/file, stale actions, dan exclusion Revenue).
- Scope admin/project status: `AdminScopeAndProjectStatusTest`.
- Teknisi mobile: `TechnicianMobileWorkflowTest`, `TechnicianProjectReviewTest`.
- Evidence/storage: `EvidenceWorkflowTest`, `EvidenceAsyncUploadTest`, `EvidencePermissionTest`.
- Import/BOQ: `BulkImportAndBoqTest`, `LopExcelImportTest`, `DesignatorCsvImportTest`.
- BOQ aktual dari reservasi: `ReservationBoqManagementTest`, `LopBoqProjectionTest`; frontend boq-editor.test.mjs (4 passed), seluruh frontend (19 passed).
- Navigasi asal dan reset BOQ: `LopReturnNavigationTest`, `ReservationBoqManagementTest`, serta regression redirect pada `LoginTest`, `LopPermissionTest`, `LopExcelImportTest`.
- Inbox Approval Saya/reminder login/badge: `MyApprovalTest` (7 tests), dengan full suite auth/session/scope/evidence tetap lulus.
- Bulk LOP terbaru: `BulkLopLatestRulesTest`, `LopProgramRulesTest`; frontend `node --test tests/Frontend/lop-form.test.mjs` (5 passed).
- Dashboard: `AdminDashboardTest`, `DashboardMonitoringTest`, `DashboardProgramScopeMatrixTest`.
- Revenue: `RevenueDashboardTest`.
- Reporting: `MaterialReportTest`.
- User management: `UserManagementWorkflowTest`, `UserPermissionTest`, `UserBulkImportTest`.

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
