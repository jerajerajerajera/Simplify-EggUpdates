<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggExporterService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Simplify\EggUpdates\Models\EggTest;
use Simplify\EggUpdates\Models\EggUpdate;
use Symfony\Component\Yaml\Yaml;

/**
 * Compares every egg with its update URL. Pelican does the same once a day but remembers the result for an hour;
 * this keeps it, so the list and the notifications stay until the egg is updated.
 */
final class UpdateChecker
{
    private const CHECKED_AT = 'simplify-egg-updates.checked-at';

    /**
     * Checks every egg with an update URL. A remote definition installed by Egg Updates before counts as current,
     * so an egg that Pelican rewrites on import (an older format) is not offered again and again.
     *
     * @return list<Egg> eggs with an update that was not known before
     */
    public static function check(): array
    {
        $new = [];
        // Copies made for a test of an update are not eggs of their own.
        $copies = EggTest::query()->whereNotNull('copy_egg_id')->select('copy_egg_id');
        foreach (Egg::query()->whereNotNull('update_url')->whereNotIn('id', $copies)->get() as $egg) {
            try {
                $remote = RemoteEgg::fetch($egg);
                $differs = self::localHash($egg, $remote->format) !== $remote->hash;
            } catch (\Throwable) {
                // An update URL that cannot be read keeps what was known.
                continue;
            }
            $state = EggUpdate::for($egg->id);
            if (! $differs || $state->applied_hash === $remote->hash) {
                if ($state->exists && $state->found_hash !== null) {
                    $state->update(['found_hash' => null, 'found_at' => null, 'held' => false]);
                }
            } elseif ($state->found_hash !== $remote->hash) {
                $state->fill(['found_hash' => $remote->hash, 'found_at' => now(), 'held' => false])->save();
                $new[] = $egg;
            }
            // Pelican's own Update button on its Eggs page follows the same result.
            Cache::put('eggs.'.$egg->uuid.'.update', $state->found_hash !== null, now()->addHours(7));
        }
        Cache::forever(self::CHECKED_AT, now()->getTimestamp());

        return $new;
    }

    public static function checkedAt(): ?Carbon
    {
        $at = Cache::get(self::CHECKED_AT);

        return is_int($at) ? Carbon::createFromTimestamp($at) : null;
    }

    private static function localHash(Egg $egg, EggFormat $format): string
    {
        $export = app(EggExporterService::class)->handle($egg->id, $format);
        $parsed = $format === EggFormat::YAML ? Yaml::parse($export) : json_decode($export, true);

        return Definition::hash(is_array($parsed) ? $parsed : []);
    }
}
