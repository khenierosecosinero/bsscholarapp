<?php

namespace App\Support;

class MailEnv
{
    /** @var array<string, string>|null */
    private static ?array $dotenvMap = null;

    /**
     * Normalize a mail-related .env value: trim, strip wrapping quotes, and
     * optionally remove internal whitespace (for Google App Passwords).
     */
    public static function string(?string $value, bool $stripInternalWhitespace = false): string
    {
        $value = trim((string) $value);

        if (
            strlen($value) >= 2
            && (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            )
        ) {
            $value = substr($value, 1, -1);
        }

        $value = trim($value);

        if ($stripInternalWhitespace) {
            $value = preg_replace('/\s+/', '', $value) ?? $value;
        }

        return $value;
    }

    /**
     * Read a key from the project .env file without using Laravel's env() helper.
     * Needed when `config:cache` / `optimize` skipped Dotenv loading and left
     * blank SMTP credentials in the cached config.
     */
    public static function fromDotenvFile(string $key, bool $stripInternalWhitespace = false): string
    {
        if (self::$dotenvMap === null) {
            self::$dotenvMap = self::parseDotenvFile(base_path('.env'));
        }

        return self::string(self::$dotenvMap[$key] ?? '', $stripInternalWhitespace);
    }

    /**
     * @return array<string, string>
     */
    private static function parseDotenvFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $map = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $map[$name] = $value;
        }

        return $map;
    }
}
