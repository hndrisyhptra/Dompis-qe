# Dompis QE Development Guide

## Project Identity

Nama aplikasi:
Dompis QE

Framework:
Laravel

Tujuan:
QE Operational Management System untuk pengelolaan pekerjaan Quality Enhancement (QE) berbasis LOP.

Aplikasi menangani:

- Input LOP
- Assignment teknisi
- Survey lapangan
- Material reservation
- Evidence management
- Approval workflow
- Reporting
- Audit trail


---

# Development Philosophy

Jangan melakukan implementasi langsung tanpa memahami existing code.

Setiap task harus melalui:

1. Requirement Audit
2. Existing Code Audit
3. Database Impact Analysis
4. Implementation Plan
5. Coding
6. Testing
7. Documentation


---

# Mandatory Workflow

Sebelum membuat perubahan:

WAJIB memberikan:

## Requirement Analysis

Berisi:

- tujuan fitur
- user flow
- role yang terlibat
- tabel yang terdampak
- risiko implementasi


## Technical Plan

Berisi:

- file yang dibuat
- file yang diubah
- migration impact
- model relationship
- controller/service impact


Jangan coding sebelum analisis selesai.


---

# Application Architecture

Gunakan pola:

Controller
    |
Service Layer
    |
Repository / Model
    |
Database


Business logic jangan ditempatkan langsung di Controller.


---

# Database Rules

Semua tabel:

- gunakan foreign key
- gunakan index
- gunakan timestamps
- gunakan soft delete untuk data penting
- gunakan audit trail


Jangan membuat tabel redundant.


---

# Main Module


## LOP Management

Entity:

qe_lops

Workflow:

draft
assigned
picked_up
survey
progress
waiting_approval
completed
rejected


---

## Assignment

Gunakan:

qe_lop_assignments


Jangan menyimpan technician_id langsung di qe_lops.


---

## Evidence

Semua evidence menggunakan:

qe_evidences


Jangan membuat:

evidence_before
evidence_after

secara terpisah.


Field:

step

type

designator_id

file_path

metadata


---

## Survey

Survey menyimpan:

- koordinat
- lokasi pekerjaan
- KML


Gunakan:

qe_surveys

qe_survey_points


---

# User Role

Role utama:

SUPER_ADMIN

ADMIN

TEKNISI

APPROVER

VIEWER


Setiap fitur harus mempertimbangkan permission.


---

# Security Rules

Wajib memperhatikan:

- authorization
- policy
- validation
- upload security
- audit log


File upload:

- validasi extension
- validasi mime type
- limit size
- generate filename aman


---

# Coding Standard

Gunakan:

- Laravel convention
- Form Request validation
- Service class
- Resource untuk API
- Clean naming


---

# Testing Requirement

Setiap fitur baru minimal:

- migration test
- permission test
- workflow test


---

# Dompis QE Menu


Dashboard


Inbox:

- Active LOP
- History


Program:

- QE Recovery
- QE Preventive
- QE Relok Utilitas


Master Designator:

- Designator
- KHS
- Paket KHS


Master Data:

- Semua LOP


Approval Evidence


User Management


---

# Future Integration

Siapkan desain agar mudah dikembangkan:

- Telegram Notification
- KML Generator
- DXF Generator
- BOM Generator
- Dashboard Analytics

# Authentication & User Management


## Authentication Requirement

Dompis QE menggunakan custom user authentication.

Jangan menggunakan default Laravel users structure tanpa penyesuaian.


Login menggunakan:

username = NIK


Contoh:

Username:
12345678

Password:
********


---

# Database Authentication


## roles table


roles

id_role

role_name

description

created_at

updated_at



Role:


SUPER_ADMIN

ADMIN

TEKNISI

MANAGER

APPROVER



---

## users table


users


id_user


role_id


nik


name


username


password


email nullable


phone nullable


branch_id nullable


status


last_login_at


