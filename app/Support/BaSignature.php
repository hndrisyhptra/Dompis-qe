<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BaSignature
{
    private static function assertInk(string $bytes): void
    {
        $image = BaImage::decode($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['signature_data' => 'Gambar TTD tidak dapat dibaca. Gambar ulang tanda tangan.']);
        }
        $small = imagescale($image, 128, 64);
        $ink = 0;
        for ($x = 0; $x < 128; $x++) {
            for ($y = 0; $y < 64; $y++) {
                $color = imagecolorsforindex($small, imagecolorat($small, $x, $y));
                if ($color['alpha'] < 110 && min($color['red'], $color['green'], $color['blue']) < 220) {
                    $ink++;
                }
            }
        }
        imagedestroy($small);
        imagedestroy($image);
        if ($ink < 3) {
            throw ValidationException::withMessages(['signature_data' => 'Bidang TTD masih kosong. Gambar tanda tangan terlebih dahulu.']);
        }
    }

    public static function read(Request $request, bool $drawOnly = false): ?array
    {
        $request->validate([
            'signature_data' => ['nullable', 'string', 'max:2800000'],
            'signature_file' => [$drawOnly ? 'prohibited' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        if ($data = $request->input('signature_data')) {
            if (! preg_match('/^data:image\/png;base64,(.+)$/s', $data, $match)) {
                throw ValidationException::withMessages(['signature_data' => 'Format TTD tidak valid. Gambar ulang tanda tangan.']);
            }
            $bytes = base64_decode($match[1], true);
            $info = $bytes === false ? false : @getimagesizefromstring($bytes);
            if (! $info || $info['mime'] !== 'image/png' || strlen($bytes) > 2097152 || $info[0] > 3000 || $info[1] > 3000) {
                throw ValidationException::withMessages(['signature_data' => 'Gambar TTD tidak valid atau terlalu besar.']);
            }

            self::assertInk($bytes);

            return ['bytes' => $bytes, 'extension' => 'png', 'source' => 'draw'];
        }
        if ($file = $request->file('signature_file')) {
            $info = @getimagesize($file->getRealPath());
            if (! $info || $info[0] > 3000 || $info[1] > 3000) {
                throw ValidationException::withMessages(['signature_file' => 'Gunakan gambar TTD yang valid, maksimal 3000 × 3000 piksel.']);
            }

            return ['bytes' => $file->get(), 'extension' => $file->extension(), 'source' => 'upload'];
        }

        return null;
    }
}
