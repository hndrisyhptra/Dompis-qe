<?php

namespace App\Enums;

/**
 * Daftar role final (revisi modul Authentication):
 * VIEWER dihapus, MANAGER ditambahkan, berbeda dari draf awal CLAUDE.md.
 */
enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case TEKNISI = 'TEKNISI';
    case MANAGER = 'MANAGER';
    case APPROVER = 'APPROVER';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::TEKNISI => 'Teknisi',
            self::MANAGER => 'Manager',
            self::APPROVER => 'Approver',
        };
    }

    /**
     * Role yang boleh melakukan input/kelola LOP (admin-level).
     */
    public static function adminLevel(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN];
    }

    /**
     * Role dengan visibilitas luas (read access ke semua data operasional)
     * tapi bukan admin-level (tidak bisa create/update/assign).
     */
    public static function broadVisibility(): array
    {
        return [self::MANAGER, self::APPROVER];
    }

    /**
     * Nama route tujuan setelah login, sesuai matriks "Redirect After
     * Login" di CLAUDE.md. Dashboard per-role (Dashboard Admin, Dashboard
     * Operasional, Dashboard Monitoring, Approval Evidence) dibangun
     * bertahap. Ditulis eksplisit
     * per-case (bukan default tunggal) supaya begitu satu dashboard selesai
     * dibangun, tinggal ganti satu baris tanpa menyentuh case lain.
     */
    public function dashboardRouteName(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'dashboard',
            self::ADMIN => 'dashboard',
            self::TEKNISI => 'technician.dashboard',
            self::MANAGER => 'lop.index', // TODO: Dashboard Monitoring
            self::APPROVER => 'lop.index', // TODO: Approval Evidence
        };
    }
}