remember_token


created_at


updated_at



Rules:


username harus menggunakan NIK.


nik harus unique.


password menggunakan Laravel hashing.


User tidak boleh login jika status inactive.



---

# Role Permission


Gunakan RBAC.


roles

    |

permissions

    |

role_permissions



Permission harus granular.


Contoh:



SUPER_ADMIN:

- manage_users
- manage_roles
- manage_master_data
- system_setting



ADMIN:

- create_lop
- assign_lop
- approve_evidence



TEKNISI:

- view_assigned_lop
- pickup_lop
- upload_evidence
- survey



MANAGER:

- view_dashboard
- monitoring_progress
- reporting



APPROVER:

- review_evidence
- approve_evidence



---

# Authentication Security


Implementasikan:


- Laravel Hash password
- CSRF protection
- Login throttling
- Session regeneration
- Logout invalidate session
- Authorization middleware
- Policy/Gate


---

# Login Flow


User membuka login page.


Input:

NIK

Password



System:

1. Cari username berdasarkan nik

2. Validasi password

3. Cek status user

4. Load role permission

5. Redirect berdasarkan role



---

# Redirect After Login


SUPER_ADMIN:

Dashboard Admin


ADMIN:

Dashboard Operasional


TEKNISI:

My Assigned LOP


MANAGER:

Dashboard Monitoring


APPROVER:

Approval Evidence

# Dompis QE UI Design System


## Product Design Direction

Dompis QE adalah aplikasi enterprise internal untuk operasional Quality Enhancement.

UI harus memberikan kesan:

- Professional
- Reliable
- Corporate
- Operational System
- Clean
- Easy to use


Referensi:
Gunakan gaya visual Dompis Cons sebagai baseline.

Dompis QE harus terlihat sebagai satu keluarga produk dengan Dompis Cons tetapi memiliki tampilan lebih modern.


Hindari:

- AI generated look
- Excessive gradient
- Glassmorphism berlebihan
- Neon color
- Gaming style
- Animasi yang tidak diperlukan


Prioritas:

Usability > Decoration


---

# UI Technology

Gunakan:

- Laravel Blade
- Tailwind CSS
- Alpine.js jika diperlukan


Gunakan komponen reusable.


Contoh:

components:

- button
- input
- card
- modal
- table
- badge


---

# Login Page Standard


## Layout Desktop


Gunakan split layout.


Left Section:

Berisi:

- Logo Dompis QE
- Nama aplikasi
- Deskripsi singkat


Contoh:

"Platform Monitoring dan Pengelolaan Quality Enhancement berbasis operasional lapangan."


Tambahkan ilustrasi sederhana:

Tema:

- field operation
- network quality
- engineering workflow


Tidak menggunakan ilustrasi AI yang terlalu kompleks.


---

Right Section:


Login Card:


Komponen:


- Input NIK
- Input Password
- Remember Me
- Button Login


Card:

- rounded modern
- soft shadow
- clean spacing
- typography jelas


---

# Mobile Design


Harus responsive:


Desktop:

split layout


Mobile:

single column


Login card:

- full width
- nyaman digunakan
- padding cukup


---

# Color Direction


Gunakan warna:

- mengikuti branding Dompis
- profesional
- corporate


Jangan membuat palette baru tanpa alasan.


---

# UX Rules


Setiap halaman harus:

- memiliki hierarchy jelas
- loading state
- error state
- empty state
- responsive


Form:

- label jelas
- validation message
- feedback setelah action


---

# Before Creating UI


Sebelum membuat halaman:


Analisa:

1. User role
2. User flow
3. Data yang ditampilkan
4. Action user
5. Responsive behavior


Kemudian buat implementasi.


# Master Designator Management


## Purpose

Master Designator adalah modul pusat untuk mengelola seluruh item pekerjaan QE.

Designator digunakan sebagai referensi untuk:

