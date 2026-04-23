<?php

declare(strict_types=1);

error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');

function env_load(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");

        if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
            $_ENV[$key] = $value;
        }
    }
}

function env_get(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }

    return $default;
}

function smtp_read($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (preg_match('/^\d{3} /', $line) === 1) {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $codes, string $step): string
{
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $codes, true)) {
        throw new RuntimeException($step . ' failed: ' . trim($response));
    }

    return $response;
}

function smtp_send($socket, string $command): void
{
    if (fwrite($socket, $command) === false) {
        throw new RuntimeException('SMTP write failed');
    }
}

function mime_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function dot_stuff(string $text): string
{
    return preg_replace('/^\./m', '..', $text) ?? $text;
}

function send_smtp_mail(array $config, string $toEmail, string $subject, string $body): void
{
    $transport = $config['encryption'] === 'ssl' ? 'ssl://' : '';
    $remote = sprintf('%s%s:%d', $transport, $config['host'], $config['port']);
    $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);

    if ($socket === false) {
        throw new RuntimeException("Connect failed: {$errstr} ({$errno})");
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, [220], 'Connect');

        smtp_send($socket, "EHLO localhost\r\n");
        smtp_expect($socket, [250], 'EHLO');

        if ($config['encryption'] === 'tls' || $config['encryption'] === 'starttls') {
            smtp_send($socket, "STARTTLS\r\n");
            smtp_expect($socket, [220], 'STARTTLS');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed');
            }

            smtp_send($socket, "EHLO localhost\r\n");
            smtp_expect($socket, [250], 'EHLO after STARTTLS');
        }

        smtp_send($socket, "AUTH LOGIN\r\n");
        smtp_expect($socket, [334], 'AUTH LOGIN');

        smtp_send($socket, base64_encode($config['user']) . "\r\n");
        smtp_expect($socket, [334], 'Username');

        smtp_send($socket, base64_encode($config['pass']) . "\r\n");
        smtp_expect($socket, [235], 'Password');

        smtp_send($socket, "MAIL FROM:<{$config['from_email']}>\r\n");
        smtp_expect($socket, [250], 'MAIL FROM');

        smtp_send($socket, "RCPT TO:<{$toEmail}>\r\n");
        smtp_expect($socket, [250, 251], 'RCPT TO');

        smtp_send($socket, "DATA\r\n");
        smtp_expect($socket, [354], 'DATA');

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . mime_header($config['from_name']) . " <{$config['from_email']}>",
            "To: <{$toEmail}>",
            'Subject: ' . mime_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $message = implode("\r\n", $headers)
            . "\r\n\r\n"
            . dot_stuff($body)
            . "\r\n.\r\n";

        smtp_send($socket, $message);
        smtp_expect($socket, [250], 'Message body');

        smtp_send($socket, "QUIT\r\n");
        smtp_expect($socket, [221], 'QUIT');
    } finally {
        fclose($socket);
    }
}

env_load(__DIR__ . '/.env');

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$material = trim($_POST['material'] ?? '');
$volume = trim($_POST['volume'] ?? '');
$address = trim($_POST['address'] ?? '');
$details = trim($_POST['details'] ?? '');

if ($name === '' && $phone === '' && $material === '' && $volume === '' && $address === '' && $details === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Пустая заявка'], JSON_UNESCAPED_UNICODE);
    exit;
}

$smtpConfig = [
    'host' => env_get('SMTP_HOST', 'smtp.yandex.ru'),
    'port' => (int) env_get('SMTP_PORT', '465'),
    'encryption' => strtolower(env_get('SMTP_ENCRYPTION', 'ssl')),
    'user' => env_get('SMTP_USER'),
    'pass' => env_get('SMTP_PASS'),
    'from_email' => env_get('SMTP_FROM', env_get('SMTP_USER')),
    'from_name' => env_get('SMTP_FROM_NAME', 'Карьер-ТЛТ+'),
];
$toEmail = env_get('SMTP_TO', $smtpConfig['from_email']);

if (
    $smtpConfig['host'] === '' ||
    $smtpConfig['port'] <= 0 ||
    $smtpConfig['user'] === '' ||
    $smtpConfig['pass'] === '' ||
    $smtpConfig['from_email'] === '' ||
    $toEmail === ''
) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Не настроена отправка почты'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subjectParts = ['Новая заявка с сайта Карьер-ТЛТ+'];
if ($material !== '') {
    $subjectParts[] = $material;
}
$subject = implode(' | ', $subjectParts);

$lines = [];
$lines[] = 'Новая заявка с сайта Карьер-ТЛТ+';
if ($name !== '') {
    $lines[] = 'Имя: ' . $name;
}
if ($phone !== '') {
    $lines[] = 'Телефон: ' . $phone;
}
if ($material !== '') {
    $lines[] = 'Материал: ' . $material;
}
if ($volume !== '') {
    $lines[] = 'Объем: ' . $volume . ' т';
}
if ($address !== '') {
    $lines[] = 'Адрес: ' . $address;
}
if ($details !== '') {
    $lines[] = 'Комментарий: ' . $details;
}
$lines[] = 'Время: ' . date('d.m.Y H:i:s');
$lines[] = 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$body = implode("\n", $lines);

try {
    send_smtp_mail($smtpConfig, $toEmail, $subject, $body);
    echo json_encode(['success' => true, 'message' => 'Заявка отправлена'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Email send failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка отправки'], JSON_UNESCAPED_UNICODE);
}
