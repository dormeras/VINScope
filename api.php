<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['ok' => false, 'message' => 'Разрешён только GET-запрос.']);
}

$vin = strtoupper(trim((string) ($_GET['vin'] ?? '')));
if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
    respond(422, ['ok' => false, 'message' => 'VIN должен состоять из 17 латинских букв и цифр.']);
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    respond(503, ['ok' => false, 'message' => 'Не найден config.php. Скопируйте config.example.php в config.php и настройте подключение к MySQL.']);
}

try {
    $config = require $configPath;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);
    $db = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $statement = $db->prepare('SELECT * FROM vehicles WHERE vin = :vin LIMIT 1');
    $statement->execute(['vin' => $vin]);
    $car = $statement->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    respond(503, ['ok' => false, 'message' => 'Не удалось подключиться к базе MySQL. Проверьте config.php и запуск database.sql.']);
}

if (!$car) {
    respond(404, ['ok' => false, 'message' => 'Автомобиль с таким VIN не найден в учебной базе.', 'vin' => $vin]);
}

function item(string $label, ?string $value, ?string $details = null, ?string $state = null): array {
    if ($value === null || $value === '') return ['label' => $label, 'value' => 'Нет данных', 'details' => 'В источниках нет сведений по этому параметру.', 'state' => 'unknown'];
    return ['label' => $label, 'value' => $value, 'details' => $details, 'state' => $state ?? 'normal'];
}

$score = (int) $car['risk_score'];
$riskLabel = $score >= 80 ? 'Низкий риск' : ($score >= 55 ? 'Требует внимания' : 'Высокий риск');

respond(200, [
    'ok' => true,
    'vehicle' => [
        'vin' => $car['vin'], 'title' => trim($car['brand'] . ' ' . $car['model']),
        'subtitle' => implode(' · ', array_filter([$car['year'] . ' г.', $car['engine'], $car['transmission'], $car['drive_type']])),
        'color' => $car['color'], 'riskScore' => $score, 'riskLabel' => $riskLabel, 'updatedAt' => $car['updated_at'],
    ],
    'groups' => [
        ['title' => 'Юридическая чистота', 'items' => [
            item('Угон / розыск', $car['theft_status'], $car['theft_details'], $car['theft_status'] === 'Не числится' ? 'good' : 'danger'),
            item('Ограничения ГИБДД', $car['restrictions_status'], $car['restrictions_details'], $car['restrictions_status'] === 'Не найдены' ? 'good' : 'danger'),
            item('Залог', $car['pledge_status'], $car['pledge_details'], $car['pledge_status'] === 'Не найден' ? 'good' : 'danger'),
            item('ПТС', $car['pts_status'], $car['pts_details']),
            item('Таможенное оформление', $car['customs_status'], $car['customs_details']),
        ]],
        ['title' => 'Эксплуатация и история', 'items' => [
            item('Количество владельцев', $car['owners_count'] === null ? null : $car['owners_count'] . ' ' . ($car['owners_count'] === '1' ? 'владелец' : 'владельца'), $car['ownership_details']),
            item('Пробег', $car['mileage_km'] === null ? null : number_format((int)$car['mileage_km'], 0, '', ' ') . ' км', $car['mileage_details'], $car['mileage_status']),
            item('Работа в такси', $car['taxi_status'], $car['taxi_details'], $car['taxi_status'] === 'Не использовался' ? 'good' : ($car['taxi_status'] === 'Использовался' ? 'danger' : 'unknown')),
            item('Каршеринг', $car['carsharing_status'], $car['carsharing_details'], $car['carsharing_status'] === 'Не использовался' ? 'good' : 'unknown'),
            item('Лизинг', $car['leasing_status'], $car['leasing_details']),
            item('Техосмотр', $car['inspection_status'], $car['inspection_details']),
        ]],
        ['title' => 'Состояние и события', 'items' => [
            item('ДТП', $car['accident_status'], $car['accident_details'], $car['accident_status'] === 'Не зарегистрированы' ? 'good' : ($car['accident_status'] === 'Зарегистрированы' ? 'danger' : 'unknown')),
            item('Окрасы и ремонт', $car['painting_status'], $car['painting_details'], $car['painting_status'] === 'Не обнаружены' ? 'good' : ($car['painting_status'] === 'Есть сведения' ? 'danger' : 'unknown')),
            item('Страховые расчёты', $car['insurance_status'], $car['insurance_details']),
            item('Продажи на аукционах', $car['auction_status'], $car['auction_details']),
            item('Сервисные кампании', $car['service_status'], $car['service_details']),
        ]],
    ],
]);
