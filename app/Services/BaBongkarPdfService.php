<?php

namespace App\Services;

use App\Models\QeLop;
use App\Support\BaBongkarRules;
use App\Support\BaImage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class BaBongkarPdfService
{
    public function render(QeLop $lop, array $form, array $signatures, iterable $photos): string
    {
        $fontDirectory = Storage::disk('local')->path('ba-bongkar/fonts');
        if (! Storage::disk('local')->makeDirectory('ba-bongkar/fonts')) {
            throw new \RuntimeException('Cache font PDF tidak dapat disiapkan.');
        }
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setChroot([storage_path('app/private'), resource_path('fonts/carlito'), $fontDirectory]);
        $options->setFontDir($fontDirectory);
        $options->setFontCache($fontDirectory);
        $options->setDefaultFont('Carlito');
        $options->setFontHeightRatio(0.82);
        $pdf = new Dompdf($options);
        $images = [];
        foreach ($photos as $photo) {
            $images[] = $this->image($photo->file_path);
        }
        foreach ($signatures as $role => &$signature) {
            if ($role === 'technician') {
                $signature['image'] = $this->signature($signature['path']);
            }
        }
        unset($signature);
        try {
            $pdf->loadHtml(view('ba-bongkar.pdf', ['lop' => $lop, 'form' => $form, 'signatures' => $signatures, 'images' => $images, 'groups' => BaBongkarRules::MATERIALS])->render());
            $pdf->setPaper('letter', 'portrait');
            $pdf->render();

            return $pdf->output();
        } finally {
            unset($pdf);
            gc_collect_cycles();
        }
    }

    private function signature(string $path, int $maxEdge = 680): string
    {
        $bytes = Storage::disk('local')->get($path);
        $image = BaImage::decode($bytes);
        if ($image === false) {
            throw new \RuntimeException('Gambar TTD BA tidak dapat dibaca.');
        }
        [$x, $y, $width, $height] = BaImage::inkBounds($image);
        $ratio = min(1, $maxEdge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $scaled = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($scaled, 0, 0, imagecolorallocate($scaled, 255, 255, 255));
        imagecopyresampled($scaled, $image, 0, 0, $x, $y, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        imagejpeg($scaled, null, 90);
        $embedded = ob_get_clean();
        imagedestroy($scaled);
        imagedestroy($image);

        return 'data:image/jpeg;base64,'.base64_encode($embedded);
    }

    private function image(string $path, int $maxEdge = 1200): string
    {
        $bytes = Storage::disk('local')->get($path);
        $image = BaImage::decode($bytes);
        if ($image === false) {
            throw new \RuntimeException('Gambar BA tidak dapat dibaca.');
        }
        $ratio = min(1, $maxEdge / max(imagesx($image), imagesy($image)));
        $width = max(1, (int) round(imagesx($image) * $ratio));
        $height = max(1, (int) round(imagesy($image) * $ratio));
        $scaled = imagecreatetruecolor($width, $height);
        imagefill($scaled, 0, 0, imagecolorallocate($scaled, 255, 255, 255));
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        ob_start();
        imagejpeg($scaled, null, 85);
        $embedded = ob_get_clean();
        imagedestroy($scaled);
        imagedestroy($image);

        return 'data:image/jpeg;base64,'.base64_encode($embedded);
    }
}
