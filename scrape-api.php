<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/scraper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'message'=>'Разрешён только GET-запрос.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$vin = normalizeVin((string)($_GET['vin'] ?? ''));
if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Некорректный VIN.'], JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    echo json_encode(['ok'=>true,'data'=>scrapeDemoSources($vin)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Ошибка скрапера: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
