<?php

namespace Tests\Support;

class PngWithExtraIccData
{
    public static function make(string $png): string
    {
        // Minimal RGB ICC header with D50 illuminant and no tags.
        $profile = str_repeat("\0", 132);
        $profile = substr_replace($profile, pack('N', 132), 0, 4);
        $profile = substr_replace($profile, "\x02\x10\0\0", 8, 4);
        $profile = substr_replace($profile, 'mntrRGB XYZ ', 12, 12);
        $profile = substr_replace($profile, 'acsp', 36, 4);
        $profile = substr_replace($profile, pack('N3', 0x0000F6D6, 0x00010000, 0x0000D32D), 68, 12);
        // Enough trailing data to reproduce libpng's extra compressed data warning.
        $data = "Test profile\0\0".gzcompress($profile, 0).str_repeat('extra', 4096);
        $chunk = pack('N', strlen($data)).'iCCP'.$data.pack('N', crc32('iCCP'.$data));

        return substr($png, 0, 33).$chunk.substr($png, 33);
    }
}
