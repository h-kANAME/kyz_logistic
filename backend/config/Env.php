<?php
// =============================================================
// KYZ Logistica - Loader .env minimalista sin dependencias
// =============================================================

class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $val = trim($parts[1]);

            if ($key === '') {
                continue;
            }

            // Si ya viene definido desde el entorno del contenedor/host,
            // no lo sobreescribimos con el archivo .env local.
            $existing = getenv($key);
            if ($existing !== false && $existing !== '') {
                $_ENV[$key] = (string)$existing;
                continue;
            }

            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }

            $_ENV[$key] = $val;
            putenv($key . '=' . $val);
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string)$value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = strtolower((string)self::get($key, $default ? 'true' : 'false'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function getInt(string $key, int $default): int
    {
        $value = self::get($key);
        return ($value !== null && is_numeric($value)) ? (int)$value : $default;
    }

    public static function getFloat(string $key, float $default): float
    {
        $value = self::get($key);
        return ($value !== null && is_numeric($value)) ? (float)$value : $default;
    }
}
