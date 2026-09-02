<?php

namespace App\Enums;

/**
 * Lifecycle status qe_lops sesuai CLAUDE.md.
 *
 * draft -> assigned -> picked_up -> survey -> progress -> waiting_approval -> completed
 * dengan cabang "rejected" dari waiting_approval (kembali ke progress) atau dari survey/progress
 * (dibatalkan admin).
 */
enum LopStatus: string
{
    case DRAFT = 'draft';
    case ASSIGNED = 'assigned';
    case PICKED_UP = 'picked_up';
    case SURVEY = 'survey';
    case PROGRESS = 'progress';
    case WAITING_APPROVAL = 'waiting_approval';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ASSIGNED => 'Assigned',
            self::PICKED_UP => 'Picked Up',
            self::SURVEY => 'Survey',
            self::PROGRESS => 'Progress',
            self::WAITING_APPROVAL => 'Waiting Approval',
            self::COMPLETED => 'Completed',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * Whitelist transisi status valid: current => [allowed next states].
     * Dipakai LopService untuk memvalidasi setiap perubahan status.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            self::DRAFT->value => [self::ASSIGNED->value],
            self::ASSIGNED->value => [self::DRAFT->value, self::PICKED_UP->value, self::REJECTED->value],
            self::PICKED_UP->value => [self::SURVEY->value, self::REJECTED->value],
            self::SURVEY->value => [self::PROGRESS->value, self::REJECTED->value],
            self::PROGRESS->value => [self::WAITING_APPROVAL->value, self::REJECTED->value],
            self::WAITING_APPROVAL->value => [self::COMPLETED->value, self::REJECTED->value],
            self::COMPLETED->value => [],
            // Dari rejected, pekerjaan dapat dikembalikan ke progress untuk
            // perbaikan atau ke waiting approval saat reviewer membatalkan
            // keputusan reject terakhir untuk memeriksa ulang evidence.
            self::REJECTED->value => [self::PROGRESS->value, self::WAITING_APPROVAL->value],
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target->value, self::transitions()[$this->value] ?? [], true);
    }

    /**
     * Varian warna x-badge untuk status ini di UI.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::ASSIGNED, self::PICKED_UP, self::SURVEY, self::PROGRESS => 'info',
            self::WAITING_APPROVAL => 'warning',
            self::COMPLETED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
