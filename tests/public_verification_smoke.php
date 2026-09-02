<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function verificationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$examination = [
    'id' => 987654,
    'examination_type' => 'drug_screen',
    'status' => 'completed',
    'lab_number' => 'LOCAL-QR-001',
    'medical_record_number' => 'LOCAL-001',
    'full_name' => 'Pasien Uji Lokal',
    'birth_place' => 'Malang',
    'birth_date' => '1990-01-01',
    'address' => 'Alamat uji lokal',
    'examined_on' => '2026-08-31',
    'sex' => 'male',
    'created_at' => '2026-08-31 01:00:00',
    'drug_amphetamine' => 'Negatif',
    'drug_methamphetamine' => 'Negatif',
    'drug_morphine' => 'Negatif',
    'drug_marijuana' => 'Negatif',
    'drug_benzodiazepine' => 'Negatif',
    'drug_cocaine' => 'Negatif',
];

$verificationUrl = PublicVerification::url($examination);
verificationAssert(is_string($verificationUrl), 'URL verifikasi seharusnya tersedia.');
verificationAssert(str_contains($verificationUrl, '/verification/examinations/987654?token='), 'URL verifikasi tidak sesuai.');

$query = parse_url($verificationUrl, PHP_URL_QUERY);
parse_str(is_string($query) ? $query : '', $queryParameters);
$token = (string) ($queryParameters['token'] ?? '');
verificationAssert($token !== '', 'Token verifikasi tidak tersedia.');
verificationAssert(PublicVerification::isValid(987654, $token), 'Token verifikasi valid seharusnya diterima.');
verificationAssert(!PublicVerification::isValid(987654, $token . 'invalid'), 'Token verifikasi yang diubah seharusnya ditolak.');

$qrDataUri = QrCodeService::dataUri($verificationUrl);
verificationAssert(is_string($qrDataUri) && str_starts_with($qrDataUri, 'data:image/png;base64,'), 'QR code PNG data URI gagal dibuat.');

$flags = [];
ob_start();
require ROOT_PATH . '/app/views/examinations/preview_drug_screen.php';
$printedMarkup = (string) ob_get_clean();
verificationAssert(str_contains($printedMarkup, 'drug-verification-qr'), 'QR tidak dirender pada template cetak.');
verificationAssert(str_contains($printedMarkup, 'data:image/png;base64,'), 'Data QR tidak masuk ke template cetak.');

echo "Public verification smoke: OK\n";
