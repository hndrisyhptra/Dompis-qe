<?php

namespace App\Services;

use App\Enums\ProgramType;

class IncidentTicketValidationService
{
    public function __construct(private readonly TicketLookupService $tickets) {}

    public function requiresLookup(string $incident, ProgramType $program): bool
    {
        return trim($incident) !== ''
            && ! str_starts_with(mb_strtoupper(trim($incident)), 'INP')
            && $program->incidentTicketType() !== null;
    }

    public function inspect(string $incident, ProgramType $program, ?array $ticket = null): array
    {
        $incident = mb_strtoupper(trim($incident));
        $required = $program->incidentTicketType();
        $result = ['valid' => true, 'required_type' => $required, 'ticket_type' => null, 'message' => ''];
        if (! $this->requiresLookup($incident, $program)) {
            return $result;
        }

        $ticket ??= $this->tickets->lookupTypes([$incident])[$incident] ?? ['found' => false];
        if ($ticket['error'] ?? false) {
            return [...$result, 'valid' => false, 'message' => 'Validasi incident gagal karena pengecekan database tiket tidak tersedia. Coba kembali; jika belum memiliki incident, kosongkan untuk membuat INP.'];
        }
        if (! ($ticket['found'] ?? false)) {
            return [...$result, 'valid' => false, 'message' => "Incident {$incident} tidak ditemukan di database tiket. Periksa nomor incident atau kosongkan untuk membuat INP."];
        }
        $type = trim((string) ($ticket['jenis_tiket_2'] ?? ''));
        $normalized = mb_strtoupper((string) preg_replace('/[\s_-]+/u', ' ', $type));
        $valid = match ($program) {
            ProgramType::RECOVERY, ProgramType::PREVENTIVE => str_contains($normalized, 'GAMAS') || $normalized === 'UNKNOWN',
            ProgramType::RELOK_TIANG => preg_match('/\bNON NUMBERING\b/u', $normalized) === 1,
            ProgramType::RELOK_UTILITAS => true,
        };

        return [
            ...$result,
            'valid' => $valid,
            'ticket_type' => $type ?: null,
            'message' => $valid
                ? 'Tiket sesuai.'
                : "Gunakan tiket {$required} untuk {$program->label()}. ".($type !== '' ? "Tiket ini berjenis {$type}." : 'Jenis tiket ini belum terisi.'),
        ];
    }
}
