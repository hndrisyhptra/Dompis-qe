<?php

namespace App\Support;

use GdImage;

class BaImage
{
    public static function decode(string $bytes): GdImage|false
    {
        $previousHandler = null;
        $previousHandler = set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$previousHandler): bool {
            // A rejected PNG colour profile does not prevent GD from reading its pixels.
            if ($severity === E_WARNING && str_starts_with($message, 'imagecreatefromstring(): gd-png: libpng warning: iCCP:')) {
                return true;
            }

            return $previousHandler !== null && $previousHandler($severity, $message, $file, $line) !== false;
        }, E_WARNING);

        try {
            return imagecreatefromstring($bytes);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Bounding box of non-white ink with a small padding, or the full image when blank.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public static function inkBounds(GdImage $image, int $threshold = 235): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $trueColor = imageistruecolor($image);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                if ($trueColor) {
                    $alpha = ($color >> 24) & 0x7F;
                    $red = ($color >> 16) & 0xFF;
                    $green = ($color >> 8) & 0xFF;
                    $blue = $color & 0xFF;
                } else {
                    $rgba = imagecolorsforindex($image, $color);
                    $alpha = $rgba['alpha'];
                    $red = $rgba['red'];
                    $green = $rgba['green'];
                    $blue = $rgba['blue'];
                }
                if ($alpha >= 120 || ($red + $green + $blue) / 3 >= $threshold) {
                    continue;
                }
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < $minX || $maxY < $minY) {
            return [0, 0, $width, $height];
        }
        $pad = (int) round(max($maxX - $minX, $maxY - $minY) * 0.04);
        $x = max(0, $minX - $pad);
        $y = max(0, $minY - $pad);
        $right = min($width - 1, $maxX + $pad);
        $bottom = min($height - 1, $maxY + $pad);

        return [$x, $y, $right - $x + 1, $bottom - $y + 1];
    }
}
