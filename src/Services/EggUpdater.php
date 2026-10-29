<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggExporterService;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Simplify\EggUpdates\Models\EggBackup;
use Simplify\EggUpdates\Models\EggUpdate;

/**
 * Updates an egg to the definition at its update URL and puts an earlier one back. Every update keeps the current
 * definition first. Running servers are not touched: they pick up the new egg at their next start or reinstall.
 */
final class EggUpdater
{
    /** Earlier definitions kept per egg. */
    public const KEEP = 5;

    public static function update(Egg $egg, ?RemoteEgg $remote = null): Result
    {
        $lock = Cache::lock('simplify-eggupdates.update.'.$egg->id, 120);
        if (! $lock->get()) {
            return new Result(false, __('simplify-eggupdates::egg-updates.messages.busy', ['egg' => $egg->name]));
        }
        try {
            $remote ??= RemoteEgg::fetch($egg);
            DB::transaction(function () use ($egg, $remote) {
                self::keep($egg);
                app(EggImporterService::class)->fromContent($remote->content, $remote->format, $egg);
                EggUpdate::for($egg->id)
                    ->fill(['applied_hash' => $remote->hash, 'found_hash' => null, 'found_at' => null, 'held' => false])
                    ->save();
            });
            Cache::forget('eggs.'.$egg->uuid.'.update');
            Cache::forget('eggs.'.$egg->fresh()?->uuid.'.update');

            return new Result(true, __('simplify-eggupdates::egg-updates.messages.updated', ['egg' => $egg->name]));
        } catch (\Throwable $exception) {
            report($exception);

            return new Result(false, __('simplify-eggupdates::egg-updates.messages.update_failed', [
                'egg' => $egg->name,
                'reason' => Definition::reason($exception),
            ]));
        } finally {
            $lock->release();
        }
    }

    /** Puts the last kept definition back. The update stays listed but held, so automatic updates leave it alone. */
    public static function putBack(Egg $egg): Result
    {
        $backup = self::latest($egg);
        if ($backup === null) {
            return new Result(false, __('simplify-eggupdates::egg-updates.messages.nothing_to_put_back', ['egg' => $egg->name]));
        }
        try {
            $hash = null;
            try {
                $hash = RemoteEgg::fetch($egg)->hash;
            } catch (\Throwable) {
                // Listed again at the next check.
            }
            DB::transaction(function () use ($egg, $backup, $hash) {
                app(EggImporterService::class)->fromContent($backup->definition, EggFormat::JSON, $egg);
                $backup->delete();
                EggUpdate::for($egg->id)
                    ->fill(['applied_hash' => null, 'found_hash' => $hash, 'found_at' => $hash ? now() : null, 'held' => $hash !== null])
                    ->save();
            });

            return new Result(true, __('simplify-eggupdates::egg-updates.messages.put_back', ['egg' => $egg->name]));
        } catch (\Throwable $exception) {
            report($exception);

            return new Result(false, __('simplify-eggupdates::egg-updates.messages.put_back_failed', [
                'egg' => $egg->name,
                'reason' => Definition::reason($exception),
            ]));
        }
    }

    public static function latest(Egg $egg): ?EggBackup
    {
        return EggBackup::query()->where('egg_id', $egg->id)->latest('id')->first();
    }

    /** Keeps the current definition, and only the last few. */
    private static function keep(Egg $egg): void
    {
        EggBackup::query()->create([
            'egg_id' => $egg->id,
            'definition' => app(EggExporterService::class)->handle($egg->id, EggFormat::JSON),
        ]);
        $keep = EggBackup::query()->where('egg_id', $egg->id)->latest('id')->limit(self::KEEP)->pluck('id');
        EggBackup::query()->where('egg_id', $egg->id)->whereNotIn('id', $keep)->delete();
    }
}
