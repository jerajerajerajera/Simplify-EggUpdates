<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Models\Egg;
use App\Models\Server;
use Simplify\EggUpdates\Enums\Mode;

/** What the scheduler does every minute: check every few hours, act on new updates as the settings say and follow tests. */
final class Runner
{
    /** Seconds between two checks of every update URL. */
    public const INTERVAL = 6 * 3600;

    /** @return list<string> lines for the command output */
    public static function run(bool $force = false): array
    {
        $mode = Settings::mode();
        $lines = [];
        $checked = UpdateChecker::checkedAt();
        $due = $checked === null || $checked->diffInSeconds(now()) >= self::INTERVAL;
        if ($force || ($mode !== Mode::Off && $due)) {
            $lines = self::handle(UpdateChecker::check(), $mode);
        }
        // A test that already started is followed to the end, also after egg updates were turned off.
        if (EggTester::busy()) {
            $lines = [...$lines, ...EggTester::tick()];
        }

        return $lines;
    }

    /**
     * New updates: eggs no server uses are updated and the others tested when the mode says so; root admins hear
     * about the rest.
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
            $used = Server::query()->where('egg_id', $egg->id)->exists();
            if ($mode->updatesUnusedEggs() && ! $used) {
                $result = EggUpdater::update($egg);
                $lines[] = $result->message;
                if ($result->ok) {
                    $updated[] = $egg->name;

                    continue;
                }
            }
            if ($mode->testsUsedEggs() && $used) {
                $lines[] = EggTester::queue($egg)->message;

                continue;
            }
            $waiting[] = $egg->name;
        }
        $and = __('simplify-eggupdates::egg-updates.and');
        if ($updated !== []) {
            Notifier::rootAdmins(
                trans_choice('simplify-eggupdates::egg-updates.notifications.updated_title', count($updated), ['egg' => $updated[0], 'count' => count($updated)]),
                trans_choice('simplify-eggupdates::egg-updates.notifications.updated_body', count($updated), ['eggs' => Definition::names($updated, $and)]),
                'success',
            );
        }
        if ($waiting !== []) {
            Notifier::rootAdmins(
                trans_choice('simplify-eggupdates::egg-updates.notifications.available_title', count($waiting), ['egg' => $waiting[0], 'count' => count($waiting)]),
                trans_choice('simplify-eggupdates::egg-updates.notifications.available_body', count($waiting), ['eggs' => Definition::names($waiting, $and)]),
            );
            foreach ($waiting as $name) {
                $lines[] = __('simplify-eggupdates::egg-updates.messages.found', ['egg' => $name]);
            }
        }

        return $lines;
    }
}
