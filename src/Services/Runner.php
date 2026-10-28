<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Models\Egg;
use App\Models\Server;
use Simplify\EggUpdates\Enums\Mode;

/** What the scheduler does: check every few hours and act on new updates as the settings say. */
final class Runner
{
    /** Seconds between two checks of every update URL. */
    public const INTERVAL = 6 * 3600;

    /** @return list<string> lines for the command output */
    public static function run(bool $force = false): array
    {
        $mode = Settings::mode();
        if ($mode === Mode::Off && ! $force) {
            return [];
        }
        $checked = UpdateChecker::checkedAt();
        if (! $force && $checked !== null && $checked->diffInSeconds(now()) < self::INTERVAL) {
            return [];
        }

        return self::handle(UpdateChecker::check(), $mode);
    }

    /**
     * New updates: eggs no server uses are updated when the mode allows it, root admins hear about the rest.
     *
     * @param  list<Egg>  $eggs
     * @return list<string>
     */
    public static function handle(array $eggs, Mode $mode): array
    {
        $lines = [];
        $updated = [];
        $waiting = [];
        foreach ($eggs as $egg) {
            if ($mode->updatesUnusedEggs() && ! Server::query()->where('egg_id', $egg->id)->exists()) {
                $result = EggUpdater::update($egg);
                $lines[] = $result->message;
                if ($result->ok) {
                    $updated[] = $egg->name;

                    continue;
                }
            }
            $waiting[] = $egg->name;
        }
        $and = __('simplify-egg-updates::egg-updates.and');
        if ($updated !== []) {
            Notifier::rootAdmins(
                trans_choice('simplify-egg-updates::egg-updates.notifications.updated_title', count($updated), ['egg' => $updated[0], 'count' => count($updated)]),
                trans_choice('simplify-egg-updates::egg-updates.notifications.updated_body', count($updated), ['eggs' => Definition::names($updated, $and)]),
                'success',
            );
        }
        if ($waiting !== []) {
            Notifier::rootAdmins(
                trans_choice('simplify-egg-updates::egg-updates.notifications.available_title', count($waiting), ['egg' => $waiting[0], 'count' => count($waiting)]),
                trans_choice('simplify-egg-updates::egg-updates.notifications.available_body', count($waiting), ['eggs' => Definition::names($waiting, $and)]),
            );
            foreach ($waiting as $name) {
                $lines[] = __('simplify-egg-updates::egg-updates.messages.found', ['egg' => $name]);
            }
        }

        return $lines;
    }
}
