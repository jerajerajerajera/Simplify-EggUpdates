<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Addon;

use App\Models\Egg;
use App\Models\User;
use Simplify\EggUpdates\Models\EggUpdate;

/**
 * The errors of Egg Updates that Explain can explain: a failed test, with the last lines of the test server's log.
 * Explain finds this class by its tag; Egg Updates does not need Explain. Ids look like "simplify-eggupdates:test:<egg id>".
 */
final class EggUpdatesProblems
{
    public const PREFIX = 'simplify-eggupdates:test:';

    public static function id(Egg $egg): string
    {
        return self::PREFIX.$egg->id;
    }

    public function canExplainProblem(string $id, User $user): bool
    {
        return $this->failed($id, $user) !== null;
    }

    /** @return array{title: string, context: string, lines: list<string>}|null */
    public function explainProblem(string $id, User $user): ?array
    {
        $state = $this->failed($id, $user);
        $egg = $state === null ? null : Egg::query()->find($state->egg_id);
        if ($state === null || $egg === null) {
            return null;
        }

        return [
            'title' => __('simplify-eggupdates::egg-updates.tests.problem_title', ['egg' => $egg->name]),
            'context' => 'Egg Updates tested a new version of the Pelican egg "'.$egg->name.'" on a temporary server, and the test failed: '
                .$state->test_reason.' The lines are the end of that server\'s install log or console, read before the server was removed.',
            'lines' => array_values(array_map('strval', $state->test_log ?? [])),
        ];
    }

    /** The failed test the id names, when this user may see it: only root admins see Wings' log lines. */
    private function failed(string $id, User $user): ?EggUpdate
    {
        if (! $user->isRootAdmin() || ! preg_match('/^'.preg_quote(self::PREFIX, '/').'(\d+)$/', $id, $match)) {
            return null;
        }
        $state = EggUpdate::query()->find((int) $match[1]);

        return $state?->test_ok === false && ! empty($state->test_log) ? $state : null;
    }
}
