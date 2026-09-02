<?php

declare(strict_types=1);

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

final class QrCodeService
{
    public static function dataUri(string $data): ?string
    {
        if ($data === '' || !class_exists(QrCode::class)) {
            return null;
        }

        $qrCode = QrCode::create($data)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->setSize(180)
            ->setMargin(8);

        // Endroid QR Code 5.1 masih memicu E_DEPRECATED pada PHP 8.5 karena
        // signature nullable lama; warning vendor ini tidak boleh masuk ke HTML.
        return @(new PngWriter())->write($qrCode)->getDataUri();
    }
}
