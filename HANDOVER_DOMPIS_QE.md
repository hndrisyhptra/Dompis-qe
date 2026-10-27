# Paket Handover Dompis QE

Terakhir diaudit: **27 Oktober 2026**
Repository: `hndrisyhptra/dompis-qe`
Branch aktif: `collab/import-LOP-import-BOQ`
HEAD dasar saat audit terbaru: `3402d79` (`feat: sorting by service area in table ticket exclude`)

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
    -> keputusan BA Bongkar (Ya: form/foto/TTD teknisi/PDF; Tidak: selesai tahap 6)
    -> waiting approval (Admin mereview pekerjaan dan paket BA)
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

Pembaruan 19 Oktober 2026: Import AISS/Download Monitoring Ticket berada pada `e1ea9f0`, perbaikan form same-origin pada `e0cefe6`, dan Download Data LOP pada `e4e5601`. Designator khusus/capture/hapus evidence (7.47) sudah masuk `03d50b2`, Material Tiba per plan dan akses AISS (7.48) pada `085f6bb`, lokasi opsional (7.49) pada `6a591d3`, serta fallback lokasi APK dan hasil Bulk LOP (7.50) pada `0932d43`. Worktree bersih saat memulai perbaikan tujuan Detail LOP dari matrix dashboard dan Monitoring Ticket (7.51); task terbaru belum di-commit.

Pembaruan 20 Oktober 2026: empat commit hari ini masih **belum tercatat di `CLAUDE.md`** tetapi sudah didokumentasikan di bagian 7.52–7.55: TSEL/PREMAN pada validasi program (`37e23f7`), GAP revenue memakai aktual penuh (`09c03c5`), exclude tiket Dompis beserta migration `incident_excludes` (`d19dc7e`), dan monitoring matrix (pengelompokan STO, judul Monitoring Wilayah, `9e251d7`). Worktree bersih sebelum task filter tanggal Data BOQ. **Belum di-commit saat dokumen ini diperbarui:** filter tanggal reported date pada Master Data → BOQ (7.56) beserta migrasi kolom `qe_lops.reported_date`, kolom Reported Date pada tabel master dan Download Excel (7.57), perubahan judul assertion `AdminDashboardTest` mengikuti rename Anda, dan edit `monitoring.blade.php` milik user. Karena filter tanggal kini menyimpan tanggal di `qe_lops`, pekerjaan ini **menyentuh schema** (lihat 2.3) tetapi tidak mengubah workflow, import, atau source Android.

Pembaruan 26 Oktober 2026: BA Bongkar/penghapusan Warehouse berada pada `b1a4849`, dan batas reported date mulai September pada `ef9e131`. Worktree bersih sebelum task tabel Monitoring Exclude. Perubahan 7.66 belum di-commit: tab matrix regional, filter tanggal/bulan, drill-down detail, dan regression test. Tidak ada migration baru atau perubahan database operasional pada task ini.

Pembaruan optimasi query 26 Oktober 2026: worktree bersih pada `5f30637` sebelum task. Optimasi 7.69 masuk commit `92a7c96`: eager load BOQ pada daftar LOP, batch scope untuk render GET, dan batch lokasi/harga Data BOQ. Tidak ada perubahan schema, migration, atau database operasional.

Pembaruan 27 Oktober 2026: label scope global pada Monitoring Exclude dan tabel monitoring masuk commit `8978487`. Matriks per STO yang dikelompokkan berdasarkan nama Service Area untuk Admin Branch dan Service Area, drill-down STO, serta regression test masuk commit `3402d79`. Perubahan 7.72 belum di-commit: upload Capture Ticket Insera oleh admin dari form Input LOP Baru dan Master Data → Data LOP. Tidak ada perubahan schema, migration, atau database operasional.

### 2.3 Status migration lokal

Migration redundan untuk tabel `qe_boq_plans`, relasi `boq_plan_id`, dan kolom `file_hash` sudah dikeluarkan dari perubahan kerja. Migration `2026_10_02_000001_expand_boq_item_name_columns.php` tetap penting untuk uraian BOQ panjang.

Audit read-only 8 Oktober 2026 (`migrate:status`, `db:table qe_lops`) memastikan MySQL lokal masih memakai enum tiga program. Migration baru `2026_10_08_000001_add_relok_tiang_program_to_qe_lops.php` berstatus **Pending**, belum dijalankan. QE Relok Tiang memerlukan migration tersebut sebelum disimpan pada MySQL. Tidak ada backfill budget atau perubahan data operasional yang dijalankan.

Pembaruan audit read-only 12 Oktober 2026: migration Relok Tiang kini tercatat **Ran batch 17** dan `qe_lops.program_type` sudah memuat empat nilai. Eksekusi migration tersebut dilakukan di luar sesi Bulk Import User ini. Struktur `users` beserta empat level scope dan `qe_import_batches.type` (varchar) tersedia; Bulk Import User tidak membutuhkan migration tambahan. Sesi ini tidak mengimport akun nyata atau mengubah database operasional.

Status ini hanya berlaku untuk database lokal `dompis_qe`. Jangan menganggap server produksi sama; periksa `migrate:status` di server setelah pull.

15 Oktober 2026 — Import AISS menambah migration `2026_10_15_000001_add_status_aiss_to_qe_lops_table.php` (nullable varchar 255). Migration baru belum dijalankan pada sesi ini. Percobaan audit read-only `migrate:status` dan `db:table qe_lops` terhalang sandbox dengan Operation not permitted pada koneksi MySQL lokal; schema/ledger lokal terkini tidak terverifikasi. Halaman Import AISS memeriksa kolom dan menonaktifkan upload jika migration belum tersedia. Memerlukan izin eksplisit sebelum migration lokal/produksi.

16 Oktober 2026 — Migration `2026_10_16_000001_add_optional_capture_choices_to_qe_lops_table.php` menambah satu kolom JSON nullable untuk dua pilihan capture (bukan evidence dummy). Belum dijalankan pada database operasional; audit read-only migrate:status serta db:table qe_lops/qe_lop_histories tidak dapat terkoneksi dari environment sesi ini. Endpoint pilihan memberi validasi yang aman jika kolom belum tersedia. Rollback menolak menghapus kolom berisi pilihan. Minta izin terpisah sebelum menjalankan migration spesifik; jangan sekaligus menjalankan migration AISS yang belum disetujui.

20 Oktober 2026 — Migration `2026_10_20_000001_create_incident_excludes_table.php` membuat tabel `incident_excludes` (unik per `incident`, alasan, pembuat, timestamps) untuk fitur exclude tiket (7.54). **Sudah dijalankan pada MySQL lokal dan lulus suite; status produksi belum diverifikasi — wajib dijalankan sebelum deploy fitur exclude.** Rollback hanya menghapus tabel ini dan tidak memengaruhi tabel lain. PENTING: `TicketCoverageService::dashboard` memuat `IncidentExclude` pada baris sebelum blok `try/catch` sumber tiket, sehingga bila migration belum berjalan **seluruh halaman Monitoring Ticket akan error 500** — bukan sekadar tombol exclude yang hilang. Tidak ada data operasional yang perlu di-backfill.

20 Oktober 2026 — Migration `2026_10_20_000002_add_reported_date_to_qe_lops_table.php` menambah kolom `qe_lops.reported_date` (DATE nullable + index) sebagai salinan `ticket.reported_date` agar filter tanggal Master Data → BOQ berjalan lokal (7.56). **Sudah dijalankan pada MySQL lokal**, dilanjutkan `php artisan dompis:sync-reported-date` (12 LOP: 1 terisi, sisanya `INP*`/fixture yang memang tidak punya tiket). Murni kolom tambahan — tidak ada data lama yang diubah; rollback hanya drop index + kolom. **Status produksi belum diverifikasi dan wajib dijalankan sebelum deploy**, urutannya: migrasi dulu, lalu command sync. PENTING: tanpa kolom ini, pembuatan/edit LOP gagal pada INSERT (`unknown column reported_date`) dan filter tanggal di Data BOQ error — jadi keduanya tidak boleh dipisah.

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

Workflow mobile teknisi aktual terdiri dari enam langkah:

1. Reservasi material.
2. Evidence material tiba. Khusus QE Relok Utilitas wajib per item MATERIAL ber-volume pada BOQ Plan; program lain tetap global. Minimal satu foto non-rejected per item, boleh multiple. Tanpa BOQ Plan/material, teknisi diminta menghubungi admin; global lama tidak menjadi pengganti.
3. Evidence pra: foto kondisi awal wajib; Tag Lokasi dan Capture Ticket Insera opsional. Surat Permintaan opsional untuk Preventive/Relok. Tag Lokasi dapat memakai GPS, isian terpisah, atau tempel pasangan Latitude, Longitude lalu Simpan lokasi; tidak perlu membuat survey kosong untuk lanjut.
4. Evidence progress per designator reservasi.
5. Evidence after per designator dan `qty_actual` wajib; Capture Logic Slot Port (Screenshot Detail Slot Port OLT) opsional.
6. BA Bongkar: wajib menjawab Ya/Tidak. Ya memerlukan form material bongkaran, minimal satu foto, TTD teknisi dan PDF. Pengajuan hanya menuju Admin, yang memeriksa paket BA pada tahap 6 Review Evidence bersama pekerjaan lain. Tidak ada role/approval/TTD Warehouse dan tidak ada TTD Admin tambahan saat completion. Format PDF mengikuti acuan Karangdoro (7.64); penerima dan pihak yang mengetahui ditandatangani pada dokumen cetak, nama/NIK/TTD contoh tidak disalin.

LOP baru dapat diajukan bila semua checklist wajib lengkap. Rejected evidence dapat diganti pada record yang sama, bukan membuat evidence baru.

Saat Waiting Approval, Completed, atau Rejected, seluruh enam tahap tetap dapat dibuka dalam mode review. Form reservasi/lokasi/rekap tidak ditampilkan; evidence tetap dikelompokkan menurut tahap dan item. Tahap dengan reject memiliki penanda merah, dan default halaman membuka reject pertama. Penggantian evidence biasa tetap tersedia pada project Rejected melalui policy existing; perbaikan BA dilakukan lewat form tahap 6 setelah Mulai perbaikan.

### 4.5 BOQ dan reservasi material

- BOQ satu per LOP di `qe_boqs`, memiliki paket dan item snapshot di `qe_boq_items`.
- BOQ dapat dibuat lewat import atau dikelola dari Master Data.
- Item BOQ dapat dicari, ditambah, diedit, dan dihapus selama aturan status mengizinkan.
- Card BOQ Aktual dari Reservasi Teknisi mempunyai tombol Edit BOQ Aktual per LOP. Admin sesuai scope/Super Admin dapat menambah material, menghapus item yang belum punya evidence/rekap, dan mengubah qty reservasi melalui modal dengan search designator dan konfirmasi. Qty actual tetap milik rekap teknisi; tidak membuat BOQ Plan palsu atau mengganti harga paket.
- Waiting Approval boleh diedit oleh admin; jika menambah material baru, LOP kembali Progress agar evidence/rekap item baru dilengkapi dan diajukan ulang. Completed menampilkan ikon gembok ber-tooltip Terkunci serta tombol reset terkonfirmasi. Reset membuka status Progress tanpa menghapus evidence, keputusan review, penugasan, qty_actual, atau snapshot BOQ; nilai realisasi keluar sementara sampai LOP Completed lagi.
- Saat assign, item material dan tiga jasa khusus dari BOQ mengisi `qe_material_reservation_items.qty` sebagai kuantitas rencana.
- Teknisi mengisi `qe_material_reservation_items.qty_actual` sebagai kuantitas realisasi; tidak ada tabel BOQ Plan terpisah.
- Jika BOQ tidak ada, reservasi teknisi menjadi fallback Data BOQ dan perhitungan nilai sesuai aturan service.
- Detail LOP dan Detail Data BOQ memisahkan tiga proyeksi: BOQ Plan dari snapshot import, BOQ Actual dari `qty_actual`, dan Sisa Material dari selisih reservasi dengan aktual.
- QE Recovery tidak memiliki BOQ Plan. Recovery, Preventive, dan Relok Tiang menghitung/menampilkan MATERIAL ditambah pengecualian J-CO-OF, J-Coring, J-OS-SM-1; Relok Utilitas tetap boleh seluruh MATERIAL/JASA. User mengonfirmasi tiga pengecualian ikut BOQ/Revenue pada semua program. Aturan canonical berada pada `ReservedDesignatorRules` dan `ProgramType::allowsBoqType(type, code)`; includesBoqServices tetap hanya Utilitas untuk pasangan jasa biasa.
- Pasangan `JASA` otomatis berdasarkan kode `M-...` -> `J-...` hanya berlaku untuk fallback reservasi/tambahan material Relok Utilitas. Snapshot import tetap memakai item dan harga tersimpan.
- Pasangan jasa biasa virtual tidak disimpan ke reservasi. Tiga jasa khusus justru menjadi item reservasi eksplisit dengan evidence Progress/After sendiri dan qty_actual harus sama qty reservasi/BOQ. Jangan menghitung ketiganya lagi sebagai pasangan otomatis dari material dengan kode dasar sama. BOQ snapshot dan harga tersimpan tidak diubah massal.
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
- Import Data AISS di Bulk Import Data memperbarui `qe_lops.status_aiss` pada LOP existing tanpa mengubah status Dompis, identitas, workflow, BOQ, atau Revenue. Format ekspor: B = ID IHLD, H = NAMA PROGRAM/Nama LOP, L = STATUS (bukan O), W = INCIDENT. XLSX/XLS maksimal 10 MB/10.000 baris, header dalam 20 baris awal; sheet dicari berdasarkan header, bukan active sheet.
- Pencocokan AISS berurutan ID IHLD → Nama LOP → Incident, exact setelah normalisasi kapitalisasi/spasi, tanpa fuzzy/pengambilan incident dari uraian. Kecocokan pertama menghentikan pencarian; kandidat ambigu, arsip, scope luar, status kosong/terlalu panjang, formula, atau tidak cocok dilewati dengan alasan. Metadata global minimal termasuk arsip hanya untuk deteksi kandidat; detail/nama LOP luar scope tidak ditampilkan. Baris valid pertama per LOP menang; unggah ulang dengan status sama tidak menulis histori tambahan.
- Import AISS memakai batch type `aiss`, job `ProcessAissImport`, progress per 25 baris, history lima upload dan hasil berhasil/gagal/dilewati. File contoh user dibaca read-only: Sheet1, 2.292 baris, tanpa formula pada empat kolom relevan; tidak diimport ke database operasional. Tidak ada template baru karena sumbernya ekspor AISS asli.
- Gate `import-aiss` kini khusus SUPER_ADMIN aktif atau ADMIN aktif dengan `admin_scope_type=area`. Sidebar, halaman/form upload, hasil/status batch dan worker memakai Gate sama. Admin Region/Branch/Service Area/tanpa scope ditolak, termasuk batch miliknya yang terlanjur queued. Admin Area tetap hanya memperbarui LOP dalam scope lokasinya melalui LopVisibilityService; bukan akses seluruh area lain.

### 4.7 Evidence dan approval

