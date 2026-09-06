<?php

namespace App\Services;

use App\Enums\ProgramType;
use App\Models\LopNameFormat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LopNamingService
{
    public const DEFAULT_TEMPLATE = '{area}{sto}_{program_code}_{incident}_{segment}';

    public function activeTemplate(): string
    {
        return LopNameFormat::query()
            ->where('is_active', true)
            ->latest('id_lop_name_format')
            ->value('template') ?? self::DEFAULT_TEMPLATE;
    }

    public function generate(array $data, ?string $template = null): string
    {
        $program = isset($data['program_type'])
            ? ProgramType::tryFrom($data['program_type'] instanceof ProgramType ? $data['program_type']->value : $data['program_type'])
            : null;

        $values = [
            '{area}' => $this->token($data['area'] ?? '', true),
            '{sto}' => $this->token($data['sto'] ?? '', true),
            '{branch}' => $this->token($data['branch'] ?? '', true),
            '{segment}' => $this->token($data['segment'] ?? '', true),
            '{program}' => $this->token($program?->label() ?? '', true),
            '{program_code}' => $program?->code() ?? '',
            '{budget_type}' => $this->token($data['budget_type'] ?? '', true),
            '{incident}' => $this->token($data['incident'] ?? '', true),
            '{description}' => $this->token($data['job_description'] ?? ''),
        ];

        $name = strtr($template ?? $this->activeTemplate(), $values);

        return trim((string) preg_replace('/_+/', '_', $name), '_');
    }

    public function updateTemplate(string $template, User $user): LopNameFormat
    {
        return DB::transaction(function () use ($template, $user) {
            LopNameFormat::query()->where('is_active', true)->update([
                'is_active' => false,
                'updated_by' => $user->id_user,
            ]);

            return LopNameFormat::create([
                'template' => trim($template),
                'is_active' => true,
                'created_by' => $user->id_user,
                'updated_by' => $user->id_user,
            ]);
        });
    }

    public function availableTokens(): array
    {
        return [
            '{area}' => 'Area',
            '{sto}' => 'Kode STO',
            '{branch}' => 'Branch',
            '{segment}' => 'Segmen',
            '{program}' => 'Nama Program lengkap',
            '{program_code}' => 'Kode singkat Program',
            '{budget_type}' => 'CAPEX atau OPEX',
            '{incident}' => 'Nomor incident',
            '{description}' => 'Deskripsi pekerjaan',
        ];
    }

    private function token(string $value, bool $uppercase = false): string
    {
        $value = trim((string) preg_replace('/[^\pL\pN]+/u', '_', trim($value)), '_');

        return $uppercase ? mb_strtoupper($value) : $value;
    }
}
