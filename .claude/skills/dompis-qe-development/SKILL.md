# Dompis QE Development Skill


## Purpose

Skill ini membantu Claude Code mengembangkan aplikasi Dompis QE secara konsisten.


---

# Before Coding


Jangan langsung membuat kode.

Lakukan:


## Step 1 - Requirement Audit


Identifikasi:

- siapa pengguna fitur
- tujuan bisnis
- workflow
- input
- output
- status perubahan


Output:

Requirement Summary


---


## Step 2 - Existing System Audit


Periksa:

- route
- controller
- model
- migration
- service
- component


Cari apakah fitur sudah tersedia.


---


## Step 3 - Database Impact


Analisis:

- tabel baru
- tabel existing
- foreign key
- index
- migration impact


Jangan membuat database baru jika existing masih cukup.


---


## Step 4 - Implementation Plan


Berikan:


Files:

Create:
-


Modify:
-


Database:

Create:
-


Modify:
-


Flow:


User
 |
Controller
 |
Service
 |
Database


---

# Coding Rules


## Laravel


Gunakan:

- Migration
- Model relationship
- Form Request
- Service Layer
- Policy


Hindari:

- massive controller
- query SQL panjang di controller
- logic bercampur


---

# Evidence Module Rules


Evidence selalu:

generic.


Format:

step:

SURVEY

BEFORE

PROGRESS

AFTER

DISMANTLE


type:

PHOTO

DOCUMENT

OTDR

OPM

TELNET


---

# Workflow Rules


LOP lifecycle:

CREATE

ASSIGN

PICKUP

SURVEY

EXECUTION

EVIDENCE

APPROVAL

COMPLETE


Setiap perubahan status harus masuk:

qe_lop_histories


---

# Upload Rules


Semua upload:

- validate
- store secure path
- generate unique filename
- save metadata


---

# Response Format


Saat menerima task:

Jawab dengan format:


## 1. Audit Requirement

...


## 2. Existing Impact

...


## 3. Database Impact

...


## 4. Implementation Plan

...


Tunggu approval sebelum coding.