- Satu tabel `qe_evidences` untuk seluruh kategori evidence.
- Material Tiba Relok Utilitas menyimpan `designator_id` existing per material BOQ Plan. `MaterialArrivalRules` menjadi sumber aturan untuk upload, checklist workflow/progress dan Complete Review. Item jasa (termasuk tiga jasa khusus), item volume nol, tambahan hanya di reservasi, serta global lama tidak menggantikan syarat per material plan. Semua evidence reviewable tetap wajib approved untuk Completed.
- Upload teknisi bersifat async per file, maksimal dua upload paralel, memiliki progress dan retry.
- File asli dikompresi tanpa mengganti nama, MIME, atau ekstensi; thumbnail terpisah boleh WebP.
- File ditampilkan melalui route terautentikasi, bukan mengandalkan URL storage publik langsung.
- Preview dan hapus sebelum upload tersedia.
- Capture Ticket Insera dan Capture Logic Slot Port tetap opsional pada semua program. Klik Tidak Ada sekarang menyimpan deklarasi pada `qe_lops.optional_capture_choices` dan histori, dengan actor/waktu per kategori. Upload jika ada membatalkannya setelah respons sukses; upload foto baru juga menghapus deklarasi secara transactional. Tidak membuat evidence dummy atau mewajibkan deklarasi untuk melewati step. Foto yang ada tetap wajib review dan memblokir pilihan Tidak Ada sampai dihapus.
- QE Preventive/Relok mempunyai dokumen pendukung multi-file `request_letter` (PDF/foto). Admin mengelolanya dari aksi LOP; teknisi melihatnya pada Step 3 dan dapat upload sendiri hanya bila admin belum menyediakan file.
- Surat Permintaan tetap disimpan di `qe_evidences`, tetapi dikecualikan dari progres dan antrean approval karena bukan evidence hasil pekerjaan.
- Galeri menampilkan pending, approved, rejected, serta alasan reject.
- Approval dikelompokkan per LOP, step, evidence group, dan item designator.
- Reviewer dapat approve, reject, reset, dan bulk approve per accordion/group.
- Teknisi dengan assignment aktif boleh menghapus foto miliknya berstatus pending/rejected, dengan konfirmasi Ya/Tidak. Approved tetap terkunci sampai admin reset review. Hapus pada Waiting Approval/Rejected membuka Progress dan reservasi draft; foto lain/keputusan review tetap disimpan. Reset review pada Completed juga membuka Progress dan mengeluarkan nilai realisasi sementara sampai proses selesai ulang. Parent dan evidence dibaca fresh dengan row lock/authorization; file dihapus setelah commit, bukan sebelum transaksi dipastikan berhasil. Admin delete tetap dibatasi scope lokasi.
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
- `app/Http/Controllers/DataLopController.php`
- `app/Http/Requests/DataLopFilterRequest.php`
- `app/Services/DataLopService.php`
- `app/Exports/DataLopExport.php`
- `app/Policies/QeLopPolicy.php`
- `resources/views/lop/index.blade.php`
- `resources/views/lop/detail.blade.php`
- `resources/views/program/show.blade.php`

### 5.2 Teknisi dan progress

- `app/Services/TechnicianWorkflowService.php`
- `app/Services/TechnicianDashboardService.php`
- `app/Services/ProjectProgressService.php`
- `app/Support/ReservedDesignatorRules.php`
- `app/Http/Requests/StoreOptionalCaptureChoiceRequest.php`
- `app/Http/Controllers/TechnicianController.php`
- `app/Http/Controllers/TechnicianWorkflowController.php`
- `resources/views/layouts/technician.blade.php`
- `resources/views/technician/project.blade.php`
- `resources/views/technician/boq-review.blade.php`
- `resources/views/technician/partials/boq-review.blade.php`
- `resources/views/technician/partials/location-form.blade.php`
- `resources/js/technician-location.js`
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
- `app/Services/AissImportService.php`
- `app/Jobs/ProcessAissImport.php`
- `app/Http/Controllers/AissImportController.php`
- `app/Http/Requests/StoreAissImportRequest.php`
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
- `app/Http/Controllers/DataBoqController.php`
- `app/Http/Requests/DataBoqFilterRequest.php`
- `app/Services/DataBoqExportService.php`
- `app/Exports/DataBoqExport.php`
- `app/Support/TicketReportedDate.php`
- `app/Services/IncidentReportedDateService.php`
- `app/Console/Commands/SyncReportedDate.php`

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
- `resources/views/dashboard/partials/program-matrix-row.blade.php`
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
15. BOQ Plan/Actual, ekspor, dan Revenue untuk Recovery/Preventive/Relok Tiang menerima MATERIAL serta tiga jasa khusus J-CO-OF/J-Coring/J-OS-SM-1. Relok Utilitas boleh seluruh MATERIAL/JASA. Tiga jasa khusus harus eksplisit di reservasi, dengan actual = qty reservasi dan evidence Progress/After sendiri; tidak diduplikasi sebagai pasangan virtual. Data jasa historis tidak dihapus massal.
16. Capture Insera dan Capture Logic Slot Port tidak wajib untuk kelengkapan Pra/After. Jika diupload, keduanya tetap evidence reviewable, bukan dokumen exempt seperti Surat Permintaan.
17. Tag Lokasi opsional untuk seluruh program; tanpa `qe_surveys` teknisi tetap dapat lanjut/submit/Completed jika evidence dan qty wajib lengkap. Simpan lokasi secara eksplisit tetap memerlukan pasangan koordinat numerik valid dan assignment/authorization existing. Jangan menyimpan survey dummy atau mengubah lokasi lama saat user melewatinya.

Pembaruan 7.50: request lokasi yang kedua koordinatnya kosong kini diterima sebagai skip/no-op tanpa survey baru atau menghapus koordinat lama. Jika salah satu diisi, pasangan/rentang tetap wajib valid. Tempel pasangan diparse di server juga; Lanjut Progress memakai form terpisah, tidak bergantung GPS/Alpine atau validasi form lokasi.

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
- Sumber canonical adalah `ticket.reported_date` (varchar), bukan `reported_date_dt`, created_at, atau tanggal LOP. Audit read-only sumber menemukan 52.809 nilai berformat ISO dan 502 berformat DD/MM/YYYY; contoh reported_date_dt mempunyai waktu berbeda dari nilai aslinya. `App\Support\TicketReportedDate::expression()` — semula metode privat `TicketCoverageService::reportedDateExpression()`, dipindah pada 20 Oktober 2026 (7.56) agar dipakai bersama dengan filter tanggal Data BOQ — mengekstrak hari ISO atau day-first slash dengan DATE/STR_TO_DATE pada MySQL, serta DATE/PRINTF/SUBSTR pada SQLite test. Tidak menggeser zona waktu. Tanggal kosong/tidak terbaca tetap terlihat bila tanpa filter, tetapi tidak masuk rentang tanggal yang dipilih.
- Kartu, pilihan, label, dan parameter usage arsip pada 7.41 dihapus. Tiga KPI sekarang Total tiket, Belum memiliki LOP, dan Sudah memiliki LOP; usage valid hanya all/without_lop/with_lop. Metadata pemakaian tetap mencakup LOP soft-deleted untuk menjaga incident yang terikat unique constraint tidak dianggap bebas. Pemakaian lama tersebut ikut Sudah memiliki LOP, tetapi detail hanya memuat LOP aktif dalam scope. Referensi yang tidak tersedia (arsip/di luar scope) tidak menampilkan nama, IHLD, status, atau link LOP; cukup Detail LOP tidak tersedia. Tidak menghapus record LOP maupun histori.
- Fixture sumber SQLite pada `InteractsWithIncidentTickets` menambahkan reported_date/reported_date_dt, tanpa migration aplikasi. `TicketCoverageTest` menjadi **17 tests / 187 assertions**: batas tengah malam/akhir hari, kedua format, day-first dengan digit tunggal, penggunaan kolom asli saat reported_date_dt berbeda hari, satu batas/reset/tanggal kosong atau tidak terbaca, validasi rentang, preservasi pagination, scope admin, penggabungan pemakaian arsip dan penghapusan UI/parameter arsip. Full suite **420 tests / 4183 assertions** lulus; Pint file PHP task, build Vite, view:cache, dan git diff --check lulus.
- Pemeriksaan read-only MySQL pada koneksi environment sesi ini untuk 16 Mei 2026 membaca **36 tiket**, seluruhnya INFRACARE/belum memiliki LOP, dengan 25 rincian per halaman yang menampilkan 16/05/2026. EXPLAIN tetap memakai index jenis tiket dan PRIMARY; satu sampel rentang historis sekitar 2,72 detik (bukan benchmark produksi). Chrome headless pada fixture terpisah memeriksa desktop/mobile, input tanggal dan min dinamis, hilangnya pilihan arsip, dark mode, clipboard, serta tidak ada overflow/error JavaScript. Belum ada tes browser pada data autentikasi nyata atau deployment produksi.
- Perubahan sebelumnya dipertahankan. Tidak ada migration/schema/write data operasional, penghapusan LOP nyata, queue restart, commit/push, atau deployment.

### 7.43 Import Status AISS ke LOP — 15 Oktober 2026

- Menu Bulk Import Data → Import Data AISS memakai Gate import-aiss untuk ADMIN/SUPER_ADMIN aktif, middleware role/auth, StoreAissImportRequest, dan job database existing. Worker membaca ulang role/keaktifan/scope pengunggah; hasil batch hanya pemilik atau Super Admin. Scope Area/Region/Branch/multi-Service Area dan fallback lokasi legacy tetap melalui LopVisibilityService.
- Migration additive menambah `qe_lops.status_aiss` nullable string(255); rollback menolak menghapus kolom berisi data. Data LOP dan semua halaman Program menampilkan Status Dompis lalu Status AISS. Kolom kosong menggunakan —; nilai teks di-escape dan tidak dipetakan ke lifecycle Dompis. Model menyertakan status_aiss; tidak ada tabel AISS/LOP baru.
- Parser memeriksa format workbook asli XLSX/XLS dan header B/H/L/W, mencari sheet meskipun Panduan aktif, serta membaca empat nilai mentah tanpa evaluasi formula atau mengambil komentar/nama pengelola ke payload hasil. Status disimpan sesuai teks yang diupload (trim), tidak mengganti ID IHLD/nama/incident/program. Status Program kolom O, RAB, dan timestamp file tidak ikut diimport; gunakan ekspor terbaru karena import berikutnya mengganti Status AISS.
- Identitas LOP dibaca per 500 record; indeks memori hanya menyimpan key dari file dengan maksimal dua kandidat per key untuk mendeteksi ambiguity. IHLD paling prioritas; jika cocok tetapi ambigu/arsip/di luar akses, tidak mencoba key berprioritas lebih rendah yang bisa mengubah LOP lain. Nama/kunci dinormalisasi trim, kapitalisasi, dan spasi; tidak membuang prefix atau nol depan pada teks.
- Setiap baris diproses dalam transaction dengan lock LOP dan recheck identitas/scope. LopService::updateAissStatus mengubah hanya status_aiss/updated_at via update terarah agar normalisasi model tidak mengubah status_project/segment legacy. Histori aiss_status_updated menyimpan nilai sebelum/sesudah, batch UUID, tahap match, dan status_lop sebelum/sesudah tetap sama. Histori dan hasil per baris atomic bersama perubahan; Summary dashboard mengenali label Update Status AISS.
- Queue memakai satu attempt, timeout 600 detik, failOnTimeout, lock 660 detik, dan batch finished tidak diproses ulang. Status batch failed ditulis jika dispatch/proses/failure callback gagal; pesan SQL/exception mentah tidak ditampilkan. Hasil yang telah committed tetap durable jika job terhenti; tidak mereplay batch lama otomatis. Hasil generic dan lima history memakai counter skip; progress JSON/JS tetap kompatibel dengan import lain. File sumber tetap privat di disk local sesuai pola import existing.
- Verifikasi: **440 tests / 4322 assertions** Laravel dan **41 frontend tests** lulus; 20 regression AISS mencakup tiga tahap/prioritas, ambiguity, duplikasi, empat scope/multi-STO/legacy, arsip, role/keaktifan/pemilik, Completed/snapshot, formula/blank/panjang/header/limit/corrupt, rollback transaksi/migration, timeout/idempotensi, hasil/progress, serta empat tabel program. Pint file PHP task, Vite build, view:cache, route registration, dan git diff --check lulus. File user berhasil diparse oleh service aplikasi tanpa import nyata.
- Worktree bersih pada awal task di a86ed89. Tidak menjalankan migration pada database operasional, tidak mengimport file nyata, restart worker, commit/push, atau deploy. MySQL lokal tidak dapat diaudit karena pembatasan koneksi sandbox; concurrency MySQL, upload browser/device dan produksi belum diuji. Setelah mendapat izin dan backup/schema diperiksa, jalankan migration spesifik sebelum upload; deploy asset/cache dan restart worker sesuai prosedur existing.

### 7.44 Download Monitoring Incident dan filter bulan — 16 Oktober 2026

- Monitoring Incident → Tiket Dompis menyediakan dropdown Download Excel untuk Total Tiket, Belum Memiliki LOP, dan Sudah Memiliki LOP. Setiap XLSX berisi metadata filter/scope, ketiga angka ringkasan, serta seluruh rincian kategori terpilih, bukan hanya halaman tabel. Ringkasan selalu mencakup semua kategori dalam filter yang sama; kategori unduhan hanya membatasi daftar.
- Filter bulan laporan `YYYY-MM` ditambahkan pada halaman dan unduhan. Bulan serta rentang tanggal memakai `ticket.reported_date` existing (bukan reported_date_dt), dan berlaku sebagai irisan jika keduanya diisi. Tombol download mengirim isian form terkini tanpa harus Terapkan; download_usage mengesampingkan usage daftar. Filter lokasi/jenis/pencarian dan month tetap terbawa pada navigasi/pagination.
- Endpoint GET `/incident-monitoring/tickets/download` memakai middleware auth/role dan `TicketCoverageDownloadRequest` yang mewarisi Gate `view-incident-monitoring`. Controller memanfaatkan `TicketCoverageService` yang sama untuk predikat jenis tiket, lokasi, occupancy, dan presenter detail. Scope Area/Region/Branch/multi-STO serta fallback legacy tetap mengikuti LopVisibilityService. Incident terpakai di luar scope/arsip tetap diklasifikasikan Sudah Memiliki LOP, tetapi identitas/detail LOP tidak diekspos.
- Rincian sumber dibaca per 500 record dengan keyset id_ticket menurun dan lookup LOP per chunk, tanpa query per baris atau pembatasan pagination. `TicketCoverageExport` memakai generator Maatwebsite/PhpSpreadsheet; workbook akhir tetap membutuhkan memori sesuai ukuran file dan belum dibenchmark pada volume produksi. Koneksi sumber gagal membatalkan download dengan 503; tidak menghasilkan ringkasan nol palsu. Respons XLSX private/no-store.
- Nomor incident/IHLD dan seluruh teks ditulis sebagai literal (mempertahankan nol depan dan mencegah formula injection). Tanggal dan angka ringkasan memakai tipe numerik Excel; header memiliki autofilter/freeze pane, nama LOP wrap dan tinggi baris otomatis. Kolom: Incident, Tanggal Laporan, Jenis Tiket, STO, Branch, Pemakaian LOP, ID IHLD, Nama LOP, Status Dompis, Keterangan.
- Verifikasi: **449 tests / 4531 assertions** Laravel dan **41 frontend tests** lulus. TicketCoverageTest kini 26 tests mencakup pembacaan XLSX nyata, tiga kategori, semua scope/legacy/arsip, nol depan/formula/date/count, gabungan filter, leap day/tahun baru, data melebihi 500 record, halaman kedua, form download, validasi, role/Gate, outage, dan hasil kosong. Full suite diulang sendiri setelah percobaan awal berbenturan dengan proses view:cache yang berjalan bersamaan; seluruhnya lulus. Pint file PHP task, Vite build, view:cache, route registration, serta git diff --check lulus. Tampilan Excel/browser dan MySQL/volume produksi belum diverifikasi.
- Tidak menambah schema atau menjalankan migration/database write operasional, import nyata, restart worker, commit/push, maupun deploy. Perubahan Import AISS 7.43 tetap dipertahankan di worktree; migration AISS tetap belum dijalankan dan memerlukan izin terpisah.

