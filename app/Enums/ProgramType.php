<?php

namespace App\Enums;

use App\Support\ReservedDesignatorRules;

enum ProgramType: string
{
    case RECOVERY = 'recovery';
    case PREVENTIVE = 'preventive';
    case RELOK_UTILITAS = 'relok_utilitas';
    case RELOK_TIANG = 'relok_tiang';

    public function label(): string
    {
        return match ($this) {
            self::RECOVERY => 'QE Recovery',
            self::PREVENTIVE => 'QE Preventive',
            self::RELOK_UTILITAS => 'QE Relok Utilitas',
            self::RELOK_TIANG => 'QE Relok Tiang',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::RECOVERY => 'QEREC',
            self::PREVENTIVE => 'QEPREV',
            self::RELOK_UTILITAS => 'QEREL',
            self::RELOK_TIANG => 'QERELTIANG',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::RECOVERY => 1,
            self::PREVENTIVE => 2,
            self::RELOK_UTILITAS => 3,
            self::RELOK_TIANG => 4,
        };
    }

    public function usesProjectStatus(): bool
    {
        return $this !== self::RECOVERY;
    }

    public function supportsMultipleSegments(): bool
    {
        return true;
    }

    public function budgetType(): LopBudgetType
    {
        return $this === self::RELOK_UTILITAS ? LopBudgetType::CAPEX : LopBudgetType::OPEX;
    }

    public function includesBoqServices(): bool
    {
        return $this === self::RELOK_UTILITAS;
    }

    public function allowsBoqType(string $type, ?string $designatorCode = null): bool
    {
        return in_array(mb_strtoupper(trim($type)), $this->includesBoqServices() ? ['MATERIAL', 'JASA'] : ['MATERIAL'], true)
            || ReservedDesignatorRules::fixedActual($designatorCode);
    }

    public function incidentTicketType(): ?string
    {
        return match ($this) {
            self::RECOVERY => 'GAMAS, UNKNOWN, TSEL, atau PREMAN',
            self::PREVENTIVE => 'GAMAS, UNKNOWN, INFRACARE, atau PREMAN',
            self::RELOK_TIANG => 'NON NUMBERING',
            self::RELOK_UTILITAS => null,
        };
    }

    /** Terima label/canonical/kode dengan variasi huruf, spasi, underscore, dan tanda baca. */
    public static function fromInput(string $value): ?self
    {
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/i', ' ', mb_strtolower(trim($value))));
        $aliases = [
            self::RECOVERY->value => ['recovery', 'qerec', 'qerecovery'],
            self::PREVENTIVE->value => ['preventive', 'qeprev', 'qepreventive'],
            self::RELOK_UTILITAS->value => ['relok utilitas', 'qerel', 'qerelok', 'qerelokutilitas'],
            self::RELOK_TIANG->value => ['relok tiang', 'qereltiang', 'qereloktiang'],
        ];
        $matches = [];
        foreach ($aliases as $program => $names) {
            foreach ($names as $name) {
                if (preg_match('/(?:^| )'.preg_quote($name, '/').'(?: |$)/', $normalized)) {
                    $matches[$program] = self::from($program);
                    break;
                }
            }
        }

        return count($matches) === 1 ? reset($matches) : ($normalized === 'relok' ? self::RELOK_UTILITAS : null);
    }
}
