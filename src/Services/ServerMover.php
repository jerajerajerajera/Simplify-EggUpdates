<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use Simplify\EggUpdates\Models\EggUpdate;

/**
 * Existing servers keep their own startup command and Docker image: Pelican copies both into the server when it is
 * created. This moves servers that still use what the egg's previous version offered, and the update removed or
 * changed, to the new one. It applies at their next start. A field the update added as required, without a valid
 * default (such as Paper's User Agent), gets the value the admin gives on the servers that have none.
 */
final class ServerMover
{
    /** @param  array<string, mixed>  $values  per env variable of fields(), the value for servers without one */
    public static function move(Egg $egg, array $values = []): Result
    {
        $previous = self::previous($egg);
        if ($previous === null) {
            return new Result(false, __('simplify-eggupdates::egg-updates.messages.nothing_to_compare', ['egg' => $egg->name]));
        }
        $fields = self::fields($egg);
        foreach ($fields as $env => $field) {
            if (! TestEnvironment::valid((string) ($values[$env] ?? ''), $field['variable']->rules ?: [])) {
                return new Result(false, __('simplify-eggupdates::egg-updates.messages.field_invalid', ['field' => $field['variable']->name]));
            }
        }
        $plan = self::plan($egg, $previous);
        foreach ($plan as $id => $change) {
            Server::query()->whereKey($id)->update($change);
        }
        $messages = [];
        if ($plan !== []) {
            $messages[] = trans_choice('simplify-eggupdates::egg-updates.messages.moved', count($plan), ['egg' => $egg->name, 'count' => count($plan)]);
        }
        foreach ($fields as $env => $field) {
            foreach ($field['servers'] as $server) {
                ServerVariable::query()->updateOrCreate(
                    ['server_id' => $server, 'variable_id' => $field['variable']->id],
                    ['variable_value' => (string) $values[$env]],
                );
            }
            $messages[] = __('simplify-eggupdates::egg-updates.messages.filled', ['field' => $field['variable']->name, 'count' => count($field['servers'])]);
        }

        return new Result(true, $messages === []
            ? __('simplify-eggupdates::egg-updates.messages.nothing_to_move', ['egg' => $egg->name])
            : implode(' ', $messages));
    }

    /** How many servers Move servers would change; 0 when there is nothing to compare with. */
    public static function count(Egg $egg): int
    {
        $previous = self::previous($egg);
        if ($previous === null) {
            return 0;
        }
        $ids = array_keys(self::plan($egg, $previous));
        foreach (self::fields($egg) as $field) {
            $ids = [...$ids, ...$field['servers']];
        }

        return count(array_unique($ids));
    }

    /**
     * Fields the last update added as required, without a valid default, with the servers that have no value for
     * them. Pelican gives such a server an empty value, so its install or start fails.
     *
     * @return array<string, array{variable: EggVariable, servers: list<int>}>
     */
    public static function fields(Egg $egg): array
    {
        $previous = self::previous($egg);
        if ($previous === null) {
            return [];
        }
        $old = collect(is_array($previous['variables'] ?? null) ? $previous['variables'] : [])->pluck('env_variable')->all();
        $fields = [];
        /** @var EggVariable $variable */
        foreach ($egg->variables()->get() as $variable) {
            $rules = $variable->rules ?: [];
            if (in_array($variable->env_variable, $old, true) || ! in_array('required', $rules, true)
                || TestEnvironment::valid((string) ($variable->default_value ?? ''), $rules)) {
                continue;
            }
            $filled = ServerVariable::query()->where('variable_id', $variable->id)->where('variable_value', '!=', '')->select('server_id');
            $servers = Server::query()->where('egg_id', $egg->id)->whereNotIn('id', $filled)->orderBy('id')->pluck('id')->all();
            if ($servers !== []) {
                $fields[$variable->env_variable] = ['variable' => $variable, 'servers' => $servers];
            }
        }

        return $fields;
    }

    /**
     * The definition before the last update: the kept backup, but only while that update is in place (after Put back
     * the egg is the backup itself and nothing needs to move).
     *
     * @return array<mixed>|null
     */
    public static function previous(Egg $egg): ?array
    {
        if (EggUpdate::query()->whereKey($egg->id)->value('applied_hash') === null) {
            return null;
        }
        $definition = json_decode((string) EggUpdater::latest($egg)?->definition, true);

        return is_array($definition) ? $definition : null;
    }

    /**
     * @param  array<mixed>  $previous
     * @return array<int, array{startup?: string, image?: string}>
     */
    public static function plan(Egg $egg, array $previous): array
    {
        $servers = Server::query()->where('egg_id', $egg->id)->get(['id', 'startup', 'image'])
            ->map(fn (Server $server) => ['id' => $server->id, 'startup' => $server->startup, 'image' => $server->image])
            ->all();

        return self::moves($servers, $previous, $egg->startup_commands ?? [], $egg->docker_images ?? []);
    }

    /**
     * A server moves only when its command or image came from the previous definition and the new one no longer has
     * it; it then gets the entry with the same name, or the new default. Anything the server set itself stays.
     *
     * @param  list<array{id: int, startup: ?string, image: ?string}>  $servers
     * @param  array<mixed>  $previous
     * @param  array<string, string>  $newStartups
     * @param  array<string, string>  $newImages
     * @return array<int, array{startup?: string, image?: string}>
     */
    public static function moves(array $servers, array $previous, array $newStartups, array $newImages): array
    {
        $oldStartups = self::startups($previous);
        $oldImages = is_array($previous['docker_images'] ?? null) ? array_filter($previous['docker_images'], 'is_string') : [];
        $plan = [];
        foreach ($servers as $server) {
            $change = [];
            $next = self::next($server['startup'], $oldStartups, $newStartups);
            if ($next !== null) {
                $change['startup'] = $next;
            }
            $next = self::next($server['image'], $oldImages, $newImages);
            if ($next !== null) {
                $change['image'] = $next;
            }
            if ($change !== []) {
                $plan[$server['id']] = $change;
            }
        }

        return $plan;
    }

    /**
     * @param  array<string, string>  $old
     * @param  array<string, string>  $new
     */
    private static function next(?string $current, array $old, array $new): ?string
    {
        $name = array_search($current, $old, true);
        if ($name === false || in_array($current, $new, true)) {
            return null;
        }
        $next = $new[$name] ?? reset($new);

        return is_string($next) && $next !== '' ? $next : null;
    }

    /**
     * Startup commands of an exported definition, name => command (older exports have one "startup").
     *
     * @param  array<mixed>  $definition
     * @return array<string, string>
     */
    private static function startups(array $definition): array
    {
        if (is_array($definition['startup_commands'] ?? null)) {
            return array_filter($definition['startup_commands'], 'is_string');
        }

        return is_string($definition['startup'] ?? null) ? ['Default' => $definition['startup']] : [];
    }
}
