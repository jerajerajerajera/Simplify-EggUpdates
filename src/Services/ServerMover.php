<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Models\Egg;
use App\Models\Server;
use Simplify\EggUpdates\Models\EggUpdate;

/**
 * Existing servers keep their own startup command and Docker image: Pelican copies both into the server when it is
 * created. This moves servers that still use what the egg's previous version offered, and the update removed or
 * changed, to the new one. It applies at their next start.
 */
final class ServerMover
{
    public static function move(Egg $egg): Result
    {
        $previous = self::previous($egg);
        if ($previous === null) {
            return new Result(false, __('simplify-eggupdates::egg-updates.messages.nothing_to_compare', ['egg' => $egg->name]));
        }
        $plan = self::plan($egg, $previous);
        foreach ($plan as $id => $change) {
            Server::query()->whereKey($id)->update($change);
        }
        $count = count($plan);

        return new Result(true, $count === 0
            ? __('simplify-eggupdates::egg-updates.messages.nothing_to_move', ['egg' => $egg->name])
            : trans_choice('simplify-eggupdates::egg-updates.messages.moved', $count, ['egg' => $egg->name, 'count' => $count]));
    }

    /** How many servers Move servers would change; 0 when there is nothing to compare with. */
    public static function count(Egg $egg): int
    {
        $previous = self::previous($egg);

        return $previous === null ? 0 : count(self::plan($egg, $previous));
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
