<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

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

function smtp_read($socket, bool $debug = false): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if ($debug) {
            echo 'S: ' . $line;
        }

        if (preg_match('/^\d{3} /', $line) === 1) {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $codes, string $step, bool $debug = false): string
{
    $response = smtp_read($socket, $debug);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $codes, true)) {
        throw new RuntimeException($step . ' failed: ' . trim($response));
    }

    return $response;
}

function smtp_send($socket, string $command, bool $debug = false): void
{
    if ($debug) {
        echo 'C: ' . $command;
    }

    fwrite($socket, $command);
}

function mime_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function dot_stuff(string $text): string
{
    return preg_replace('/^\./m', '..', $text) ?? $text;
}

env_load(__DIR__ . '/.env');

$host = env_get('TEST_SMTP_HOST', 'smtp.yandex.ru');
$port = (int) env_get('TEST_SMTP_PORT', '465');
$encryption = strtolower(env_get('TEST_SMTP_ENCRYPTION', 'ssl'));
$username = env_get('TEST_SMTP_USER', 'karier-tlt@yandex.ru');
$password = env_get('TEST_SMTP_PASS', 'vpxfxirdsacovkow');
$fromEmail = env_get('TEST_SMTP_FROM', $username);
$fromName = env_get('TEST_SMTP_FROM_NAME', 'SMTP Test');
$toEmail = env_get('TEST_SMTP_TO', 'aikido3103@gmail.com');
$subject = env_get('TEST_SMTP_SUBJECT', 'Тест SMTP с Yandex');
$body = env_get('TEST_SMTP_BODY', "Это тестовое письмо, отправленное через smtp.yandex.ru.\n\nЕсли письмо пришло, SMTP-авторизация и отправка работают.");
$debug = in_array('--debug', $argv, true);

if ($password === '') {
    fwrite(STDERR, "Не задан TEST_SMTP_PASS в .env или окружении.\n");
    exit(1);
}

$transport = $encryption === 'ssl' ? 'ssl://' : '';
$remote = sprintf('%s%s:%d', $transport, $host, $port);
$socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);

if ($socket === false) {
    fwrite(STDERR, "Не удалось подключиться к {$remote}: {$errstr} ({$errno})\n");
    exit(1);
}

stream_set_timeout($socket, 20);

try {
    smtp_expect($socket, [220], 'Connect', $debug);

    smtp_send($socket, "EHLO localhost\r\n", $debug);
    smtp_expect($socket, [250], 'EHLO', $debug);

    if ($encryption === 'tls' || $encryption === 'starttls') {
        smtp_send($socket, "STARTTLS\r\n", $debug);
        smtp_expect($socket, [220], 'STARTTLS', $debug);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Не удалось включить TLS после STARTTLS');
        }

        smtp_send($socket, "EHLO localhost\r\n", $debug);
        smtp_expect($socket, [250], 'EHLO after STARTTLS', $debug);
    }

    smtp_send($socket, "AUTH LOGIN\r\n", $debug);
    smtp_expect($socket, [334], 'AUTH LOGIN', $debug);

    smtp_send($socket, base64_encode($username) . "\r\n", $debug);
    smtp_expect($socket, [334], 'Username', $debug);

    smtp_send($socket, base64_encode($password) . "\r\n", $debug);
    smtp_expect($socket, [235], 'Password', $debug);

    smtp_send($socket, "MAIL FROM:<{$fromEmail}>\r\n", $debug);
    smtp_expect($socket, [250], 'MAIL FROM', $debug);

    smtp_send($socket, "RCPT TO:<{$toEmail}>\r\n", $debug);
    smtp_expect($socket, [250, 251], 'RCPT TO', $debug);

    smtp_send($socket, "DATA\r\n", $debug);
    smtp_expect($socket, [354], 'DATA', $debug);

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . mime_header($fromName) . " <{$fromEmail}>",
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

    smtp_send($socket, $message, false);
    smtp_expect($socket, [250], 'Message body', $debug);

    smtp_send($socket, "QUIT\r\n", $debug);
    smtp_expect($socket, [221], 'QUIT', $debug);

    echo "Письмо успешно отправлено на {$toEmail}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    fclose($socket);
}
