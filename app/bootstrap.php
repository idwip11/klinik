<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

require ROOT_PATH . '/app/Env.php';
require ROOT_PATH . '/app/Database.php';
require ROOT_PATH . '/app/Csrf.php';
require ROOT_PATH . '/app/Flash.php';
require ROOT_PATH . '/app/AuditLogger.php';
require ROOT_PATH . '/app/View.php';
require ROOT_PATH . '/app/Auth.php';
require ROOT_PATH . '/app/ValidationException.php';
require ROOT_PATH . '/app/NumberFormat.php';
require ROOT_PATH . '/app/PatientRepository.php';
require ROOT_PATH . '/app/ExaminationRepository.php';
require ROOT_PATH . '/app/ExaminationPanelCatalogRepository.php';
require ROOT_PATH . '/app/WidalResultOptions.php';
require ROOT_PATH . '/app/ExaminationParameterResultRepository.php';
require ROOT_PATH . '/app/LabTestCatalog.php';
require ROOT_PATH . '/app/CumulativeResultRepository.php';
require ROOT_PATH . '/app/ReferenceRangeRepository.php';
require ROOT_PATH . '/app/ReferenceEvaluator.php';
require ROOT_PATH . '/app/HistoricalReferenceEvaluator.php';
require ROOT_PATH . '/app/FinalizationService.php';
require ROOT_PATH . '/app/ExaminationType.php';
require ROOT_PATH . '/app/AnalyticsRepository.php';
require ROOT_PATH . '/app/AnalyticsAggregateExporter.php';
require ROOT_PATH . '/app/PublicVerification.php';
require ROOT_PATH . '/app/QrCodeService.php';
require ROOT_PATH . '/app/ReportRepository.php';
require ROOT_PATH . '/app/ReportExcelExporter.php';
require ROOT_PATH . '/app/ExaminationExcelExporter.php';
require ROOT_PATH . '/app/ExaminationPdfExporter.php';
require ROOT_PATH . '/app/UserRepository.php';
require ROOT_PATH . '/app/LegacyImportMapper.php';
require ROOT_PATH . '/app/LegacyImportService.php';

Env::load(ROOT_PATH . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Makassar'));

$isProduction = Env::get('APP_ENV', 'production') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

session_name(Env::get('SESSION_NAME', 'klinik_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

set_exception_handler(static function (Throwable $exception) use ($isProduction): void {
    error_log((string) $exception);
    http_response_code(500);
    if ($isProduction) {
        echo 'Terjadi gangguan pada aplikasi. Silakan hubungi administrator.';
        return;
    }

    echo 'Application error: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
});
