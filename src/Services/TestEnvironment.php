<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\ServerVariable;
use Illuminate\Support\Facades\Validator;

/**
 * The variables of a Test first server. A variable keeps its default when that is filled and valid. An empty default
 * (such as FTB's modpack ID) or one that is not valid (such as Paper's User Agent, a bot token or an admin password)
 * gets the value the egg's own servers use, and otherwise the first of a few neutral values that its own rules accept.
 */
final class TestEnvironment
{
    /** Names the plugin and where it lives, as download services such as PaperMC's ask of a user agent. */
    public const FILLER = 'Simplify-EggUpdates (https://github.com/jerajerajerajera/Simplify-EggUpdates)';

    /** @return array<string, string> */
    public static function for(Egg $copy, Egg $egg): array
    {
        $used = self::usedValues($egg);

        return $copy->variables()->get()
            ->mapWithKeys(fn (EggVariable $variable) => [$variable->env_variable => self::value($variable, $used[$variable->env_variable] ?? [])])
            ->all();
    }

    /**
     * The first valid one of the default, $used and the neutral fillers; an empty default comes after $used.
     *
     * @param  list<string>  $used
     */
    public static function value(EggVariable $variable, array $used = []): string
    {
        $rules = $variable->rules ?: [];
        $candidates = [...self::order((string) ($variable->default_value ?? ''), $used), ...self::fillers($rules)];
        foreach ($candidates as $value) {
            if (self::valid($value, $rules)) {
                return $value;
            }
        }

        return $candidates[0];
    }

    /**
     * The default first, unless it is empty: an empty value would make an egg such as FTB install nothing.
     *
     * @param  list<string>  $used
     * @return list<string>
     */
    public static function order(string $default, array $used): array
    {
        return $default === '' ? [...$used, $default] : [$default, ...$used];
    }

    /**
     * The image the egg's servers use most that the new version still offers, else its first (Pelican's default).
     * CurseForge, for one, lists Java 8 first: a modern modpack would stop at once on it.
     *
     * @param  array<string, string>  $images
     * @param  list<string>  $used  most used first
     */
    public static function image(array $images, array $used): ?string
    {
        foreach ($used as $image) {
            if (in_array($image, $images, true)) {
                return $image;
            }
        }

        return array_values($images)[0] ?? null;
    }

    /** @return list<string> the images the egg's servers use, most used first */
    public static function usedImages(Egg $egg): array
    {
        return $egg->servers()->pluck('image')->filter()->countBy()->sortDesc()->keys()->map(fn ($image) => (string) $image)->all();
    }

    /** Whether Pelican will accept the value; a rule that cannot be checked (a broken pattern) counts as a no. */
    public static function valid(string $value, array $rules): bool
    {
        try {
            return Validator::make(['value' => $value], ['value' => $rules])->passes();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Neutral values to try, the plugin's name first, then what the rules themselves name: the first allowed choice,
     * the lowest number or length.
     *
     * @param  list<string>  $rules
     * @return list<string>
     */
    private static function fillers(array $rules): array
    {
        $values = [self::FILLER, 'SimplifyEggUpdates', 'simplify-egg-updates', 'https://github.com/jerajerajerajera/Simplify-EggUpdates'];
        foreach ($rules as $rule) {
            [$name, $argument] = array_pad(explode(':', (string) $rule, 2), 2, '');
            $first = trim(explode(',', $argument)[0]);
            match ($name) {
                'in' => array_push($values, ...array_filter(explode(',', $argument), fn ($choice) => $choice !== '')),
                'between', 'min', 'gte', 'gt' => array_push($values, $first, (string) ((int) $first + 1)),
                'digits', 'digits_between', 'size' => array_push($values, str_repeat('1', max(1, (int) $first)), str_repeat('s', max(1, (int) $first))),
                default => null,
            };
        }

        // A value with the start, end and length the rules ask for, such as a token that ends in "==".
        $parts = collect($rules)->mapWithKeys(fn ($rule) => [explode(':', (string) $rule, 2)[0] => trim(explode(',', explode(':', (string) $rule, 2)[1] ?? '')[0])]);
        $start = (string) $parts->get('starts_with', '');
        $end = (string) $parts->get('ends_with', '');
        $length = max((int) $parts->get('size', $parts->get('min', 0)), strlen($start.$end) + 1);
        $values[] = $start.str_repeat('A', $length - strlen($start.$end)).$end;

        return [...$values, '1', '0', 'true'];
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