### 7.45 Download same-origin dan hapus filter bulan — 16 Oktober 2026

- Melanjutkan commit `e1ea9f0`, form filter Tiket Dompis dan tiga tombol Download Excel kini memakai `route(..., [], false)`. URL relatif mempertahankan protokol/domain halaman browser, tanpa membawa HTTP hasil deteksi backend ke form HTTPS. Tidak mengubah trusted proxies, APP_URL, sertifikat, redirect server, atau pemaksaan HTTPS global; konfigurasi server tetap perlu diperiksa bila ada redirect HTTP lanjutan.
- Filter Bulan laporan dihapus dari Blade, validasi, service query, metadata filter, dan nama file download. Parameter month dari bookmark lama diabaikan serta tidak diteruskan ke pagination. Rentang date_from/date_to existing tetap memakai reported_date dan berlaku pada ringkasan/grafik/daftar/download. Metadata XLSX baris ketiga menjadi Dasar tanggal: reported_date tiket Dompis; posisi header dan rincian tetap sama.
- Tiga kategori download, isian filter terkini, seluruh baris lintas pagination, scope canonical, kerahasiaan detail LOP luar scope/arsip, tipe sel Excel, dan penanganan sumber gagal dipertahankan. Tidak ada perubahan database atau dependency.
- Dua regression direproduksi gagal sebelum perbaikan: form absolut membawa HTTP internal, dan month lama masih membatasi data. Sesudah perbaikan, DOM form/ketiga tombol diperiksa dengan deteksi HTTP dan HTTPS untuk ADMIN/SUPER_ADMIN; parameter bulan lama tidak memengaruhi halaman/XLSX dan tidak muncul lagi di filter/pagination. Regression leap day/tahun baru serta ekspor diperbarui memakai rentang tanggal.
- Verifikasi: **451 tests / 4591 assertions** Laravel dan **41 frontend tests** lulus; regression monitoring **41 tests / 611 assertions** mencakup TicketCoverageTest (28) dan IncidentMonitoringTest (13). Pint file PHP task, Vite build, view:cache, serta git diff --check lulus. Tidak ada tes browser/server aaPanel nyata, migration/write database operasional, commit/push, restart worker, atau deployment. Setelah commit/push dan pull, clear/rebuild Blade view cache di server agar form baru digunakan. Tidak perlu migration untuk perbaikan ini; izin migration AISS tetap terpisah.

### 7.46 Download Data LOP sesuai filter — 16 Oktober 2026

- Master Data → Data LOP mempunyai tombol Download Excel di sebelah Input LOP Baru. Tombol submit form GET dengan formaction relatif `/master-data/lops/export`, mengikuti isian pencarian/program/status/branch terkini tanpa harus Terapkan dan tanpa membawa page. Seluruh hasil dalam scope diekspor, bukan hanya 20 baris tabel. Action form filter juga relatif agar tidak mengirim dari halaman HTTPS menuju HTTP hasil deteksi backend.
- `DataLopFilterRequest` dipakai index/export: authorize ADMIN/SUPER_ADMIN serta policy viewAny, memvalidasi string pencarian/branch maksimum 255, enum program/status, dan pagination. Middleware role/auth tetap tersedia; bypass middleware role tidak membuka unduhan untuk Teknisi/Manager/Approver. Filter lokasi tidak memperluas scope.
- Filter/query existing diekstrak ke `DataLopService` agar tabel dan ekspor menggunakan predikat serta `LopVisibilityService` yang sama (Area/Region/Branch/multi-Service Area dan legacy). Pencarian tetap Incident/Nama LOP/STO dan branch tetap nama legacy seperti filter tabel existing. Pencarian literal 0 kini diterapkan, bukan terabaikan oleh pemeriksaan truthy. Soft-deleted LOP tidak diekspor. Tidak mengubah Active LOP creator-only atau lifecycle.
- Satu sheet Data LOP berisi ID IHLD, Incident, Nama LOP, STO, Branch, Area, Program, Segmen, Budget Type, Status Dompis, Status AISS, jenis BOQ, Jumlah Item BOQ, Deskripsi Pekerjaan, Dibuat Oleh, Tanggal Dibuat (WIB). Lokasi ekspor mengutamakan FK dengan fallback legacy; BOQ memakai item_count snapshot atau count item reservasi bila plan tidak ada. Ini daftar LOP, bukan ekspor rincian designator/nilai Revenue. Nilai sumber tidak diubah/dihitung ulang.
- Rincian dibaca per 250 LOP dengan keyset ID menurun, eager-load metadata relasi dan withCount item reservasi, tanpa memuat evidence/foto atau seluruh item BOQ. Workbook Maatwebsite/PhpSpreadsheet tetap membutuhkan memori sesuai jumlah sel; volume produksi belum dibenchmark. File private/no-store, tanpa file publik/salinan permanen. Identifier/teks memakai tipe string dan format teks; jumlah item/tanggal numerik, null tetap blank, nol tetap 0. Header gelap, wrap deskripsi/nama, autofilter dan freeze pane C2.
- Verifikasi: **459 tests / 4733 assertions** Laravel dan **41 frontend tests** lulus. `DataLopExportTest` (8 tests / 142 assertions) membaca XLSX nyata: filter gabungan/individual dan q 0, seluruh scope/multi-STO/legacy/luar scope, arsip, pagination/chunk 255, formula/leading zero/date/count/null, fallback FK/reservasi, role tanpa middleware, parameter malformed, DOM form same-origin dan current-input/no page. Pint file PHP task, Vite build, view:cache, route registration, serta git diff --check lulus.
- Layout dievaluasi dengan workbook sintetis dari exporter aplikasi dan pratinjau dua rentang memakai spreadsheet tooling; tidak ada data operasional yang diekspor. Renderer pratinjau dapat menampilkan identifier numerik-teks tanpa nol depan meskipun inspect dan XLSX asli mempertahankan nilai string; tipe/nilai/format teks diverifikasi terpisah dengan PhpSpreadsheet. Tidak ada verifikasi Excel native/browser/server aaPanel nyata, migration/write database operasional, dependency baru, commit/push, queue restart, atau deployment. Migration AISS tetap memerlukan izin terpisah bila belum dijalankan pada environment tujuan.

### 7.47 Tiga designator khusus, deklarasi capture dan hapus evidence teknisi — 16 Oktober 2026

- Requirement user: J-CO-OF, J-Coring, J-OS-SM-1 pada BOQ Plan/reservasi wajib terlihat di workflow, qty aktual mengikuti qty reservasi/BOQ, serta mempunyai evidence Progress dan After. Klarifikasi user mengizinkan nilainya ikut BOQ/Revenue pada seluruh program. Jasa lain tetap mengikuti kategori program existing; Recovery tetap tanpa Plan dan realisasi hanya Completed.
- `ReservedDesignatorRules` menyimpan whitelist case-insensitive exact (bukan prefix/fuzzy), query picker reservasi, eligibility dan pemetaan kode. Dipakai import BOQ/legacy, save, assignment/sinkronisasi reservasi, editor admin/teknisi, proyeksi/Data BOQ/download per LOP dan keseluruhan, serta SQL Revenue seluruh scope. Pair J khusus tidak dibentuk otomatis dari M dengan kode dasar sama; J eksplisit memakai own reservation/qty_actual dan harga snapshot. Pasangan biasa Utilitas tetap existing. Tidak membuat master designator atau mengubah snapshot/data historis secara massal.
- LOP lama yang BOQ-nya sudah memuat jasa khusus namun belum terreservasi menampilkan item rencana di Step 1 dan belum dianggap lengkap; GET tidak menulis data. Saat Simpan Reservasi, item khusus dari plan yang belum disertakan ikut ditambahkan. Save reservasi mempertahankan ID/actual item existing serta melindungi hapus item berevidence/actual, bukan menghapus seluruh reservasi. Progress/After menampilkan ketiga item dan checklist menolaknya bila proof belum ada/rejected. Qty aktual terisi otomatis readonly, request menolak nilai berbeda, service menyimpan qty reservasi yang fresh dalam lock. Summary/review BOQ memakai aturan kelengkapan sama. Complete Review juga menolak tiga item jika belum persisted, qty belum sesuai, atau Progress/After belum approved.
- Pilihan Tidak Ada untuk insera/slot_port menyimpan `{unavailable,user_id,recorded_at}` per kategori di kolom JSON nullable baru. LopService menulis kolom terarah dan event optional_capture_choice tanpa mengubah status/legacy/BOQ. PUT `technician.projects.optional-capture` memakai role/auth, Form Request dan Gate assignment aktif/status kerja, lalu recheck parent lock. Kategori lain, project milik teknisi lain, read-only status dan pilihan yang menutupi file tersimpan ditolak. Pilihan sama idempotent. Pembatalan/upload baru mencatat histori dan menghapus marker; rollback upload mempertahankan marker. Tidak Ada tetap opsional, bukan evidence/review/count/progres dummy. UI teknisi memuat pilihan setelah reload; admin review dan tracking menampilkan keterangannya.
- Frontend menunggu respons sebelum menandai tersimpan, memakai endpoint relatif, PUT same-origin dan CSRF; loading/disabled/error, timeout 15 detik, pencegahan klik ganda, serta proteksi file terpilih/error tetap tersedia. Tidak menyimpan pilihan palsu ketika offline/419/validasi gagal. Preview/retry/multiple upload dan format file existing dipertahankan.
- DELETE `technician.projects.evidence.destroy` menampilkan konfirmasi per foto. Policy membatasi role Teknisi, uploader sendiri, assignment aktif, status bukan Completed, dan evidence bukan approved/dokumen exempt. Hapus pending/rejected pada Waiting Approval/Rejected membuka Progress melalui LopService, mencatat evidence_deleted dan mengembalikan reservasi draft. Admin scope tetap canonical; ID evidence milik parent lain ditolak. Service mengunci parent lalu membaca fresh evidence dan reauthorize agar model pending lama tidak menghapus foto yang baru diapprove. Physical file cleanup hanya setelah outer commit; rollback tidak merusak file asli. Policy di galeri memakai eager-loaded parent/assignment untuk menghindari query per foto; backend mutation tetap memakai data fresh.
- Reset review menyimpan audit reviewer/note/waktu lama. Reset pada Completed khusus admin-level dan membuka Progress (nilai realisasi keluar sementara); teknisi lalu dapat menghapus foto pending dan melengkapi/mengajukan ulang. General endpoint transisi teknisi tetap tidak mengizinkan manipulasi status; izin Waiting Approval → Progress hanya dipakai koreksi evidence dengan active assignment. Dokumen Surat Permintaan tetap approval-exempt dan tidak mengikuti jalur hapus foto teknisi.
- Verifikasi: **480 tests / 4927 assertions** Laravel dan **46 frontend tests** lulus; `TechnicianSpecialDesignatorAndEvidenceTest` (21 cases / 194 assertions) meliputi empat program/all scopes/snapshot vs master/virtual pair, plan dan fallback/revenue/ekspor, CSV import whitelist, reservasi legacy tanpa write GET, fixed actual dan proof, completion guard, persistence/idempotency/role/state/file guard, reset Completed, stale model/parent/assignment, outer rollback/file retention, serta rollback migration berisi data. Regression lama ikut lulus. Pint file PHP task, Vite build, view:cache, route registration dan git diff --check lulus.
- Migration JSON hanya diuji di SQLite test, **belum dijalankan pada MySQL lokal/produksi**. Audit DB read-only tidak dapat terkoneksi. Tidak ada import/upload/hapus/review data nyata, worker restart, commit/push atau deployment. Browser/device/concurrency MySQL/produksi belum diverifikasi. Memerlukan izin untuk migration spesifik sebelum fitur Tidak Ada aktif; setelah deploy build asset/rebuild cache dan restart worker agar import BOQ memuat whitelist terbaru. LOP lama Completed yang perlu koreksi harus dibuka admin terlebih dahulu, bukan diubah diam-diam.

### 7.48 Material Tiba per material BOQ Plan dan akses Import AISS — 16 Oktober 2026

- Khusus QE Relok Utilitas, Step 2 menampilkan accordion per designator MATERIAL dengan qty > 0 pada snapshot `qe_boqs/qe_boq_items`, memakai kode/nama/qty/unit plan. Tiap item wajib minimal satu foto non-rejected; multiple foto, preview, hapus, replace rejected dan status review existing dipertahankan. Accordion item mulai tertutup agar banyak item tetap ringkas. Recovery/Preventive/Relok Tiang tetap upload Material Tiba global.
- `MaterialArrivalRules` dipakai `TechnicianWorkflowService`, `ProjectProgressService`, `EvidenceService::storeOne`, serta `EvidenceApprovalService`. Designator wajib berasal dari material BOQ Plan LOP yang sama, bukan jasa khusus/umum, item volume nol atau hanya tambahan reservasi. Store membaca parent/plan fresh dalam lock sebelum menulis file agar model lama tidak mengabaikan perubahan plan. Tanpa plan/material, checklist tidak lengkap dan UI meminta admin melengkapi BOQ Plan; tidak membuat plan palsu dari reservasi.
- Foto global/item lama tetap dapat dilihat, tetapi tidak memenuhi checklist per item plan; replacement untuk kelompok dokumentasi lama tidak ditawarkan di UI. Tidak ada penghapusan/backfill/relabel foto lama otomatis. Waiting Approval/Completed lama tetap status existing saat dibaca; bila perlu evidence tambahan, admin perlu mengembalikan alur melalui reject/reset existing, bukan GET menulis data. Submit Approval ditolak bila ada material plan belum berevidence. Complete Review dan tombol selesai menuntut proof approved per material plan, selain semua evidence reviewable approved. Admin Step 2 menampilkan checklist plan dan kelompok foto per designator.
- `import-aiss` di `AppServiceProvider` membatasi akun aktif SUPER_ADMIN atau ADMIN scope AREA. Sidebar menyembunyikan menu untuk scope lain; controller, Form Request, hasil/status batch serta worker existing memakai Gate yang sama. Scope/pemilik hasil batch existing tidak diperluas. Antrean lama dari akun yang berubah scope atau tidak berhak menjadi failed tanpa menulis Status AISS; worker tetap mengecek ulang izin selama proses.
- Regression baru `MaterialArrivalPerDesignatorTest` **12 cases / 97 assertions**: checklist parsial/global/rejected, dua jalur upload/multiple/original file, desainator bukan plan/jasa/zero/extra, assignment, plan hilang, tampilan teknisi read-only/admin, completion guard, replace, submit dan stale plan sebelum storage, serta tiga program lain unchanged. `AissImportTest` diperbarui untuk Area/legacy/outside dan penolakan semua scope lain/menu/URL/upload/hasil/polling/worker/inactive/perubahan scope.
- Verifikasi: **493 tests / 5060 assertions** Laravel dan **46 frontend tests** lulus. Test mengabaikan config cache runtime melalui `APP_CONFIG_CACHE` ke path temporary yang kosong agar tetap SQLite in-memory; percobaan awal memakai cache runtime terhalang koneksi MySQL sebelum migrasi/test menulis. Pint file PHP task, Vite build, Blade view:cache dan git diff --check lulus. Tidak ada browser/device/worker/server produksi nyata yang diuji.
- Tidak menambah schema atau menjalankan migration/write/import/review/upload operasional, queue restart, commit/push atau deploy. Fitur baru memakai `qe_evidences.designator_id` existing. Migration capture opsional 7.47 dan AISS 7.43 tetap belum dieksekusi oleh sesi ini dan membutuhkan izin terpisah jika belum tersedia di environment tujuan. Setelah deploy rebuild cache dan restart worker agar pembatasan Gate AISS termuat pada proses lama.

