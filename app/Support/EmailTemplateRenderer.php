<?php

namespace App\Support;

/**
 * Substitutes {{ token }} placeholders in email subjects and bodies.
 *
 * Unknown tokens are left untouched so a typo is visible in the admin preview
 * rather than silently producing an empty string in a sent email.
 */
class EmailTemplateRenderer
{
    private const TOKEN = '/\{\{\s*([a-z0-9_]+)\s*\}\}/i';

    /** Plain-text substitution for subject lines. */
    public static function text(string $string, array $data): string
    {
        return self::substitute($string, $data, fn ($value) => (string) $value);
    }

    /**
     * Substitution inside already-sanitised HTML bodies. Values are
     * HTML-escaped so a member name like "A & B <x>" can't break the markup.
     */
    public static function html(string $html, array $data): string
    {
        return self::substitute($html, $data, fn ($value) => e((string) $value));
    }

    private static function substitute(string $subject, array $data, callable $escape): string
    {
        return preg_replace_callback(self::TOKEN, function (array $m) use ($data, $escape) {
            $key = strtolower($m[1]);

            return array_key_exists($key, $data) ? $escape($data[$key]) : $m[0];
        }, $subject);
    }
}
