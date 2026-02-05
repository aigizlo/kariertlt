<?php
error_reporting(0);

function env_load($path)
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");
        $_ENV[$key] = $value;
    }
}

env_load(__DIR__ . '/.env');

$token = $_ENV['BOT_TOKEN'] ?? '';
$channelId = $_ENV['CHANNEL_ID'] ?? '';

header('Content-Type: application/json');

if ($token === '' || $channelId === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Не настроены BOT_TOKEN или CHANNEL_ID']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$material = trim($_POST['material'] ?? '');
$volume = trim($_POST['volume'] ?? '');
$address = trim($_POST['address'] ?? '');
$details = trim($_POST['details'] ?? '');

if ($name === '' && $phone === '' && $material === '' && $volume === '' && $address === '' && $details === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Пустая заявка']);
    exit;
}

$lines = [];
$lines[] = "🔔 Новая заявка с сайта Карьер‑ТЛТ+";
if ($name !== '') $lines[] = "Имя: " . $name;
if ($phone !== '') $lines[] = "Телефон: " . $phone;
if ($material !== '') $lines[] = "Материал: " . $material;
if ($volume !== '') $lines[] = "Объем: " . $volume . " т";
if ($address !== '') $lines[] = "Адрес: " . $address;
if ($details !== '') $lines[] = "Комментарий: " . $details;
$lines[] = "Время: " . date('d.m.Y H:i:s');

$text = implode("\n", $lines);

$url = "https://api.telegram.org/bot{$token}/sendMessage";
$params = [
    'chat_id' => $channelId,
    'text' => $text
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 200 && $response !== false) {
    echo json_encode(['success' => true, 'message' => 'Заявка отправлена']);
} else {
    http_response_code(500);
    error_log('Telegram send failed. HTTP=' . $http_code . ' CURL=' . $curl_error . ' RESP=' . $response);
    echo json_encode(['success' => false, 'message' => 'Ошибка отправки']);
}