### 7.49 Tag Lokasi opsional dan tempel koordinat — 19 Oktober 2026

- `TechnicianWorkflowService::state/completeSurvey` tidak lagi mensyaratkan survey untuk Step Pra. `ProjectProgressService` juga tidak mengurangi progress karena lokasi kosong; PRE tetap wajib non-rejected. Material Tiba (termasuk per material plan Utilitas), Progress/After, qty actual, capture opsional dan persetujuan evidence tetap mengikuti aturan existing. Complete Review tidak menambah syarat lokasi, dan indikator Step 1 admin tidak bergantung pada survey.
- Form lokasi dipisahkan ke partial `technician/partials/location-form` dengan badge Opsional, petunjuk boleh langsung lanjut, GPS, kolom Tempel koordinat, dua isian Latitude/Longitude yang dapat ditinjau/diedit, tombol Simpan lokasi/Buka peta, loading/error dan indikator belum disimpan. Read-only teknisi menyatakan Lokasi tidak diisi (opsional). Koordinat existing tetap ditampilkan; skip tidak mengirim request, tidak menghapus data dan tidak membuat `qe_surveys` kosong.
- Module `resources/js/technician-location.js` menerima pasangan desimal bertitik yang dipisah koma/spasi/titik koma (opsional kurung). Default Latitude lalu Longitude; long-lat dapat dibalik otomatis hanya bila angka pertama di luar rentang latitude dan angka kedua valid sebagai latitude, seperti 112.752090, -7.257472. Jika keduanya -90..90, urutan tetap mengikuti label latitude dahulu, bukan menebak. URL Maps/DMS/decimal-comma/teks lain ditolak dengan petunjuk format; tidak memakai API geocoding atau clipboard permission. Angka 0 valid. Pasangan hasil parsing tetap perlu Simpan lokasi untuk tercatat.
- Paste invalid tidak merusak koordinat tersimpan; edit manual/paste membuang accuracy GPS lama dan memakai source manual. Respons GPS terlambat tidak menimpa perubahan manual; unsupported/denied/throw/timeout memberikan error tanpa memblokir langkah pekerjaan. State GPS/paste hanya lokal sampai POST existing berhasil; bukan penanda tersimpan palsu. Form action relatif same-origin dan server tetap mewajibkan pasangan numeric/range pada aksi Simpan, sehingga lokasi opsional tidak berarti menyimpan nilai parsial/null.
- Regression direproduksi gagal sebelum perbaikan. `TechnicianMobileWorkflowTest` memeriksa Pra tanpa lokasi, PRE kosong/rejected, UI tempel dan read-only, keempat program dari pickup hingga Completed/100% tanpa survey/capture, serta simpan/update/zero/invalid/teknisi lain tanpa merusak lokasi. Frontend `technician-location.test.mjs` berisi 8 cases untuk parser/paste/range/zero/dirty/accuracy, GPS gagal, klik ganda dan respons GPS stale.
- Verifikasi: **494 tests / 5101 assertions** Laravel dan **54 frontend tests** lulus; test SQLite in-memory melewati config cache runtime dengan `APP_CONFIG_CACHE` temporary. Pint file PHP task, build Vite, view:cache dan git diff --check lulus. Tidak ada migration/schema/dependency baru, write data operasional, GPS/upload/login akun nyata, queue restart, commit/push atau deployment. Browser/APK/device serta server produksi belum diuji. Migration AISS/capture opsional terdahulu tidak dijalankan pada task ini dan tetap memerlukan izin terpisah bila belum tersedia di environment tujuan.

### 7.50 Fallback lokasi APK dan hasil gagal Bulk Import LOP — 19 Oktober 2026

- User melaporkan pesan GPS gagal pada APK WebView walau izin perangkat sudah diberikan, lalu tombol tampak disabled. Code sebelumnya memang men-disable Simpan lokasi saat koordinat kosong dan menampilkan tombol lanjut abu-abu jika checklist belum lengkap. Tidak ada bukti bahwa permission/GPS native APK telah diperbaiki; task ini memperbaiki fallback web agar kegagalan GPS tidak menahan pekerjaan.
- Form lokasi tidak lagi mempunyai HTML required, guard submit JavaScript atau disabled berdasarkan koordinat. Ada link Lanjut tanpa mengisi lokasi ke foto Pra. Tombol Lanjut Progress selalu aktif, memakai form ID terpisah, formnovalidate/novalidate dan POST same-origin tanpa ketergantungan pada GPS/komponen Alpine. Backend tetap memeriksa evidence wajib; foto Pra belum berhasil diupload dijelaskan sebagai kekurangan evidence, bukan lokasi. Material Tiba per BOQ Plan Utilitas dan evidence/qty lainnya tidak dilonggarkan.
- `StoreSurveyLocationRequest` menerima kedua koordinat kosong; `saveLocation` mengembalikan null tanpa menulis survey atau menghapus lokasi lama. Pasangan parsial tetap ditolak. `coordinates` kini ikut dikirim oleh form dan diparse melalui `CoordinatePair` di server, sehingga paste bisa disimpan walaupun helper JavaScript gagal dimuat. Format/range/urutan mengikuti 7.49; tipe/location_source default manual, source GPS tetap dipertahankan jika tidak memakai paste. Tidak ada kolom/tabel/migration baru.
- Audit menemukan bug pasti `ImportRowWriter::insert`: menghapus seluruh hasil batch pada setiap flush 25 baris. Regression 56 baris sebelum perbaikan hanya memiliki enam detail terakhir. API `append` ditambahkan khusus chunk Bulk LOP; `insert` existing tetap replace untuk importer lain/BOQ. Bulk LOP kini mempertahankan semua detail lintas chunk dan batch finished tidak diproses ulang otomatis.
- Error validasi baris memakai label Indonesia dan menyebut field/perbaikan: STO kosong/tidak terdaftar/inaktif, scope/Branch/Area bertentangan, program/segmen tidak dikenal, Deskripsi Pekerjaan kosong/panjang, Incident salah/duplikat, Paket tidak tersedia. `result.errors` dan saran koreksi disimpan bersama message, row_number, reference dan payload terbatas. Budget otomatis tidak menghasilkan error wajib yang menyesatkan ketika program tidak dikenal. SQL/binding/path/koneksi dan exception internal tidak ditampilkan; schema/panjang/timeout/worker signature lama mempunyai pesan aman khusus.
- Halaman hasil Bulk LOP mempunyai filter Semua/Gagal/Berhasil, mempertahankan filter pada pagination, dan menampilkan baris asli, Incident/Nama LOP, STO/program, penyebab serta saran upload ulang hanya baris gagal. Job/file failure sebelum parsing tetap menampilkan alasan tingkat file. Jika jumlah detail lebih sedikit dari total/counter (batch lama atau proses terhenti), ada peringatan untuk memeriksa Data LOP. Detail yang dahulu terhapus tidak direkonstruksi/dikarang, file lama tidak direimport dan data nyata tidak diubah.
- `ProcessBulkLopImport` menangani failure secara aman, failOnTimeout, lock 660 detik/timeout 600, recheck akun aktif/izin create, serta menjaga hasil/counter durable dan tidak menimpa batch finished dari callback terlambat. Dispatch yang gagal tetap menghasilkan tautan halaman hasil dan pesan aman. Masih mungkin ada baris LOP yang committed sebelum flush hasil saat proses mati mendadak; periksa Data LOP sebelum retry. Ini bukan jaminan transaksi batch seluruh file atau uji concurrency MySQL.
- Verifikasi: **500 tests / 5182 assertions** Laravel dan **54 frontend tests** lulus. Regression mencakup DOM tombol Continue/form terpisah/tanpa required, blank/no-op dengan lokasi existing, fallback paste server/invalid/ownership, hasil 56 baris/3 chunk, alasan field, filter/pagination, batch idempotensi, callback gagal/late, header/file/SQL redaction, dispatch failure dan tampilan rincian tidak lengkap. Pint file PHP task, Vite build, view:cache, dan git diff --check lulus. Tests tetap SQLite memory dengan config cache terpisah. Belum ada uji APK/device/GPS native atau worker/server produksi nyata.
- Tidak menjalankan migration, import/review/upload akun nyata, write database operasional, restart worker, commit/push atau deploy. Setelah kode dipush/pull, build asset, rebuild view cache dan restart worker melalui prosedur deployment agar handler import baru dipakai. Migration AISS/capture opsional terdahulu tetap membutuhkan izin terpisah bila belum tersedia.

### 7.51 Detail langsung dari matrix dashboard dan tiket yang sudah memiliki LOP — 19 Oktober 2026

- Audit menemukan tautan membawa ID yang benar, tetapi route legacy `lop.show` sengaja hanya redirect ke daftar/halaman asal. User tidak pernah masuk informasi LOP yang dipilih. Perilaku legacy tersebut tetap dipertahankan untuk alur lain agar tidak mengembalikan masalah navigasi lama.
- Route auth GET `/lop/{qe_lop}/detail` bernama `lop.detail` menampilkan satu LOP secara langsung melalui `LopController::detail`, bukan redirect ke Inbox/Master Data atau modal lain. `QeLopPolicy::view` tetap memeriksa scope canonical seluruh level admin; bukan filter created_by Inbox. Superadmin mengikuti policy existing; Teknisi yang berhak diarahkan ke workspace mobile dan yang tidak ditugaskan ditolak. Parent arsip tetap 404.
- `AdminDashboardService::matrixLops` mengirim detail_url relatif ke route baru, dipakai bersama desktop/mobile pada modal angka matrix Program/Status LOP. Dua tombol desktop/mobile tab Monitoring Ticket → Sudah Memiliki LOP juga memakai route tersebut. Pencocokan tiket, angka dashboard, filter, scope, serta data di luar akses/arsip tidak berubah. Tautan legacy pada halaman lain tidak diubah massal.
- Konten memakai komponen detail existing (Overview, Material & Laporan sesuai izin, Tiket & Jaringan), dengan prop standalone dan panel pembungkus non-dialog. Modal existing tetap modal secara default. BOQ/progress/harga memakai service existing; tidak ada kalkulasi ulang harga atau query write. Tombol Kembali memakai whitelist `LopReturnDestination`, ditambah halaman monitoring/tiket agar filter asal dipertahankan; URL eksternal tetap ditolak.
- Regression `LopDirectDetailTest` (4 cases) mencakup empat scope admin/Superadmin, LOP buatan user lain di scope, target tepat tanpa redirect, luar scope/arsip/guest/Teknisi, non-dialog, return URL/filter dan penolakan origin eksternal, serta mengikuti dua link responsive tiket sampai HTTP 200 LOP target. AdminDashboardTest memeriksa detail_url matrix serta halaman target; TicketCoverageTest diperbarui untuk link langsung. Suite navigasi lama tetap lulus.
- Verifikasi: **504 tests / 5249 assertions** Laravel dan **54 frontend tests** lulus. Vite build, view:cache, route:list untuk lop.detail, Pint file PHP task dan git diff --check lulus. Tidak ada migration/schema/write data operasional, import nyata, restart worker, commit/push atau deploy. Browser/APK/produksi belum diuji; setelah pull clear/rebuild route/view cache agar route dan Blade baru digunakan. Tidak perlu migration untuk perbaikan ini.

### 7.52 Tambah TSEL pada Recovery dan PREMAN pada Recovery/Preventive — 20 Oktober 2026 (commit `37e23f7`)

- `ProgramType::incidentTicketTypeLabel()` kini: Recovery = `GAMAS, UNKNOWN, TSEL, atau PREMAN`; Preventive = `GAMAS, UNKNOWN, INFRACARE, atau PREMAN`. TSEL hanya diterima pada Recovery; PREMAN diterima pada keduanya. `IncidentTicketValidationService::matchesProgram()` memakai kecocokan mengandung untuk `GAMAS`/`TSEL` dan kecocokan persis untuk `UNKNOWN`/`INFRACARE`/`PREMAN`.
- Validasi dipakai bersama oleh input manual LOP dan Bulk LOP, sehingga teks panduan sheet (`BulkLopTemplateService`), pesan per baris di `ImportBatchController`, label halaman Bulk LOP, dan pesan frontend `lop-form.test.mjs` semuanya mengikuti enum. Invariant lain tidak berubah: Revenue tanpa plan tetap hanya untuk Recovery.
- Regression: `IncidentProgramValidationTest` bertambah kasus TSEL (hanya Recovery) dan PREMAN (kedua program), termasuk penolakan TSEL pada Preventive.

### 7.53 GAP dan persentase realisasi memakai aktual penuh — 20 Oktober 2026 (commit `09c03c5`)

- Sebelumnya `RevenueService` menghitung `gap_value = max(0, plan - plannedActual)` dan `realization_percentage` dari `plannedActual / plan`, sehingga GAP terlihat mengecil dan persentase terlihat lebih tinggi dari kartu Overview. Sekarang keduanya memakai `actual_value`: **GAP = Plan − Realisasi, persentase = Realisasi ÷ Plan**, berlaku pada per-program, agregat, dan `detailOverview`.
- Aturan canonical tidak berubah: revenue realisasi hanya menghitung LOP `completed`, BOQ adalah snapshot, Recovery tanpa plan. `RevenueDashboardTest` disesuaikan.

### 7.54 Exclude tiket Dompis dari monitoring — 20 Oktober 2026 (commit `d19dc7e`)

