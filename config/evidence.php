<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk penyimpanan evidence
    |--------------------------------------------------------------------------
    |
    | Semua file evidence (foto/pdf + thumbnail) ditulis & dibaca lewat disk
    | ini. Default `public` (storage/app/public, dilayani via symlink /storage).
    | Ganti ke `s3` bila nanti pindah ke object storage / MinIO tanpa menyentuh
    | EvidenceService atau view.
    |
    */

    'disk' => env('EVIDENCE_DISK', 'public'),

];
