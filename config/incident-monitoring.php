<?php

return [
    /*
     | Batas bawah reported_date tiket Dompis yang boleh dibaca halaman
     | Monitoring Incident (Tiket Dompis). Tiket sebelum tanggal ini tidak
     | dihitung, tidak ditampilkan, dan tidak diunduh. Ubah lewat env bila
     | periode sumber berubah.
     */
    'reported_from' => env('INCIDENT_TICKET_REPORTED_FROM', '2026-09-01'),
];
