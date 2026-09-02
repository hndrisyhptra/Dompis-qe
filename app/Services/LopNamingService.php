<?php

namespace App\Services;

use App\Enums\WbsType;
use App\Models\LopNameFormat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LopNamingService
{
    public const DEFAULT_TEMPLATE = '{area}{sto}_{wbs_code}_{incident}_{description}';

    public function activeTemplate(): string
    {
        return LopNameFormat::query()
            ->where('is_active', true)
            ->latest('id_lop_name_format')
            ->value('template') ?? self::DEFAULT_TEMPLATE;
    }

    public function generate(array $data, ?string $template = null): string
    {
        $wbs = isset($data['wbs_type'])
            ? WbsType::tryFrom($data['wbs_type'] instanceof WbsType ? $data['wbs_type']->value : $data['wbs_type'])
            : null;

        $values = [
            '{area}' => $this->token($data['area'] ?? '', true),
            '{sto}' => $this->token($data['sto'] ?? '', true),
            '{branch}' => $this->token($data['branch'] ?? '', true),
            '{segment}' => $this->token($data['segment'] ?? '', true),
            '{wbs}' => $this->token($wbs?->label() ?? '', true),
            '{wbs_code}' => $wbs?->code() ?? '',
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
            '{wbs}' => 'Nama WBS lengkap',
            '{wbs_code}' => 'Kode singkat WBS',
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
