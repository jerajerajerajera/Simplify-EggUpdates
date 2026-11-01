<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\ServerVariable;
use Illuminate\Support\Facades\Validator;

/**
 * The variables of a Test first server. A variable keeps its default when that is valid. A required variable without
 * a valid default (such as Paper's User Agent) gets the value the egg's own servers use, and otherwise a value that
 * names this plugin. Pelican then says which variable still has no valid value.
 */
final class TestEnvironment
{
    /** Names the plugin and where it lives, as download services such as PaperMC's ask of a user agent. */
    public const FILLER = 'Simplify-EggUpdates (https://github.com/jerajerajerajera/Simplify-EggUpdates)';

    /** @return array<string, string> */
    public static function for(Egg $copy, Egg $egg): array
    {
        $used = self::usedValues($egg);

        return $copy->variables()->get()->mapWithKeys(function (EggVariable $variable) use ($used) {
            $candidates = [(string) ($variable->default_value ?? ''), ...($used[$variable->env_variable] ?? []), self::FILLER];
            foreach ($candidates as $value) {
                if (Validator::make(['value' => $value], ['value' => $variable->rules ?: []])->passes()) {
                    return [$variable->env_variable => $value];
                }
            }

            return [$variable->env_variable => $candidates[0]];
        })->all();
    }

    /** @return array<string, list<string>> the values the egg's servers use, per variable, most used first */
    private static function usedValues(Egg $egg): array
    {
        $names = $egg->variables()->pluck('env_variable', 'id');

        return ServerVariable::query()
            ->whereIn('variable_id', $names->keys())
            ->whereIn('server_id', $egg->servers()->select('id'))
            ->where('variable_value', '!=', '')
            ->get(['variable_id', 'variable_value'])
            ->groupBy(fn (ServerVariable $value) => $names[$value->variable_id])
            ->map(fn ($values) => $values->countBy('variable_value')->sortDesc()->keys()->map(fn ($value) => (string) $value)->all())
            ->all();
    }
}
