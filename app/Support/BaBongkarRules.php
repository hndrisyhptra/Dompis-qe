<?php

namespace App\Support;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Models\QeEvidence;
use App\Models\QeLop;
use Illuminate\Validation\ValidationException;

class BaBongkarRules
{
    public const MATERIALS = [
        'feeder' => ['label' => 'Feeder', 'types' => ['288 Core', '264 Core', '144 Core', '96 Core', '48 Core', '36 Core', '24 Core'], 'measure' => 'length'],
        'distribusi' => ['label' => 'Distribusi', 'types' => ['48 Core', '24 Core', '12 Core'], 'measure' => 'length'],
        'odc' => ['label' => 'ODC', 'types' => ['ODC C', 'ODC B'], 'measure' => 'quantity'],
        'odp' => ['label' => 'ODP', 'types' => ['ODP Pole', 'ODP Wall', 'ODP Padenstal', 'DP Tembaga', 'ODP CA'], 'measure' => 'quantity'],
        'closure' => ['label' => 'Universal Closure', 'types' => ['288 Core', '144 Core', '96 Core', '48 Core', '24 Core'], 'measure' => null],
        'tiang' => ['label' => 'Tiang', 'types' => ['T9', 'T7', 'Tiang Galvanis'], 'measure' => 'quantity'],
        'drop_core' => ['label' => 'Drop Core', 'types' => [], 'measure' => 'length', 'additional_measure' => 'quantity'],
    ];

    public static function document(QeLop $lop): ?QeEvidence
    {
        return $lop->evidences->first(fn ($e) => $e->category === EvidenceCategory::BA_BONGKAR);
    }

    public static function complete(QeLop $lop): bool
    {
        if ($lop->ba_bongkar_required === false) {
            return true;
        }
        $doc = self::document($lop);

        return $lop->ba_bongkar_required === true
            && data_get($lop->ba_bongkar_data, 'ready') === true
            && $doc !== null && $doc->status !== EvidenceStatus::REJECTED
            && data_get($doc->metadata, 'revision') === data_get($lop->ba_bongkar_data, 'revision')
            && $lop->evidences->where('category', EvidenceCategory::BA_BONGKAR_PHOTO)->isNotEmpty()
            && ! $lop->evidences->contains(fn ($e) => $e->category?->isBaBongkar() && $e->status === EvidenceStatus::REJECTED);
    }

    public static function assertSubmittable(QeLop $lop): void
    {
        if (! self::complete($lop)) {
            throw ValidationException::withMessages(['workflow' => 'Lengkapi tahap 6 BA Bongkar: pilih Ya/Tidak dan, jika Ya, simpan BA lengkap beserta foto dan TTD teknisi.']);
        }
    }

    public static function assertCompletable(QeLop $lop): void
    {
        self::assertSubmittable($lop);
        if ($lop->ba_bongkar_required) {
            $doc = self::document($lop);
            if ($doc->status !== EvidenceStatus::APPROVED) {
                throw ValidationException::withMessages(['review' => 'BA Bongkar harus disetujui Admin sebelum LOP selesai.']);
            }
        }
    }
}
