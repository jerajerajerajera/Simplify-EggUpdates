<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Enums\EggFormat;

/** Pure helpers for egg definitions, the same way Pelican compares them. */
final class Definition
{
    /** Pelican's hash of a definition: without exported_at, as JSON, md5. */
    public static function hash(array $definition): string
    {
        unset($definition['exported_at']);

        return md5(json_encode($definition, JSON_THROW_ON_ERROR));
    }

    /** The format of an update URL, from its extension (Pelican's eggs are YAML, older ones JSON). */
    public static function format(string $url): EggFormat
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['yaml', 'yml'], true) ? EggFormat::YAML : EggFormat::JSON;
    }

    /** "Paper", "Paper and Forge", "Paper, Fabric and Forge". */
    public static function names(array $names, string $and): string
    {
        $names = array_values($names);
        if (count($names) <= 1) {
            return (string) ($names[0] ?? '');
        }
        $last = array_pop($names);

        return implode(', ', $names).' '.$and.' '.$last;
    }

    /** A short, single-line reason for a message. */
    public static function reason(\Throwable $exception): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($exception->getMessage())) ?? '');

        return $text === '' ? '?' : rtrim(mb_strimwidth($text, 0, 200, '…'), '.');
    }
}