- Tabel baru `incident_excludes` lewat `2026_10_20_000001_create_incident_excludes_table.php` (`incident` varchar 100 unik ternormalisasi, `keterangan_exclude` varchar 50, `created_by` FK `users.id_user` null-on-delete, timestamps). **Migration wajib dijalankan di produksi — lihat 2.3.**
- `App\Enums\ExcludeReason`: `non_material` (Non Material), `temporer` (Temporer), `duplikat_open` (Duplikat Tiket Open). `ExcludeTicketRequest` mensyaratkan `SUPER_ADMIN|ADMIN`, `incident` maks 100, dan `reason` enum. `TicketCoverageService::exclude()` memakai `updateOrCreate` pada `incident` ternormalisasi `UPPER(TRIM())`; `unexclude()` menghapusnya.
- Route `POST /incident-monitoring/tickets/exclude` dan `DELETE /incident-monitoring/tickets/{incident}/unexclude`, keduanya `auth` + `role:SUPER_ADMIN,ADMIN`.
- Klasifikasi coverage kini bertiga dan berurutan, dengan LOP lebih dulu: `CASE WHEN with_lop THEN 'with_lop' WHEN excluded THEN 'excluded' ELSE 'without_lop'`. Konsekuensinya tiket yang sudah punya LOP tetap `with_lop` meski di-exclude. Tab filter `usage` bertambah `excluded`; tanpa LOP + excluded masuk `excluded`, sisanya `without_lop`. Baris membawa `exclude_reason`, dan export `TicketCoverageExport` mengikuti klasifikasi yang sama.
- `tickets.blade.php` menambah tombol exclude/unexclude beserta pemilih alasan; `incident-monitoring/index.blade.php` menampilkan tab baru. `IncidentExcludeTest` (baru), `IncidentMonitoringTest`, dan `TicketCoverageTest` diperbarui.

### 7.55 Monitoring matrix: pengelompokan STO dan judul Monitoring Wilayah — 20 Oktober 2026 (commit `9e251d7`)

- `DashboardProgramMatrixService` menambah key `groups`, hanya untuk dimensi `service_area`: baris di-group berdasarkan nama STO (sudah `orderBy('name')` lalu `orderBy('workzone')`, dan `groupBy` mempertahankan urutan itu). Tiap grup berisi `name`, `count`, `summary` hasil merge metrics, dan `rows`. Dua konstanta penampung: `STO TANPA NAMA` untuk nama kosong dan `STO BELUM TERDATA` untuk baris `Service Area belum terdata`. Dimensi lain tetap `groups => []`.
- `program-matrix.blade.php` merender baris header per grup (nama, jumlah STO, angka merge) sebagai `<tbody>` terpisah, lalu baris anak STO di dalamnya; partial baru `program-matrix-row.blade.php` memakai `<td>` bersama sehingga markup angka grup dan baris anak tidak terduplikasi. Accordion per **grup** (bukan per STO) lewat `x-data="{ openGroups: ... }"` dengan default terbuka bila total baris ≤ 10, sehingga ratusan STO bisa dilipat per wilayah tanpa kehilangan baris angka.
- Judul panel `dashboard/index.blade.php` diganti **`Matrix Program` → `Monitoring Wilayah`**; subtitle hanya dirender untuk SUPER_ADMIN, dan label "Ringkasan per … sesuai scope akses Anda." dihapus dari panel maupun dari card program-matrix. Dua blok legenda dan satu baris penjelas masih ada dalam bentuk **dikomentari** (`{{-- ... --}}`), belum dihapus permanen. Perubahan `monitoring.blade.php` (`wrap-break-word`) milik user.
- Regression `AdminDashboardTest::test_service_area_matrix_groups_sto_rows_by_name_and_stays_collapseable`. Assertion judul pada `AdminDashboardTest:156` disesuaikan dari `Matrix Program` ke `Monitoring Wilayah` karena rename di atas — perubahan file test ini masih belum di-commit (lihat 2.2).

### 7.56 Filter tanggal reported date pada Master Data → Data BOQ — 20 Oktober 2026 (belum di-commit)

