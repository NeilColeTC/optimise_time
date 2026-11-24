<?php

declare(strict_types=1);

$envFile = dirname(__DIR__, 2) . '/.env';
if (!is_file($envFile)) {
    return;
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $value = trim($value, " \t\n\r\0\x0B\"'");
    if ($key !== '' && getenv($key) === false) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