- Reservasi material teknisi
- Penyusunan BOQ pekerjaan
- Perhitungan harga pekerjaan
- Evidence berdasarkan item pekerjaan
- Reporting pekerjaan
- Integrasi KHS / Paket pekerjaan
- Generate RAB / Cost Calculation


Designator TIDAK boleh dibuat secara hardcoded di aplikasi.

Semua item pekerjaan harus berasal dari database.


---

# Designator Data Flow


Flow utama:


Customer

    |

    |

Package

    |

    |

Designator

    |

    |

Designator Price


Kemudian digunakan:


LOP

    |

    |

BOQ Items

    |

    |

Evidence berdasarkan Designator



---

# Database Structure


## 1. customers


## Purpose

Master customer atau owner pekerjaan.


Contoh:

- Telkom
- PLN
- Customer lainnya



Table:


customers


Fields:


id_customer


customer_code


customer_name


description


is_active


created_at


updated_at



Relationship:


Customer

hasMany

Packages



Customer

hasMany

Designators



Rules:


- customer_code harus unique
- customer yang sudah digunakan tidak boleh dihapus
- gunakan is_active untuk deaktivasi



---

# 2. packages


## Purpose


Master paket pekerjaan.


Package digunakan untuk menentukan kelompok pekerjaan dan harga.


Contoh:


Package:

QE Recovery Paket 5


Package:

QE Preventive Paket A


Package:

Relokasi Utilitas CAPEX



Table:


packages


Fields:


id_package


customer_id


package_code


package_name


description


is_active


created_at


updated_at



Relationship:


Package belongsTo Customer


Package hasMany Designator Prices



Rules:


- package_code unique dalam customer
- package tidak boleh dihapus jika sudah digunakan BOQ
- gunakan soft delete atau is_active



---

# 3. designators


## Purpose


Master item pekerjaan.


Designator menyimpan informasi dasar pekerjaan tanpa harga.


Harga TIDAK disimpan pada tabel ini.


Contoh:


Designator:

M-Rak Pasif spliter 1:4


Uraian:

19 inch 24 core Pull type optical fiber distribution frame 24 port Rack Mounted Indoor fiber patch panel



Satuan:

pcs


Type:

jasa


Kategori:

Passive Optical Component



Table:


designators


Fields:


id_designator


customer_id


designator


uraian_pekerjaan


satuan


type


category_id


is_active


created_at


updated_at



Relationship:


Designator belongsTo Customer


Designator belongsTo Category


Designator hasMany Designator Prices


Designator hasMany BOQ Items



Rules:


- designator harus unique berdasarkan customer
- jangan menyimpan harga pada tabel designators
- uraian pekerjaan menjadi master description



---

# 4. designator_categories


## Purpose


Master kategori designator.


Jangan menggunakan text bebas untuk kategori.


Contoh:


- ODP
- ODC
- Kabel
- Material
- Jasa
- Testing
- Dismantle



Table:


designator_categories


Fields:


id_category


name


description


is_active


created_at


updated_at



Relationship:


Category hasMany Designators



---

# 5. designator_types


## Purpose


Master tipe pekerjaan.


Digunakan agar type tidak berupa text bebas.


Contoh:


- Material
- Jasa
- Instalasi
- Pengukuran
- Dismantle



Table:


designator_types


Fields:


id_type


name


description


is_active


created_at


updated_at



Relationship:


Type hasMany Designators



---

# 6. designator_prices


## Purpose


Menyimpan harga designator berdasarkan paket.


Satu designator dapat memiliki harga berbeda tergantung package.


Contoh:


Designator:

M-Rak Pasif spliter 1:4


Package:

5


Price:

397068



Table:


designator_prices


Fields:


id_price


designator_id


package_id


price


effective_date


expired_date


created_by


updated_by


created_at


updated_at



Relationship:


Designator Price belongsTo Designator


Designator Price belongsTo Package



Rules:


