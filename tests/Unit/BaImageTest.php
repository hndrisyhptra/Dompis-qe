<?php

namespace Tests\Unit;

use App\Support\BaImage;
use ErrorException;
use GdImage;
use PHPUnit\Framework\TestCase;
use Tests\Support\PngWithExtraIccData;

class BaImageTest extends TestCase
{
    public function test_icc_warning_is_reproduced_but_ba_decoder_reads_pixels_and_restores_handler(): void
    {
        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        $png = PngWithExtraIccData::make(ob_get_clean());
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });
        try {
            $this->assertInstanceOf(GdImage::class, imagecreatefromstring($png));
        } finally {
            restore_error_handler();
        }
        $this->assertContains('imagecreatefromstring(): gd-png: libpng warning: iCCP: extra compressed data', $warnings);
        $handler = static fn (int $severity, string $message, string $file, int $line) => throw new ErrorException($message, 0, $severity, $file, $line);
        set_error_handler($handler);
        try {
            $decoded = BaImage::decode($png);
            $this->assertInstanceOf(GdImage::class, $decoded);
            $this->assertSame(20, imagesx($decoded));
            $this->assertSame(10, imagesy($decoded));
            $restored = set_error_handler(null);
            restore_error_handler();
            $this->assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    public function test_unrelated_decode_warnings_reach_existing_handler_and_restore_it_on_failure(): void
    {
        $handler = static fn (int $severity, string $message, string $file, int $line) => throw new ErrorException($message, 0, $severity, $file, $line);
        set_error_handler($handler);
        try {
            try {
                BaImage::decode('not an image');
                $this->fail('Corrupt image warning must not be suppressed.');
            } catch (ErrorException $error) {
                $this->assertStringContainsString('imagecreatefromstring()', $error->getMessage());
                $this->assertStringNotContainsString('iCCP:', $error->getMessage());
            }
            $restored = set_error_handler(null);
            restore_error_handler();
            $this->assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    public function test_undecodable_image_still_returns_false_when_existing_handler_handles_warning(): void
    {
        set_error_handler(static fn (): bool => true);
        try {
            $this->assertFalse(BaImage::decode('not an image'));
        } finally {
            restore_error_handler();
        }
    }

    public function test_ink_bounds_trims_white_margins_and_falls_back_to_full_canvas(): void
    {
        $image = imagecreatetruecolor(120, 60);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 30, 15, 80, 45, imagecolorallocate($image, 17, 24, 39));

        $this->assertSame([28, 13, 55, 35], BaImage::inkBounds($image));

        $blank = imagecreatetruecolor(40, 20);
        imagefill($blank, 0, 0, imagecolorallocate($blank, 255, 255, 255));

        $this->assertSame([0, 0, 40, 20], BaImage::inkBounds($blank));
    }
}