- **Rancangan pertama tidak kuat pada data produksi**: karena `qe_lops` tidak menyimpan tanggal laporan, filter harus menerjemahkan rentang → daftar incident di koneksi read-only `mysql_dompis` lalu mengirisnya di query lokal setiap request. Karena itu tanggalnya dipindahkan ke kolom lokal.
- **Kolom `qe_lops.reported_date` (DATE, nullable, terindeks)** menyimpan salinan ternormalisasi dari sumber canonical `ticket.reported_date` — bukan `reported_date_dt`, `created_at`, atau tanggal LOP. Nilai di lapangan campuran ISO (sering disertai waktu) dan `DD/MM/YYYY`; keduanya menghasilkan hari yang sama. Tanggal kosong/tidak terbaca disimpan `NULL`: tetap tampil tanpa filter, tidak pernah masuk rentang terpilih.
- `App\Support\TicketReportedDate::normalize()` adalah versi PHP dari `expression()` dengan aturan identik (garis miring = hari-dulu, selain itu ISO, waktu diabaikan, tanggal kalender tidak valid → `NULL`). `expression()` dan `applyRange()` tetap dipakai `TicketCoverageService` untuk halaman monitoring, jadi pemindaian tiket berbasis rentang tidak ikut berubah.
- `App\Services\IncidentReportedDateService` kini berisi `datesFor(array $incidents)` dan `applyRange($query, $from, $to)`. Lookup memakai kolom `incident` yang **berindex**; `UPPER(TRIM())` sengaja ditinggalkan karena tidak bisa memakai index dan berarti full scan tabel 1,75 GB / 829k baris per LOP yang dibuat. `applyRange()` adalah satu-satunya tempat aturan `>=`/`<=` diterapkan untuk daftar BOQ, list reservasi, dan export, sehingga tidak ada dua versi aturan.
- **Pengisian kolom berada di satu jalur**: `LopService::create()` dan `LopService::update()` — satu-satunya penulis `incident` pada `qe_lops`, dan manual/Bulk LOP/Excel import/AISS semuanya lewat service ini. Mengubah incident saat edit ikut menyegarkan tanggalnya. Incident `INP*` dan nilai yang tidak ada di sumber tetap `NULL`. Sumber tiket yang mati saat menyimpan **tidak menggagalkan** LOP; tanggalnya dapat menyusul lewat sync.
- `php artisan dompis:sync-reported-date` meng-backfill dan menyegarkan kolom: chunk 500, idempoten, `--all` untuk memaksa seluruh baris, dan `timestamps` tidak diubah supaya `updated_at` LOP tidak ikut bergeser. Sudah dijalankan lokal; jalankan lagi setelah migrasi di produksi dan kapan pun sumber tiket pulih.
- Karena halaman dan export tidak lagi menyentuh `mysql_dompis`, **banner "sumber tiket tidak tersedia" dan pembatalan unduhan (`back()->with('error')` dihapus** — jalur gagal itu tidak ada lagi. Pencarian `q` tetap tidak dibawa ke export, sesuai permintaan.
- `DataBoqFilterRequest` tetap memvalidasi `date_format:Y-m-d` dan `after_or_equal:date_from` dengan pesan Indonesia. Halaman menampilkan input `min` terikat, teks "Filter aktif", link Reset, dan tautan `Download Excel` dinamis yang membawa `date_from`/`date_to` aktif beserta judul yang berubah; tanpa filter tautan kembali polos. Filter berlaku untuk **kedua daftar** (BOQ Plan dan BOQ Aktual dari Reservasi Teknisi) dan untuk export.
- Regression `DataBoqDateFilterTest` (12 cases): inklusif dua batas, rentang terbuka + tanggal tak terbaca, list reservasi ikut tersaring, filter tidak bocor dari scope admin cabang, salinan tanggal saat create (ISO, hari-dulu, tak terbaca, `INP`), penyegaran saat incident diedit, sinkronisasi backfill beserta rerun tanpa perubahan, tautan unduh membawa rentang aktif, isi Excel mengikuti rentang (rentang penuh/satu batas/tanpa filter), rentang terbalik ditolak di daftar maupun di export, dan filter + export tetap jalan saat koneksi tiket rusak. `IncidentProgramValidationTest` memastikan prefetch tipe tiket tetap satu query batch untuk seluruh baris.
- Verifikasi: **530 tests / 5437 assertions** Laravel lulus. Pint pada file PHP task lulus, `view:cache` dan `npm run build` lulus, `git diff --check` bersih. **Migrasi kolom dan command sync sudah dijalankan pada MySQL lokal** (lihat 2.3); tidak ada import nyata, restart worker, commit/push, atau deploy. MySQL/produksi, Nginx, dan storage tidak diverifikasi; test memakai SQLite dengan `mysql_dompis` di-fake in-memory.

### 7.57 Kolom Reported Date pada tabel master dan Download Excel — 21 Oktober 2026 (belum di-commit)

- `DataBoqExport` menambah kolom **`Reported Date` di posisi C**, di antara `Incident` dan `Nama LOP`, sehingga seluruh penomoran kolom setelahnya bergeser. `DataBoqExportService::rows()` menyisipkan `$lop->reported_date`; penyesuaian kolom apa pun **wajib disinkronkan dengan `headings()`**, dan nilai kosong tetap `null` (sel benar-benar kosong) mengikuti `WithStrictNullComparison`.
- Penyesuaian teknis yang menyertainya: `bindValue` memaksa teks pada A–G sehingga proteksi nilai mirip formula pada `Nama LOP` berpindah ke kolom D, `columnFormats` numerik pindah ke H–K, `columnWidths` disusun ulang (Reported Date 16), `wrapText` pindah ke D dan F, dan autofilter menjadi `A1:K`.
- Ketiga tabel kini memperlihatkan tanggal **di samping incident** sebagai teks kecil `text-[10px] tabular-nums` abu-abu dengan `title="Tanggal laporan incident"`: `master-data/lops` (sel LOP) dan `master-data/boqs` untuk **kedua daftar** (BOQ Plan serta BOQ Aktual dari Reservasi Teknisi). Nilai kosong tampil `—`, bukan sel kosong. Format memakai ISO `Y-m-d` apa adanya — sengaja sama dengan kolom Excel dan nilai pada input filter, supaya tidak ada dua format untuk tanggal yang sama.
- Test yang menyesuaikan: `DataBoqExportTest` (posisi kolom, `=1+1` pindah ke D2, autofilter A1:K, baris lengkap dengan kolom kosong), `TechnicianSpecialDesignatorAndEvidenceTest` dan `BoqProgramCategoryRulesTest` (indeks kolom Total 9 → 10). Regression baru `DataBoqDateFilterTest::test_reported_date_is_shown_next_to_the_incident_on_both_master_tables` memastikan tanggal tampil berdampingan dengan incident di kedua halaman, `—` untuk LOP tanpa tanggal, dan kolom C Excel membawa tanggal dari kolom lokal.
- Verifikasi: **531 tests / 5443 assertions** lulus. Pint pada file task lulus, `view:cache` dan `npm run build` lulus, `git diff --check` bersih. Tidak ada migrasi atau schema baru dari perubahan ini — kolom `qe_lops.reported_date` sudah tersedia dan terisi dari 7.56.

### 7.58 Tahap 6 BA Bongkar dan Warehouse — 23 Oktober 2026 (belum di-commit)

- Dua migration additive baru: `2026_10_23_000001_add_ba_bongkar_to_qe_lops.php` menambah keputusan nullable `ba_bongkar_required` dan draft/revisi JSON `ba_bongkar_data`; `2026_10_23_000002_add_warehouse_role.php` menambah WAREHOUSE dan permission view/review BA. SUPER_ADMIN tetap mendapat seluruh kode permission; keputusan BA tetap khusus Warehouse melalui policy. **Keduanya sudah Ran pada MySQL lokal `dompis_qe`, batch 34, setelah izin eksplisit user; produksi belum diterapkan/diverifikasi.**
- Audit read-only MySQL lokal (`migrate:status`, `db:table qe_lops`, `db:table qe_evidences`) memastikan migration terdahulu Ran, step DISMANTLE dan category varchar(30) tersedia. Artefak tabel/kolom BOQ plan legacy masih terlihat pada schema lokal; tidak dihapus atau digunakan task ini. Produksi belum diaudit.
- Semua program menggunakan enam tahap. Tidak = keputusan tersimpan tanpa evidence dummy/antrean Warehouse. Ya = draft form dengan beberapa rincian per jenis sesuai PDF di plans, maksimal 12 foto × 10 MB/24 MP, TTD teknisi digambar, lalu generate PDF. Nomor BA, tahun, merek, mitra dan usulan tindak lanjut opsional. Data bongkaran terpisah dari BOQ/reservasi/harga/Revenue.
- Dompdf 3.1.6 ditambahkan ke Composer; runtime memerlukan DOM/MBString/GD. PDF/foto memakai `qe_evidences`, kategori `ba_bongkar`/`ba_bongkar_photo`, step DISMANTLE, designator null, disk local privat. Streaming/ZIP memilih disk per evidence; file lama tetap memakai disk existing. Foto asli dipertahankan; gambar di dalam PDF diperkecil. TTD disimpan privat dan inputnya tidak diflash ke session.
- TTD kiri atas dari akun teknisi sebelum submit; kanan atas dari Warehouse saat approve; kiri bawah dari Admin/Super Admin saat menyelesaikan LOP; kanan bawah seluruhnya kosong untuk pihak perusahaan lain offline. Warehouse/Admin dapat menggambar atau upload JPG/PNG/WebP maksimal 2 MB. Nama/NIK berasal dari akun, NIK kosong tidak dikarang.
- BA diperiksa sebagai satu paket PDF/foto. Jalur upload/approve/reject/bulk/reset/replace generic tidak dapat memodifikasi paket BA. Setiap revisi memakai UUID, snapshot form/TTD dan lock parent; request stale ditolak. Evidence lama soft-deleted dengan file tetap tersimpan untuk audit. Draft baru tidak masuk Warehouse sampai diajukan ulang. Ya yang pernah diajukan tidak dapat diganti Tidak untuk menghindari review.
- Submit transactional; review Admin dan Warehouse paralel. Reject BA memindahkan Waiting Approval ke Rejected melalui LopService. Perbaikan membuat revisi baru tanpa menghapus approval evidence pekerjaan lain. Penambahan TTD tidak mengubah isi yang diperiksa. PDF final/TTD Admin/completion satu transaksi; kegagalan membersihkan file baru dan rollback DB. Guard BA juga berlaku pada transisi status generic.
- Warehouse memakai field scope dan `LopVisibilityService` existing: Area, Region, Branch, multi-Service Area serta fallback legacy. User Management/Bulk Import User mendukung scope role ini. Warehouse tidak ditambahkan ke adminLevel/broadVisibility. Login menuju dashboard khusus; menu operasional hanya Dashboard dan Inbox BA Bongkar. Middleware workspace, permission dan policy membatasi modul, record dan file di backend.
- Dashboard berisi jumlah pending/approved/rejected dan antrean tertua. Inbox menyediakan pencarian Incident/LOP, filter lokasi/status, pagination, detail form/foto/PDF, TTD dan alasan reject. Mode review enam tahap, navigasi Review BOQ sampai step 6, dan Approval Saya siap selesai menuju tahap terakhir. Progres menghitung enam tahap dan tidak memakai evidence rejected untuk kelengkapan Progress/After.
- Command `dompis:activate-ba-bongkar --cutoff=ISO_8601_DENGAN_TIMEZONE` default **dry-run**. `--apply --actor=ID_SUPER_ADMIN_AKTIF` menandai seluruh dataset cutoff di histori, lalu mengubah Waiting Approval/Completed/Rejected menjadi Progress. Status awal/Progress dipertahankan; arsip dikecualikan. Assignment/evidence/approval lama/BOQ/actual tetap utuh, reservasi yang dibuka kembali menjadi draft. Histori `ba_bongkar_activated` mencegah rerun membuka Completed yang sudah diselesaikan ulang. LOP tanpa teknisi dilaporkan.
- **Aktivasi lokal selesai 23 Oktober 2026 sekitar 13:20 WIB:** cutoff `2026-10-23T11:14:10+07:00`, actor Super Admin aktif ID 2, 12 LOP diberi histori `ba_bongkar_activated`. Waiting Approval 1 dan Completed 2 (LOP ID 2, 6, 7) kembali ke Progress. Status akhir: Draft 7, Picked Up 1, Progress 4. Tidak ada LOP lanjut tanpa assignment aktif atau item aktual tanpa harga. Nilai Completed Rp1.803.197 keluar sementara dari realisasi sampai Completed lagi. Evidence, approval lama, BOQ/snapshot, item reservasi/qty aktual, assignment dan 42 histori lama terverifikasi tetap utuh; reservasi LOP yang dibuka kembali menjadi draft. Dry-run ulang menghasilkan nol kandidat.
- Backup schema/data privat lokal: `/private/tmp/dompis-ba-activation-20261023-041410/database-before-complete.sql`, SHA256 `f8920558ceed8118cca408d7e0fc19d6566bc0add9845cbc5b6d06a66cc7af92`; manifest pembanding `before.json`/`after.json` pada direktori yang sama. Backup ini memuat data sensitif: jangan commit/publikasikan; pindahkan ke penyimpanan backup privat bila perlu retensi karena `/private/tmp` dapat dibersihkan. Maintenance dipakai saat apply dan aplikasi sudah live kembali. Pemeriksaan akhir memastikan schema/ledger/permission Warehouse serta evaluasi workflow seluruh 12 LOP berhasil. Tidak membuat akun Warehouse operasional, import, restart worker, commit/push atau deployment produksi.
- Penerapan: backup/commit dahulu, tentukan cutoff dataset, audit dry-run, siapkan akun Warehouse berscope, install Composer/build, lalu jalankan dua migration spesifik setelah izin database. Gunakan maintenance saat apply agar tidak bersamaan dengan pekerjaan pengguna. Rollback schema menolak keputusan/draft tersimpan; rollback role menolak akun Warehouse yang masih terikat. Pemulihan status harus berdasar histori/backup dan memperhatikan pekerjaan baru, bukan membalik semua status massal.
- Periksa `post_max_size`, `upload_max_filesize`, memori PHP dan Nginx untuk multipart banyak foto di produksi. Verifikasi MySQL/worker/storage privat/Android WebView fisik tetap terpisah dari test SQLite/sync.
- Verifikasi: test baru `BaBongkarWorkflowTest` mencakup Ya/Tidak, PDF privat, tiga TTD per akun, approval paralel, reject/revisi/stale, scope empat level/legacy, user/import, cleanup, aktivasi/idempotensi/arsip, rollback dan banyak rincian/12 foto. Frontend BA menguji radio, rincian independen, gambar/resize/hapus TTD. Regression lama mengikuti enam tahap dan label MAGU milik user. Full suite memakai `php -d memory_limit=512M vendor/bin/phpunit` karena gabungan PDF/Excel melampaui batas CLI default 128 MB. Browser Chrome headless dengan fixture SQLite memeriksa form mobile, gambar TTD, payload submit, menu Warehouse, modal Admin dan error JavaScript; PDF diperiksa visual. Ini bukan uji APK/perangkat produksi.

### 7.59 Perbaikan warning profil warna PNG pada PDF BA — 26 Oktober 2026 (belum di-commit)

- Generate BA dapat gagal 500 saat PNG foto/TTD mengandung profil warna dengan warning `imagecreatefromstring(): gd-png: libpng warning: iCCP: extra compressed data`. GD masih dapat membaca piksel, tetapi handler Laravel mengubah warning menjadi exception. Fixture PNG sintetis mereproduksi pesan yang sama sebelum perbaikan.
- `App\Support\BaImage::decode()` dipakai oleh `BaBongkarPdfService` dan pemeriksaan tinta `BaSignature`. Handler sementara hanya menangani warning `iCCP` dari decoder PNG; warning lain tetap diteruskan ke handler sebelumnya, hasil decode `false` tetap ditolak, dan handler selalu dipulihkan melalui `finally`. Foto asli tetap disimpan tanpa perubahan; gambar dalam PDF tetap diubah menjadi JPEG seperti sebelumnya.
- Regression HTTP mencakup foto PNG bermasalah sampai generate/approve Warehouse/completion Admin dengan upload TTD PNG yang sama, serta TTD teknisi yang digambar. Unit test memastikan warning asli muncul pada GD, decoding tetap berhasil, warning gambar rusak tidak disembunyikan, dan handler dipulihkan pada jalur sukses/gagal. Test berjalan di PHP 8.5.0 dengan SQLite in-memory; tidak mengulang request atau mengubah LOP 7 pada MySQL operasional.
- Tidak ada migration, perubahan schema/status/approval, dependency, UI, atau deployment dari perbaikan ini. Perubahan BA Bongkar sebelumnya dan label MAGU milik user tetap dipertahankan.
- Verifikasi: `php -d memory_limit=512M vendor/bin/phpunit --colors=never` lulus **555 tests / 5714 assertions**; subset BA/decoder lulus **20 tests / 193 assertions**. Pint pada enam file PHP terkait dan `git diff --check` lulus. Build frontend tidak diulang karena tidak ada perubahan Blade/JavaScript/CSS.

### 7.60 Jumlah BA Bongkar berupa bilangan bulat — 26 Oktober 2026 (belum di-commit)

- Input `Jumlah (buah)` sebelumnya memakai `min="0.001"` bersama `step="1"`, sehingga browser menaikkan nilai menjadi 1.001, 2.001, dst., walaupun backend sudah memvalidasi `integer`. Minimum quantity kini 1 dengan step tetap 1. Panjang kabel tetap boleh desimal; tidak ada pembulatan atau perubahan data lama.
- Regression memeriksa atribut form, penolakan jumlah 1.5/1.001, penerimaan jumlah 2, serta keluaran template PDF `Jumlah: 2 Buah` dan panjang 190.5 meter. `BaBongkarWorkflowTest` + `TechnicianMobileWorkflowTest`: **40 tests / 468 assertions** lulus. Pint file test, `view:cache`, `npm run build`, dan `git diff --check` lulus. Tidak ada migration atau write database operasional.

### 7.61 Ringkasan dan mode edit BA terpisah — 26 Oktober 2026 (belum di-commit)

- Tahap 6 teknisi kini menampilkan ringkasan/PDF dan Ajukan Approval setelah BA dibuat. Form hanya dirender setelah klik **Edit BA Bongkar** (`step=6&edit_ba=1`); heading BA yang berulang di ringkasan dihilangkan khusus pada halaman ini. Tombol Batal kembali ke ringkasan tanpa menyimpan perubahan. Saat mengedit, checklist/submit approval disembunyikan agar perubahan form tidak tertukar dengan BA tersimpan.
- BA baru/draft tanpa PDF langsung membuka form. Pilihan Tidak yang sudah tersimpan memiliki tombol **Ubah pilihan BA Bongkar**. Form edit mempertahankan nilai/foto/TTD tersimpan; kegagalan validasi BA membuka form kembali dengan old input. Error submit approval tidak otomatis membuka form. Sukses generate kembali ke ringkasan melalui redirect existing. Waiting Approval/Completed/Rejected tetap memakai halaman review tanpa editor, termasuk ketika query edit dikirim manual.
- Regression mencakup ringkasan, edit, batal tanpa perubahan revisi/PDF, validasi dengan old input, perubahan keputusan Tidak, dan larangan editor setelah submit. `BaBongkarWorkflowTest` + `TechnicianMobileWorkflowTest` + `TechnicianProjectReviewTest`: **52 tests / 716 assertions** lulus. Pint file test, `view:cache`, `npm run build`, dan `git diff --check` lulus. Tidak ada perubahan schema, write database operasional, permission, atau aturan approval.

### 7.62 Tata letak PDF BA lebih formal — 26 Oktober 2026 (belum di-commit)

- PDF memakai DejaVu Serif 11 pt (sebelumnya DejaVu Sans 9 pt), judul 14 pt, margin cetak yang disesuaikan, label identitas sejajar, serta tabel jenis/rincian material. Checkbox tetap memakai DejaVu Sans agar simbol terbaca. Baris material tidak dipecah antardua halaman; blok pernyataan/tanda tangan tetap utuh. Data panjang/banyak rincian tetap dapat menghasilkan beberapa halaman.
- Dua posisi atas sejajar untuk Yang Menyerahkan dan Yang Menerima, dengan ruang TTD serta nama/NIK masing-masing. **Kedua posisi bawah di bawah Mengetahui dikosongkan**, tanpa tulisan Admin, nama, NIK atau gambar TTD. Ini menggantikan pemetaan tampilan kiri bawah pada 7.58; aturan completion/penyimpanan TTD Admin dalam metadata tetap berlaku, hanya tidak dicetak di kedua kolom kosong tersebut.
- PDF contoh dengan dua rincian, TTD teknisi/penerima dan satu foto diperiksa visual melalui PDFKit: isi utama dan empat posisi TTD berada di halaman pertama; lampiran foto di halaman kedua. Contoh data uji: `/private/tmp/dompis-ba-preview/sample-final.pdf`. PDF existing tidak diubah massal; tampilan baru berlaku saat generate/penandatanganan menghasilkan PDF baru.
- Regression memastikan kedua kolom bawah kosong walaupun metadata Admin diberikan, sedangkan identitas teknisi/penerima tetap tampil. `BaBongkarWorkflowTest` + `BaImageTest`: **24 tests / 258 assertions** lulus, termasuk banyak rincian/12 foto dan tiga tahap penandatanganan. Pint file terkait, `view:cache`, `npm run build`, dan `git diff --check` lulus. Preview memakai SQLite in-memory; tidak mengubah database operasional, status, approval lama, atau file BA existing.

### 7.63 Material PDF tanpa tabel dan ruang TTD lebih lega — 26 Oktober 2026 (belum di-commit)

- Mengikuti koreksi user, tabel material pada 7.62 diganti susunan bagian A–G tanpa border/background tabel. Heading kelompok melekat pada rincian pertama; setiap rincian dijaga agar tidak terbelah halaman. DejaVu Serif 11 pt dan checkbox tetap dipertahankan.
- Jarak penutup ke judul TTD menjadi 8 mm, judul Yang Menyerahkan/Yang Menerima ke bidang TTD 5 mm, tinggi bidang atas 21 mm, jeda identitas atas ke Mengetahui 12 mm, jeda Mengetahui ke bidang bawah 7 mm, serta tinggi bidang bawah 22 mm. Padding kolom 9 mm menjaga ruang horizontal. Kedua posisi bawah tetap kosong tanpa label jabatan/nama/TTD.
- Blok penutup dan seluruh TTD tetap utuh. Dengan ruang yang lebih lega, contoh dua rincian/dua TTD/satu foto kini memiliki tiga halaman: isi BA, penutup/TTD, lalu lampiran foto. Tidak dipaksakan satu halaman dengan mengurangi font atau jarak. Diperiksa visual melalui PDFKit; contoh data uji terbaru `/private/tmp/dompis-ba-preview/sample-spaced-final.pdf`.
- `BaBongkarWorkflowTest` + `BaImageTest`: **24 tests / 258 assertions** lulus, termasuk banyak rincian/12 foto. `view:cache`, `npm run build`, dan `git diff --check` lulus. Perubahan hanya template PDF dan handover; tidak mengubah database operasional atau PDF lama. Generate ulang diperlukan untuk memakai tampilan terbaru.

### 7.64 Format acuan Karangdoro dan penghapusan Warehouse — 26 Oktober 2026 (belum di-commit)

- Requirement terbaru menggantikan flow Warehouse pada 7.58 dan perubahan layout 7.62–7.63. Role enum, route/dashboard/inbox/controller/middleware/view Warehouse dihapus; konfigurasi scope/user/import/menu kembali khusus Admin. User Management dan import membatasi pilihan ke role canonical. Login role yang tidak lagi didukung ditolak tanpa exception `UserRole::from`. Migration tambah Warehouse 23 Oktober tetap dipertahankan karena sudah ada di ledger; additive `2026_10_26_000001_remove_warehouse_role.php` menghapus role, kedua permission BA khusus Warehouse dan mapping-nya. Migration menolak jika akun masih memakai role, termasuk akun terarsip; down hanya mengembalikan definisi role/permission, bukan akun/approval.
- Tahap 6 tetap menentukan Ya/Tidak sebelum submit. Jika Ya, form/foto/TTD teknisi/PDF tetap tersedia; Tidak menyelesaikan tahap tanpa evidence dummy. Setelah submit, Admin/Super Admin dengan `approve_evidence` dan scope LOP memeriksa BA sebagai satu paket melalui `evidence-approval.ba-bongkar.review`. Approve/reject tidak membutuhkan TTD Warehouse atau Admin tambahan, tidak merender ulang PDF, dan memakai revision lock. Reject mengubah Waiting Approval ke Rejected melalui LopService. Revisi BA tidak menghapus approval evidence pekerjaan lain. Completion tetap memerlukan semua evidence reviewable approved, termasuk BA bila Ya. Jalur review generic tidak dapat memodifikasi paket BA sebagian. Decision No yang pernah diajukan sebagai Ya tetap tidak bisa dipakai melewati review.
- PDF mengikuti `plans/3JBR_REL_TNG_RELOK DESA KARANGDORO (BA BONGKAR).pdf`: Letter 612 × 792 pt, dua halaman formulir dengan posisi isian/checkbox yang diukur dari acuan, format hari/tanggal, bagian A–E di halaman 1, lanjutan Merk/kolom Tiang/5 rincian Drop Core/Barcode SCMT/tanda tangan di halaman 2, dan grid foto dua kolom setelahnya. Rincian material di luar slot, atribut seperti merek yang tidak mempunyai slot pada kelompok terkait, perbedaan tahun tiap rincian Drop Core, mitra, dan lokasi lebih dari 175 karakter disertakan pada halaman lanjutan agar data tidak hilang. PDF tidak lagi memakai tabel material atau font serif dari revisi sebelumnya.
- Carlito Regular/Bold/Italic disertakan pada `resources/fonts/carlito` untuk mendekati Calibri pada acuan; sumber resmi commit `3a810cab78ebd6e2e4eed42af9e8453c4f9b850a`, lisensi OFL ada dalam folder. Font dasar 9.24 pt, judul 11.76 pt, font-height ratio 0.82 dan margin/koordinat mengikuti contoh. Cache font berada di disk local privat `ba-bongkar/fonts`, akses remote PDF tetap nonaktif, chroot hanya storage privat/font yang diperlukan. Layout tidak bergantung pada file PDF acuan saat runtime. Jangan menghilangkan asset font/lisensinya saat deploy.
- Form menambah isian opsional Barcode SCMT, nama/jabatan/telepon penerima dan judul lampiran foto. Drop Core juga dapat mencatat jumlah unit berupa integer; canonical `ODP Padenstal` lama tetap diterima tetapi di PDF ditulis Pedestal sesuai acuan. Identitas/telepon teknisi berasal dari snapshot akun. Nama/NIK/TTD dari contoh tidak disalin. Dua posisi Mengetahui memakai judul jabatan sesuai acuan dengan nama/NIK/TTD kosong untuk diisi manual. Metadata tanda tangan lama tetap diarsipkan; tidak menjadi syarat approval lagi. PDF existing tidak ditimpa massal; format baru berlaku saat dibuat ulang.
- **MySQL lokal sudah diterapkan, batch 35:** setelah persetujuan eksekusi, akun Warehouse ID 22 dinonaktifkan dan `role_id` dilepas melalui `UserService::retireRemovedRole`, remember token dibersihkan, sesi akun diakhiri, dan histori `role_retired` dicatat oleh Super Admin ID 2. Akun beserta identitas/profil/histori lama tetap utuh; tidak otomatis dipindahkan ke role yang lebih tinggi. Role Warehouse dan dua permission-nya sudah tidak ada. Maintenance dipakai saat apply dan aplikasi sudah live kembali. Status 12 LOP tetap: Draft 7, Picked Up 1, Progress 3, Waiting Approval 1; tidak ada reset/review otomatis atau perubahan Revenue.
- Backup SQL privat sebelum operasi: `/private/tmp/dompis-remove-warehouse-20261026-032737/before.sql`, SHA256 `96f65c20a59f8284fea31e27d05ea0804ee7bf18d1f21fda66e7255911175de6`, manifest `before.json`/`after.json` di direktori sama. Perbandingan seluruh tabel memastikan LOP/evidence/approval/BOQ/snapshot/qty/assignment dan data lainnya tidak berubah. Verifikasi akhir read-only: role/permission Warehouse nol, akun 22 inactive tanpa role, schema/ledger sesuai, dan LOP 7 dengan BA pending dapat direview Admin. Backup memuat data sensitif: simpan privat dan jangan commit; `/private/tmp` bukan retensi permanen. Produksi belum diterapkan/diverifikasi.
- Contoh output data uji terbaru: `/private/tmp/dompis-ba-reference-preview/ba-bongkar-format-acuan.pdf`, tiga halaman (dua formulir + satu lampiran empat foto). Preview dibandingkan visual dan koordinat teks/font dengan acuan melalui PDFKit; alur Ya → submit → approve BA Admin → Completed juga diuji menggunakan SQLite in-memory. Ini bukan pengajuan/approval terhadap LOP operasional.
- Verifikasi final: **560 tests / 5748 assertions** backend dan **65 frontend tests** lulus. Regression mencakup Ya/Tidak, kelengkapan form, PDF privat/ICC, Admin scope empat level/legacy, review paket/stale/reject/revisi, tidak ada syarat TTD tambahan, penghapusan route/role/import, guard retirement akun/sesi/histori, layout acuan/kolom TTD manual, data di luar slot template, rincian tambahan dan 12 foto. Pint file terkait, `view:cache`, `npm run build`, dan `git diff --check` lulus. Tidak ada commit/push/deployment produksi; perubahan label MAGU milik user tetap dipertahankan.

### 7.65 Batas reported_date tiket Monitoring Incident — 26 Oktober 2026 (belum di-commit)

- Halaman **Monitoring Incident → Tiket Dompis** kini hanya membaca tiket dengan `reported_date` mulai **1 September 2026** sampai **hari ini (WIB)**. Tiket sebelum batas, tanggal kosong/tidak terbaca, dan tanggal masa depan tidak ikut dihitung, ditampilkan, maupun diunduh secara default. Ini menggantikan default "semua periode" pada 7.42; pengguna masih dapat mempersempit rentang atau memilih tanggal akhir eksplisit.
- Batas bawah berada di `config/incident-monitoring.php` (`INCIDENT_TICKET_REPORTED_FROM`, default `2026-09-01`) dan dinormalisasi oleh `TicketReportedDate::floor()`. `TicketCoverageRequest` menolak `date_from`/`date_to` sebelum batas; kedua input tanggal memakai `min` yang sama. `TicketCoverageService` memakai rentang efektif pada query jenis tiket, workzone, agregasi KPI/grafik, daftar, dan export Excel. Tanggal akhir default adalah hari ini bila tidak diisi.
- Batas ini khusus sumber tiket pada halaman Monitoring Incident. Lookup tiket per incident untuk tab Incident pada LOP dan form Input LOP tetap memakai incident spesifik agar LOP lama tidak kehilangan jenis tiketnya. Tidak ada migration, perubahan schema, atau penulisan ke `dompis_db`.
- Angka kartu **Tiket Exclude** diberi warna hijau tua (`text-emerald-800`, dark mode `dark:text-emerald-300`); angka Total tiket, Belum memiliki LOP, dan Sudah memiliki LOP tetap memakai warna netral sebelumnya.
- Pada tabel **Monitoring Exclude Tiket**, angka kolom Sudah Exclude (termasuk rincian alasan), header SUDAH MAPPING, dan badge Sudah exclude memakai hijau; kolom Belum Exclude tetap memakai brand merah. Subheader berulang **Sudah Exclude** memakai latar hijau muda dan garis bawah hijau, sedangkan **Belum Exclude** memakai latar merah muda dan garis bawah brand, sehingga pasangan kolom mudah dibedakan tanpa hanya mengandalkan warna angka. Badge **Tiket Exclude** pada daftar Tiket Dompis juga memakai hijau.
- Verifikasi: full suite `php -d memory_limit=1G vendor/bin/phpunit` **563 tests / 5783 assertions** lulus. Regression terkait: `TicketCoverageTest` + `IncidentExcludeTest` + `LopDirectDetailTest` **43 tests / 612 assertions**, `IncidentMonitoringTest` + `LopPermissionTest` **36 tests / 274 assertions**, `DataBoqDateFilterTest` **13 tests / 64 assertions**. Pint file task, `view:cache`, `npm run build`, dan `git diff --check` lulus. Bila server memakai config cache, jalankan `php artisan config:cache` setelah mengatur env dan clear/rebuild view cache.

### 7.66 Tabel Monitoring Exclude per regional — 26 Oktober 2026 (belum di-commit)

- Menu **Monitoring Incident → Monitoring Exclude** (`GET /incident-monitoring/exclusions`, Admin/Super Admin) menampilkan matrix sesuai acuan `plans/monitor_ticket_exclude.jpeg`: regional JATENG DIY, JAWA TIMUR, BALI NUSRA, TOTAL; jenis SQM/GAMAS, PREMAN, INFRACARE; jumlah sudah/belum exclude dan empat alasan canonical. Nama regional lain serta workzone kosong/tidak dikenal tetap masuk baris tersendiri agar total tidak kehilangan tiket. Kolom alasan lainnya muncul jika ada alasan legacy di daftar exclude.
- Sumber status adalah keberadaan incident yang sudah dinormalisasi pada `incident_excludes`, **terlepas dari keberadaan LOP**. Ini berbeda dengan KPI penggunaan di tab Tiket Dompis yang mendahulukan status sudah memiliki LOP. Label mapping pada gambar diterjemahkan menjadi sudah/belum exclude sesuai permintaan. SQM memakai klasifikasi GAMAS yang sudah ada; exact PREMAN/INFRACARE tetap mengikuti aturan sumber sebelumnya. Unit hitung adalah baris sumber `ticket`, konsisten antara matrix dan detail, termasuk jika incident terduplikasi pada sumber.
- Filter rentang tanggal atau bulanan memakai `ticket.reported_date` ISO/DD/MM/YYYY dan batas konfigurasi 7.65. Default rentang dari batas bawah hingga hari ini WIB; bulan eksplisit mencakup awal–akhir bulan, dipotong batas bawah bila perlu. Request menolak bulan/tanggal invalid atau sebelum batas; input tanggal tidak aktif diabaikan dalam mode bulanan. Filter region/branch/STO dan pencarian diterapkan bersama dengan scope canonical `LopVisibilityService`.
- Setiap angka, termasuk nol dan TOTAL, membuka daftar detail pada halaman yang sama dengan pilihan regional/jenis/status/alasan yang tepat. Filter global tetap berlaku; matrix tetap utuh ketika memilih sel. Detail dipaginasi 25 baris melalui SQL, pagination mempertahankan semua filter dan anchor detail. Tabel dapat digeser horizontal, kolom regional tetap terlihat, dan tautan angka memiliki label aksesibilitas. Sumber gagal dimuat ditandai tidak tersedia, bukan nol palsu.
- `TicketCoverageService::monitoringContext()`, `sourceQuery()`, normalisasi/klasifikasi, dan presentasi baris dipakai bersama agar pembatasan sumber, tanggal, dan wilayah tidak digandakan. Agregasi exclude berada di `TicketExcludeMonitoringService`, validasi di `TicketExcludeMonitoringRequest`. Tidak ada query di loop Blade, perubahan status LOP, schema/migration baru, atau write ke database operasional/sumber Dompis. Tabel `incident_excludes` dari 7.54 tetap prasyarat deployment.
- Verifikasi: full suite **575 tests / 6526 assertions** lulus; `TicketExcludeMonitoringTest` **12 tests / 743 assertions** mencakup seluruh 72 tautan sel, empat level scope/multi-STO/legacy, normalisasi, batas tanggal/bulan termasuk tahun kabisat, pagination/duplikasi, data tidak terpetakan, alasan legacy, outage, dan authorization. Pint file task, Blade cache, build Vite, dan diff check lulus. Pint seluruh repo masih melaporkan format lama pada **13 file di luar task**; tidak diubah. Preview browser data uji SQLite terpisah memverifikasi desktop 1440px, mobile 390px tanpa overflow halaman, dark mode, pergantian periode, dan tanpa exception JavaScript. MySQL/produksi belum diverifikasi atau dideploy pada task ini.

### 7.67 Capture tabel Monitoring Exclude — 26 Oktober 2026 (belum di-commit)

- Tabel **Monitoring Incident → Monitoring Exclude** memiliki tombol **Capture** dan pilihan **PNG / JPG**, menggunakan `dashboardRegionCapture` serta komponen `dashboard-capture-controls` yang sudah digunakan dashboard. Tidak ada handler/dependency baru. Capture memuat seluruh kolom dan baris matrix, judul, periode, scope/filter wilayah/pencarian aktif, serta waktu pembacaan. Nama file menyertakan identitas monitoring, scope, dan rentang tanggal.
- Target capture hanya section matrix pada `resources/views/incident-monitoring/exclusions.blade.php`. Form filter, daftar detail/pagination, kontrol capture, dan petunjuk klik tidak ikut gambar. Handler existing melebarkan clone tabel pada layar sempit, mempertahankan DOM/scroll/tautan asli, mengikuti tema, dan menangani retry/error/loading. Kontrol tidak ditampilkan ketika sumber tiket gagal dimuat. Header **SUDAH MAPPING** tetap rata tengah dan kalimat penjelasan panjang yang diminta dihapus tidak dikembalikan.
- Verifikasi: `TicketExcludeMonitoringTest` + `AdminDashboardTest` **22 tests / 825 assertions** dan **8 frontend capture tests** lulus, termasuk integrasi Admin/Super Admin dengan filter serta batas area capture. Pint test terkait, view cache, build Vite, dan diff check lulus. Browser dengan data uji SQLite terpisah menghasilkan PNG desktop **3782×974**, JPG mobile dan PNG dark mode **3782×958**; seluruh 19 kolom utuh meskipun tabel asli digeser, 72 tautan tetap ada, scroll asli tetap, dan tidak ada exception JavaScript. Tidak ada perubahan query, authorization, schema/migration, write database operasional, commit/push, atau deployment.

### 7.68 Batalkan exclude tiket — 26 Oktober 2026 (commit `5f30637`)

- Tiket yang sudah terlanjur di-exclude dapat dikembalikan melalui tombol ikon **Batalkan exclude** pada tab **Tiket Exclude** di halaman Monitoring Incident (mobile dan desktop) serta kolom **Aksi** pada detail **Monitoring Exclude**. Dialog konfirmasi menjelaskan bahwa tiket kembali ke daftar **Belum memiliki LOP**, alasan exclude dihapus, dan tiket dapat digunakan lagi; pilihan **Tidak** menutup dialog tanpa perubahan.
- Komponen `resources/views/components/unexclude-ticket-button.blade.php` memakai `<dialog>` native dengan ID unik per render, sehingga aman ketika baris yang sama tampil di daftar mobile dan tabel desktop. Tombol kini berupa ikon saja; teks **Batalkan** muncul sebagai tooltip saat hover/focus dan `aria-label` menyebut incident lengkap. Form mengirim `DELETE` ke route existing `incident-monitoring.tickets.unexclude` dengan CSRF dan konfirmasi dua langkah.
- Route `DELETE /incident-monitoring/tickets/{incident}/unexclude` kini memakai `UnexcludeTicketRequest`: otorisasi Gate `view-incident-monitoring` dan validasi nomor incident maksimal 100 karakter. `TicketCoverageService::unexclude()` mengembalikan `bool`; controller menampilkan pesan sukses atau pesan idempoten bila baris sudah tidak ada. Normalisasi `UPPER(TRIM())` tetap dipakai, sehingga incident huruf kecil/spasi tetap cocok.
- Hanya baris `incident_excludes` yang dihapus. Tidak ada migration/schema baru, perubahan status LOP, penulisan ke sumber Dompis, atau perubahan perhitungan KPI/status. Untuk Admin, service memverifikasi workzone tiket pada sumber Dompis terhadap `LopVisibilityService` sebelum exclude/unexclude; Super Admin melewati pemeriksaan scope. Bila sumber gagal dibaca, controller membalas 503, bukan error 500. Otorisasi halaman tetap Admin/Super Admin dan daftar dibatasi scope wilayah.
- Verifikasi: full suite **581 tests / 6592 assertions** lulus. Regression terkait: `IncidentExcludeTest` **14 tests / 81 assertions** (termasuk otorisasi Admin/Super Admin/teknisi, scope di luar akses, normalisasi, idempotensi, dan tombol di kedua halaman) serta `TicketCoverageTest` + `TicketExcludeMonitoringTest` **43 tests / 1278 assertions**. Pint file task, `view:cache`, `npm run build`, dan `git diff --check` lulus.

### 7.69 Optimasi query daftar LOP dan Data BOQ — 26 Oktober 2026 (commit `92a7c96`)

- Active LOP dan History melakukan eager load `boq.items.designator` agar `ProjectProgressService`/`ReservedDesignatorRules` tidak mengambil rincian BOQ per baris. Query/filter, pagination, ringkasan progress/approval, serta Blade tetap memakai aturan existing.
- `LopVisibilityService::prepareForView()` menghitung akses seluruh model pada satu halaman dengan query scope canonical. Hasil disimpan di atribut request sebagai WeakMap per objek model, dengan konteks user/scope/pivot-STO/lokasi model. `canAccess()` memakainya hanya pada GET yang telah dipersiapkan; user/model/konteks berbeda kembali ke query canonical. POST/PUT/PATCH/DELETE selalu membaca akses terbaru. Cache tidak disimpan pada model, session, singleton, maupun lintas request. Dipakai pada Active LOP, History, dan Master Data LOP; policy/Gate serta aturan empat level scope dan fallback branch/STO legacy tetap berlaku.
- `LopBoqValueService::summarizeMany()` memuat relasi BOQ, lokasi, region, dan reservasi secara berkelompok untuk halaman Data BOQ. `LopBoqProjectionService::withPreparedPrices()` menggabungkan pengambilan harga per paket/designator dan lookup pasangan jasa, lalu menjalankan perhitungan canonical yang sama. Konteks harga hanya hidup selama callback dan dipulihkan melalui finally, termasuk saat exception; pemanggilan berikutnya membaca harga terbaru. Prioritas snapshot/paket wilayah/referensi, jasa virtual, harga nol/hilang, qty_actual nol/null, dan struktur DTO dipertahankan. Tidak ada cache harga lintas request.
- Pengukuran ulang fixture audit SQLite yang sama: Active LOP 20 baris **268 → 32 query**, Master Data LOP 20 baris **136 → 18**, Data BOQ 15 reservasi **134 → 22**. Jumlah query pada halaman ini tidak lagi bertambah per baris untuk akses/relasi/harga yang dibatch. Angka ini jumlah query data uji, bukan klaim persentase percepatan MySQL produksi. Audit kandidat lanjutan (pagination laporan material, referensi global/parse tanggal Monitoring Tiket, agregasi tab Summary Dashboard, dan agregasi Revenue) belum diubah pada tahap ini; indeks perlu audit schema dan EXPLAIN di environment tujuan sebelum diputuskan.
- Regression `QueryOptimizationTest`: **10 tests / 117 assertions**, termasuk budget query halaman, kesamaan ringkasan, scope Area/Region/Branch/multi-STO dan legacy, pembatasan cache ke GET/user/model/request, perubahan scope/lokasi, batch vs single, snapshot/paket/referensi/jasa virtual/nol/null/harga hilang, penyegaran harga, dan cleanup exception. Full suite **591 tests / 6709 assertions** lulus; Pint semua file task, `view:cache`, dan `git diff --check` lulus. Tidak mengubah Blade/JavaScript, tidak menjalankan migration, write database operasional/sumber Dompis, restart worker, commit/push, atau deployment. MySQL/produksi belum diverifikasi.

### 7.70 Label scope global disembunyikan pada Monitoring Exclude — 26 Oktober 2026 (commit `8978487`)

- Header **Monitoring Exclude Tiket** tidak lagi menampilkan teks **Seluruh wilayah** untuk Super Admin. Label scope Admin (Area, Region, Branch, atau Service Area) tetap ditampilkan karena masih relevan.
- Nilai yang sama tidak dimasukkan ke context capture maupun nama file capture. Untuk Super Admin, nama file menjadi `monitoring-exclude-tiket-<tanggal_awal>-<tanggal_akhir>`; untuk Admin, label scope tetap disertakan.
- Tidak ada perubahan query, data, authorization, atau teks pada halaman lain. Teks **Seluruh wilayah operasional** pada menu akun global tidak diubah karena berada di luar konten Monitoring Exclude.
- Verifikasi: full suite **592 tests / 6715 assertions** lulus; `IncidentExcludeTest` **15 tests / 87 assertions**. `view:cache`, `npm run build`, dan `git diff --check` lulus.

### 7.71 Matriks Monitoring Exclude per Service Area/STO untuk Admin Branch dan Service Area — 27 Oktober 2026 (commit `3402d79`)

- Admin dengan scope **Branch** (termasuk fallback legacy `branch_id`) dan **Service Area** kini melihat baris matriks berdasarkan **STO/workzone**, bukan region. Baris STO dikelompokkan menurut `service_areas.name` seperti matrix Program Dashboard: grup dapat dilipat, menampilkan jumlah STO dan agregat setiap kolom, serta default terbuka bila total STO <= 10. Header kolom menjadi **SERVICE AREA / STO**; Admin Area, Admin Region, dan Super Admin tetap memakai baris regional.
- Setiap sel STO melakukan drill-down ke tiket pada STO tersebut melalui key internal `matrix_region=sto:<WORKZONE>`. URL filter region lama pada akun Branch/Service Area tidak memperlebar data dan menghasilkan nol tiket.
- Pengelompokan dipilih dari effective scope (`admin_scope_type`, fallback `branch_id`) di `TicketExcludeMonitoringService`. `TicketCoverageService::monitoringContext` kini ikut memilih kolom `service_areas.name` agar label grup tersedia; batas data tetap berasal dari `TicketCoverageService` dan `LopVisibilityService`. Tidak ada perubahan schema, data, authorization, atau query sumber.
- Baris sel matriks diekstrak ke `resources/views/incident-monitoring/_exclude-matrix-row.blade.php` agar dipakai bersama mode regional dan mode grup. Capture tetap menyertakan ringkasan grup; tombol buka/lipat dikecualikan dari capture.
- Kolom **Belum Exclude** memakai latar merah brand: header `bg-brand-500` dengan teks putih, sel data `bg-brand-100/70` dengan teks merah gelap, serta varian gelap yang tetap kontras.
- Regression `TicketExcludeMonitoringTest`: **14 tests / 809 assertions**; full suite **593 tests / 6750 assertions**. `view:cache`, `npm run build`, Pint file task, dan `git diff --check` lulus.

### 7.72 Capture Ticket Insera oleh admin dari Input LOP dan Data LOP — 27 Oktober 2026 (belum di-commit)

- Admin dan Super Admin dapat menyiapkan **Capture Ticket Insera** saat membuat LOP tunggal melalui section **02B** pada form Input LOP Baru, atau setelah LOP dibuat/diimport melalui aksi kamera pada **Master Data → Data LOP**. Aksi pada Data LOP tersedia selama status LOP belum `completed`.
- Upload dari Data LOP memakai route `data-lops.insera.store` dan `StoreInseraEvidenceRequest`: wajib role admin-level, lolos `uploadEvidence` beserta scope lokasi, status non-completed, maksimal 12 gambar JPG/PNG/WEBP, dan 10 MB per file. Modal menampilkan capture yang sudah tersimpan, preview, status review, dan tombol hapus untuk evidence pending/rejected; filter halaman dipertahankan melalui `return_url`.
- Form create menerima `insera_files[]` opsional. Bila ada file, LOP dan evidence disimpan lalu admin diarahkan ke Data LOP dengan filter incident. Bila upload evidence gagal, LOP tetap tersimpan (capture bersifat opsional) dan admin diminta mengunggah ulang dari Data LOP. Tanpa file, alur lama tetap kembali ke form Input LOP Baru.
- File disimpan melalui `EvidenceService::uploadInsera()` sebagai `category=insera`, `step=SURVEY`, `type=PHOTO`, dan status **pending**. File langsung terlihat pada Step 3 · Evidence Pra teknisi sehingga teknisi tidak perlu mengunggah ulang, tetapi tetap masuk antrean review admin seperti evidence lain. Upload juga membatalkan pilihan **Tidak Ada** bila sebelumnya tersimpan.
- Tidak ada schema, migration, data operasional, atau source Dompis yang diubah. Komponen UI baru: `resources/views/components/admin-insera-modal.blade.php`; request baru: `app/Http/Requests/StoreInseraEvidenceRequest.php`.
- Regression `AdminInseraEvidenceTest`: **5 tests / 24 assertions** (upload admin + tampil di Step 3, create dengan file, validasi non-gambar, guard scope/teknisi/completed, dan aksi UI hanya untuk LOP non-completed). Full suite **598 tests / 6774 assertions**; `view:cache`, `npm run build`, dan `git diff --check` lulus.

### 7.73 Material lainnya dan perapian pilihan Tiang pada BA Bongkar — 27 Oktober 2026 (belum di-commit)

- Form Step 6 teknisi menyediakan **Material lainnya (opsional)** berupa teks bebas maksimal 2.000 karakter, dengan contoh **patchcore 20 meter (1 buah)**. Nilai dipertahankan saat menyimpan draft, mengedit BA, dan menampilkan ringkasan teknisi maupun review Admin. Teks ditampilkan dengan escaping HTML.
- Field `form.other_materials` disimpan melalui alur BA yang sudah ada dalam JSON `qe_lops.ba_bongkar_data` dan snapshot metadata dokumen. Tidak ada migration, backfill, atau perubahan data operasional. BA dapat dibuat hanya dengan material lainnya tanpa baris material A–G; bila keduanya kosong, generate tetap ditolak. Persyaratan foto, TTD teknisi, dan approval Admin tetap berlaku.
- PDF menambahkan **H. Material lainnya** di bawah **G. Drop Core**. Keterangan panjang memiliki pratinjau ringkas dan dicetak lengkap pada halaman rincian tambahan; teks tanpa spasi juga membungkus. Blok penutup dan tanda tangan digeser bersama agar tidak bertabrakan dengan bagian baru. PDF lama tetap arsip; format baru berlaku setelah generate ulang melalui workflow yang sah.
- Bagian **F. Tiang** kini memakai tiga kolom dengan posisi heading, kotak centang, dan jarak baris yang konsisten: **Tinggi Tiang**, **Jenis Tiang**, dan **Kondisi**. Centang berada di dalam kotaknya. Verifikasi visual PDF dilakukan untuk teks pendek, teks multiline panjang, dan 2.000 karakter tanpa spasi; tanda tangan tetap berada pada halaman formulir kedua.
- Lima regression baru pada `BaBongkarWorkflowTest` mencakup roundtrip draft/edit/review, material lainnya sebagai satu-satunya material sampai approval/completed, validasi teks/kebutuhan material, escaping/urutan PDF, serta kelengkapan teks pada halaman lanjutan termasuk teks multibyte.
- Verifikasi lokal: full suite **603 tests / 6819 assertions**, tiga test frontend BA, Pint file PHP task, `view:cache`, `npm run build`, dan `git diff --check` lulus. Perubahan belum di-commit atau di-deploy ke produksi.
- Penyempurnaan identitas penerima: nama tetap pada baris pertama, sedangkan jabatan dan telepon digabung pada baris kedua dengan pemisah ` · ` (contoh **SECURITY · 081212341234**). Field kosong tidak menghasilkan pemisah tambahan. Menghapus baris telepon terpisah memberi jarak lebih lega sebelum **Mengetahui** dan dua TTD bawah. Template `ba-bongkar/pdf.blade.php`, regression referensi PDF diperbarui; **28 test BA / 329 assertions**, Pint file test, `view:cache`, build, dan pemeriksaan visual PDF lulus. Tanpa perubahan database; PDF tersimpan perlu dibuat ulang melalui alur edit BA agar memakai tata letak ini.

## 8. Status Masalah dan Pekerjaan Belum Selesai

### Prioritas tinggi

- BA Bongkar terbaru mengikuti 7.64: **Warehouse sudah dihapus di kode dan MySQL lokal (batch 35)**. Produksi memerlukan commit/deployment, backup, audit ledger/akun, migration schema BA dan penghapusan role sesuai urutan ledger setelah izin database. Akun Warehouse yang masih ada harus ditangani eksplisit sebelum migration penghapusan; jangan membuat akun Warehouse baru. Jangan mengulang aktivasi/reset LOP 7.58 pada dataset yang sudah ditandai. PDF lama tetap arsip dengan layout saat dibuat; gunakan proses generate ulang yang sah bila memerlukan format terbaru.

- **Migration `2026_10_20_000001_create_incident_excludes_table.php` wajib dijalankan di produksi** sebelum fitur exclude tiket (7.54) dipakai. Query `IncidentExclude` ada di luar blok `try/catch`, jadi tanpa tabel ini halaman Monitoring Ticket error 500. Sudah Ran di lokal; jalankan setelah commit siap dan backup/tag tersedia.
- **Migration `2026_10_20_000002_add_reported_date_to_qe_lops_table.php` wajib dijalankan di produksi, diikuti `php artisan dompis:sync-reported-date`** (7.56). Tanpa kolomnya, INSERT pembuatan/edit LOP gagal (`unknown column reported_date`) sehingga seluruh workflow LOP berhenti, dan filter tanggal Data BOQ ikut error. Sudah Ran + tersinkron di lokal (12 LOP); status produksi belum diverifikasi.
- Migration `2026_10_16_000001_add_optional_capture_choices_to_qe_lops_table.php` belum dijalankan; minta izin sebelum aktivasi penyimpanan Tidak Ada. Schema/ledger MySQL lokal dan produksi belum diverifikasi pada task ini.
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
598 tests passed
6774 assertions
65 frontend tests passed
```

Test memakai SQLite `:memory:` dan queue `sync`, sehingga tidak membuktikan konfigurasi MySQL, Nginx, storage permission, atau worker produksi.

Pemetaan test utama:

- Detail langsung dari dashboard/tiket: `LopDirectDetailTest` (4 cases), `AdminDashboardTest`, `TicketCoverageTest`; navigasi legacy: `LopReturnNavigationTest`.
- Fallback lokasi/no-op/DOM Continue/PHP paste: `TechnicianMobileWorkflowTest` (22 cases). Hasil chunk Bulk LOP/penyebab/filter/file/job/dispatch: `BulkLopLatestRulesTest` (10 cases).
- Lokasi opsional/lanjut tanpa survey: `TechnicianMobileWorkflowTest`; frontend tempel/GPS: `technician-location.test.mjs` (8 cases).
- Material Tiba per plan Relok Utilitas: `MaterialArrivalPerDesignatorTest` (12 cases); pembatasan Import AISS/menu/worker/scope: `AissImportTest` (21 tests).
- Designator khusus/pilihan capture/hapus evidence: `TechnicianSpecialDesignatorAndEvidenceTest` (21 cases); frontend evidence-optional.test.mjs (7) dan boq-editor.test.mjs (5).
- Download Data LOP sesuai filter: `DataLopExportTest` (8 tests).
- Monitoring tiket dan download Excel: `TicketCoverageTest`; exclude tiket beserta alasan dan tab `excluded`: `IncidentExcludeTest` (8 tests); monitoring Incident pada LOP: `IncidentMonitoringTest`.
- BOQ AISS per Completed: `AissBoqExportTest` (7 tests, ekspor tanpa template/header/font/lebar/actual/snapshot/paket/role/scope/legacy/null/zero/arsip).
- Validasi incident/program: `IncidentProgramValidationTest` (18 tests, termasuk TSEL/PREMAN dan upload CSV/XLSX melalui job); frontend `lop-form.test.mjs` (16 tests).
- Download Data BOQ: `DataBoqExportTest` (6 tests, XLSX/snapshot/aktual/null/zero/role/scope/legacy/pagination/arsip).
- Filter tanggal reported date pada Master Data → BOQ, termasuk Download Excel: `DataBoqDateFilterTest` (13 tests, inklusif dua batas/rentang terbuka/list reservasi/guard scope/salinan saat create/penyegaran saat edit/sync backfill/tautan unduh/isi Excel/validasi rentang/tampilan kolom Reported Date/filter tetap jalan saat sumber tiket mati).
- Kolom Reported Date pada tabel master dan export Data BOQ: `DataBoqExportTest` (6 tests, posisi kolom/formula-injection/nilai nol dan kosong/scope/pagination/arsip).
- LOP dan permission: `LopWorkflowTest`, `LopPermissionTest`, `LopManualInputTest`.
- Autentikasi/session: `LoginTest`, `SessionExpiryTest` (CSRF diaktifkan pada regression), `LogoutConfirmationTest`, `AccountPasswordTest`; frontend auth-session.test.mjs (11 passed), seluruh frontend (20 passed).
- Pembatalan/arsip LOP semua status dan Active LOP pembuat: `LopCancellationTest` (termasuk retensi BOQ/evidence/file, stale actions, dan exclusion Revenue).
- Scope admin/project status: `AdminScopeAndProjectStatusTest`.
- Teknisi mobile: `TechnicianMobileWorkflowTest`, `TechnicianProjectReviewTest`.
- Evidence/storage: `EvidenceWorkflowTest`, `EvidenceAsyncUploadTest`, `EvidencePermissionTest`, `AdminInseraEvidenceTest`.
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