- harga tidak boleh disimpan di designators
- satu kombinasi designator + package hanya boleh memiliki satu harga aktif
- perubahan harga harus memiliki histori



---

# Excel Import Designator


## Purpose


Master Designator dapat diimport menggunakan Excel.


Template Excel wajib:



Header:


designator

uraian pekerjaan

satuan

type

paket

harga



Example:


designator:

M-Rak Pasif spliter 1:4



uraian pekerjaan:

19 inch 24 core Pull type optical fiber distribution frame 24 port Rack Mounted Indoor fiber patch panel, Include RS232 Passive Splitter Rackmount Chassis - 2U



satuan:

pcs



type:

jasa



paket:

5



harga:

397068



---

# Import Process


Saat upload Excel:


## Step 1

Validasi template.


System harus memastikan:


- Semua header tersedia
- Format file benar
- Data tidak kosong



Jika gagal:

tampilkan error import.



---

## Step 2

Validasi Customer


Import harus memiliki customer_id.


Semua designator berada dalam customer tertentu.



---

## Step 3

Process Package


Kolom:


paket


digunakan sebagai:


packages.package_code



Jika package belum tersedia:


Option:


1. Auto create package


atau


2. Tampilkan error untuk dibuat manual



---

## Step 4

Process Designator


Cari berdasarkan:


customer_id

+

designator



Jika belum ada:


Create designator baru.



Jika sudah ada:


Update:


- uraian pekerjaan
- satuan
- type



---

## Step 5

Process Price


Insert/update:


designator_prices



Mapping:


designator_id


package_id


price



---

# Import History


Setiap import harus tercatat.


Table:


designator_import_logs



Fields:


id_import


customer_id


file_name


total_row


success_row


failed_row


uploaded_by


created_at



---

# Import Error Log


Untuk menyimpan data gagal.


Table:


designator_import_errors



Fields:


id


import_id


row_number


column_name


value


message


created_at



Example:


Row:

15


Column:

paket


Message:

Package 5 tidak ditemukan



---

# BOQ Integration


Designator digunakan saat membuat BOQ.


Flow:


LOP

↓

Pilih Package

↓

Load Designator berdasarkan Package

↓

Input Quantity

↓

Generate BOQ



---

# boq_items


## Purpose


Menyimpan snapshot pekerjaan pada LOP.


BOQ tidak boleh mengambil harga realtime dari master.


Saat BOQ dibuat:


copy:


- designator
- uraian pekerjaan
- satuan
- harga



Table:


boq_items


Fields:


id_boq


lop_id


designator_id


designator


uraian_pekerjaan


satuan


quantity_actual


unit_price


total_price


created_at


updated_at



Relationship:


BOQ belongsTo LOP


BOQ belongsTo Designator



---

# BOQ Template (Recommended)


Untuk mempercepat input pekerjaan.


## boq_templates


Fields:


id_template


package_id


name


description


is_active



Relationship:


Package hasMany Templates



---

## boq_template_items


Fields:


id


template_id


designator_id


default_quantity



Relationship:


Template hasMany Designator



Example:


Package:

QE Recovery


Template:


Standard Recovery



Items:


ODP

Kabel FO

Closure

Accessory



---

# Security Rules


Master Designator hanya dapat dikelola:


SUPER_ADMIN



Admin hanya dapat:


- menggunakan designator
- membuat BOQ



Teknisi:


- melihat designator dari LOP
- upload evidence berdasarkan designator



---

# Audit Rules


Semua perubahan master harus tercatat:


- create
- update
- delete
- import excel
- perubahan harga



Gunakan:


audit_logs



---

# Future Development


Struktur harus siap untuk:


- Import Excel KHS
- Import harga periodik
- Generate BOQ otomatis
- Generate RAB
- Material warehouse integration
- Evidence requirement berdasarkan designator
- DXF/GIS integration
- Dashboard cost analysis


