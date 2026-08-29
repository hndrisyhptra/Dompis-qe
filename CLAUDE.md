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


WBS:

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




