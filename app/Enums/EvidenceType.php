<?php

namespace App\Enums;

enum EvidenceType: string
{
    case PHOTO = 'PHOTO';
    case DOCUMENT = 'DOCUMENT';
    case OTDR = 'OTDR';
    case OPM = 'OPM';
    case TELNET = 'TELNET';

    public function label(): string
    {
        return match ($this) {
            self::PHOTO => 'Foto',
            self::DOCUMENT => 'Dokumen',
            self::OTDR => 'OTDR',
            self::OPM => 'OPM',
            self::TELNET => 'Telnet',
        };
    }
}
