<?php

namespace App\Enums;

enum LopSegment: string
{
    case FEEDER = 'feeder';
    case DISTRIBUSI = 'distribusi';
    case ODC = 'odc';
    case ODP = 'odp';
    case TIANG = 'tiang';
    case JC = 'jc';
    case OTB = 'otb';

    public function label(): string
    {
        return match ($this) {
            self::FEEDER => 'Feeder',
            self::DISTRIBUSI => 'Distribusi',
            self::ODC => 'ODC',
            self::ODP => 'ODP',
            self::TIANG => 'Tiang',
            self::JC => 'JC',
            self::OTB => 'OTB',
        };
    }
}
