<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Enums\EggFormat;
use App\Models\Egg;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/** The definition published at an egg's update URL. */
final readonly class RemoteEgg
{
    public function __construct(
        public string $content,
        public EggFormat $format,
        public string $hash,
    ) {}

    public static function fetch(Egg $egg): self
    {
        $url = (string) $egg->update_url;
        if (! preg_match('#^https://#i', $url)) {
            throw new RuntimeException(__('simplify-egg-updates::egg-updates.errors.https'));
        }
        $format = Definition::format($url);
        $content = Http::timeout(10)->connectTimeout(3)->get($url)->throw()->body();

        return new self($content, $format, Definition::hash(self::parse($content, $format)));
    }

    /** @return array<mixed> */
    public static function parse(string $content, EggFormat $format): array
    {
        $parsed = $format === EggFormat::YAML ? Yaml::parse($content) : json_decode($content, true);
        if (! is_array($parsed)) {
            throw new RuntimeException(__('simplify-egg-updates::egg-updates.errors.not_an_egg'));
        }

        return $parsed;
    }
}
