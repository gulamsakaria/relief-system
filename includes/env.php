<?php
// Minimal ".env" loader — supports "KEY=value" lines, "#" comments, blank lines.

function loadEnv(string $path): void {
    if (!is_readable($path)) {
        return; // no .env present — config.php falls back to defaults
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? $default;
}
