<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Enums\EggFormat;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Eggs\Sharing\EggImporterService;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\ServerDeletionService;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Simplify\EggUpdates\Models\EggTest;
use Simplify\EggUpdates\Models\EggUpdate;

/**
 * "Test first": before an egg that servers use is updated, the new definition is imported as a separate copy and a
 * test server is made from it, owned by a root admin, on a temporary port above the ranges hosts forward to players.
 * Only when that server installs and starts (Wings reports it running once the egg's "done" line shows) is the real
 * egg updated, with exactly the tested definition. Test server, port and copy are removed either way. One test runs
 * at a time; the scheduler moves it on every minute.
 */
final class EggTester
{
    /** Start of the external id of test servers. Simplify leaves these servers out of its lists. */
    public const MARK = 'simplify-egg-check-';

    private const INSTALL_SECONDS = 15 * 60;

    private const START_SECONDS = 3 * 60;

    /** Temporary test ports: above the usual game ranges, below the ephemeral range. */
    public const PORTS = [45000, 45999];

    /** What a test server gets. */
    private const MEMORY = 2048;

    private const DISK = 8192;

    private const CPU = 200;

    public static function isTestServer(?Server $server): bool
    {
        return $server !== null && str_starts_with((string) $server->external_id, self::MARK);
    }

    /** Queues a test of the egg's published update; it starts right away when no other test runs. */
    public static function queue(Egg $egg): Result
    {
        EggTest::query()->firstOrCreate(['egg_id' => $egg->id], ['stage' => EggTest::QUEUED, 'stage_at' => now()]);
        EggUpdate::for($egg->id)->fill(['test_ok' => null, 'test_reason' => null, 'tested_at' => null])->save();
        self::tick();

        return new Result(true, __('simplify-egg-updates::egg-updates.tests.queued', ['egg' => $egg->name]));
    }

    public static function stage(Egg $egg): ?string
    {
        return EggTest::query()->where('egg_id', $egg->id)->value('stage');
    }

    public static function busy(): bool
    {
        return EggTest::query()->exists();
    }

    /**
     * Called every minute: moves the running test on, or starts the next one.
     *
     * @return list<string>
     */
    public static function tick(): array
    {
        $running = EggTest::query()->where('stage', '!=', EggTest::QUEUED)->first();
        if ($running === null) {
            return self::next();
        }
        try {
            return self::advance($running);
        } catch (\Throwable $exception) {
            report($exception);

            return self::finish($running, false, __('simplify-egg-updates::egg-updates.tests.not_followed', ['reason' => Definition::reason($exception)]));
        }
    }

    /** @return list<string> */
    private static function next(): array
    {
        $test = EggTest::query()->where('stage', EggTest::QUEUED)->orderBy('id')->first();
        if ($test === null) {
            return [];
        }
        $egg = Egg::query()->find($test->egg_id);
        if ($egg === null) {
            $test->delete();

            return [];
        }
        try {
            self::begin($test, $egg);
        } catch (\Throwable $exception) {
            report($exception);
            $reason = $exception instanceof ValidationException
                ? __('simplify-egg-updates::egg-updates.tests.needs_value', ['fields' => implode(' ', array_merge(...array_values($exception->errors())))])
                : Definition::reason($exception);
            self::cleanUp($test);
            $test->delete();
            self::remember($egg, false, __('simplify-egg-updates::egg-updates.tests.not_made', ['reason' => $reason]));
            Notifier::rootAdmins(
                __('simplify-egg-updates::egg-updates.tests.not_tested_title', ['egg' => $egg->name]),
                __('simplify-egg-updates::egg-updates.tests.not_tested_body', ['reason' => $reason]),
                'warning',
            );

            return [$egg->name.': '.$reason];
        }

        return [__('simplify-egg-updates::egg-updates.tests.installing', ['egg' => $egg->name])];
    }

    /** Imports a copy of the new definition and makes the test server on a temporary port. */
    private static function begin(EggTest $test, Egg $egg): void
    {
        $remote = RemoteEgg::fetch($egg);
        $definition = RemoteEgg::parse($remote->content, $remote->format);
        // A fresh uuid and a new model, so the import never touches the real egg.
        unset($definition['uuid']);
        $definition['name'] = __('simplify-egg-updates::egg-updates.tests.copy_name', ['egg' => $egg->name]);
        $copy = app(EggImporterService::class)->fromContent(json_encode($definition, JSON_THROW_ON_ERROR), EggFormat::JSON, new Egg);
        $test->fill(['copy_egg_id' => $copy->id, 'content' => $remote->content, 'format' => $remote->format->name, 'hash' => $remote->hash])->save();

        $owner = User::query()->get()->first(fn (User $user) => $user->isRootAdmin());
        if ($owner === null) {
            throw new RuntimeException(__('simplify-egg-updates::egg-updates.tests.no_owner'));
        }
        $node = Node::query()->get()->first(fn (Node $node) => ! $node->isUnderMaintenance() && $node->isViable(self::MEMORY, self::DISK, self::CPU));
        if ($node === null) {
            throw new RuntimeException(__('simplify-egg-updates::egg-updates.tests.no_node'));
        }
        $allocation = self::allocation($node);
        $test->fill(['allocation_id' => $allocation->id])->save();

        $server = app(ServerCreationService::class)->handle([
            'external_id' => self::MARK.$egg->id.'-'.now()->getTimestamp(),
            'name' => __('simplify-egg-updates::egg-updates.tests.server_name', ['egg' => $egg->name]),
            'description' => __('simplify-egg-updates::egg-updates.tests.server_description'),
            'owner_id' => $owner->id,
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'egg_id' => $copy->id,
            'environment' => self::environment($copy),
            'memory' => self::MEMORY,
            'swap' => 0,
            'disk' => self::DISK,
            'io' => 500,
            'cpu' => self::CPU,
            'database_limit' => 0,
            'allocation_limit' => 0,
            'backup_limit' => 0,
            'oom_killer' => false,
            'skip_scripts' => false,
            'start_on_completion' => false,
        ]);
        $test->fill(['server_id' => $server->id, 'stage' => EggTest::INSTALLING, 'stage_at' => now()])->save();
    }

    /** The default value of every variable of the egg, as a new server gets them. */
    public static function environment(Egg $egg): array
    {
        return $egg->variables()->get()
            ->mapWithKeys(fn (EggVariable $variable) => [$variable->env_variable => (string) ($variable->default_value ?? '')])
            ->all();
    }

    /** A free port in the test range on the node's first address, made for this test only. */
    private static function allocation(Node $node): Allocation
    {
        $ip = Allocation::query()->where('node_id', $node->id)->orderBy('id')->value('ip') ?? '0.0.0.0';
        $taken = Allocation::query()->where('node_id', $node->id)->where('ip', $ip)->whereBetween('port', self::PORTS)->pluck('port')->all();
        $port = self::freePort($taken);
        if ($port === null) {
            throw new RuntimeException(__('simplify-egg-updates::egg-updates.tests.no_port', ['from' => self::PORTS[0], 'to' => self::PORTS[1]]));
        }

        return Allocation::query()->create(['node_id' => $node->id, 'ip' => $ip, 'port' => $port]);
    }

    /** The highest port of the test range that is not taken, or null. */
    public static function freePort(array $taken): ?int
    {
        $taken = array_flip(array_map('intval', $taken));
        for ($port = self::PORTS[1]; $port >= self::PORTS[0]; $port--) {
            if (! isset($taken[$port])) {
                return $port;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function advance(EggTest $test): array
    {
        $server = $test->server_id ? Server::query()->find($test->server_id) : null;
        if ($server === null) {
            return self::finish($test, false, __('simplify-egg-updates::egg-updates.tests.deleted'));
        }
        $status = $server->status?->value ?? $server->status;
        $age = $test->stage_at?->diffInSeconds(now()) ?? 0;
        if ($test->stage === EggTest::INSTALLING) {
            if (in_array($status, ['install_failed', 'reinstall_failed'], true)) {
                return self::finish($test, false, __('simplify-egg-updates::egg-updates.tests.not_installed'), $server);
            }
            if ($status === null || $status === '') {
                app(DaemonServerRepository::class)->setServer($server)->power('start')->throw();
                $test->fill(['stage' => EggTest::STARTING, 'stage_at' => now()])->save();

                return [__('simplify-egg-updates::egg-updates.tests.starting', ['egg' => $server->name])];
            }

            return $age > self::INSTALL_SECONDS ? self::finish($test, false, __('simplify-egg-updates::egg-updates.tests.install_slow'), $server) : [];
        }
        $state = app(DaemonServerRepository::class)->setServer($server)->getDetails()['state'] ?? 'unknown';
        if ($state === 'running') {
            return self::finish($test, true, '');
        }
        if ($state === 'offline' && $age > 30) {
            return self::finish($test, false, __('simplify-egg-updates::egg-updates.tests.stopped'), $server);
        }

        return $age > self::START_SECONDS ? self::finish($test, false, __('simplify-egg-updates::egg-updates.tests.start_slow'), $server) : [];
    }

    /**
     * Updates the egg on success and reports on failure; cleans up both ways.
     *
     * @return list<string>
     */
    private static function finish(EggTest $test, bool $ok, string $reason, ?Server $server = null): array
    {
        $egg = Egg::query()->find($test->egg_id);
        $log = $ok || $server === null ? '' : self::logTail($server);
        self::cleanUp($test);
        if ($ok && $egg !== null && $test->content !== null) {
            $format = $test->format === EggFormat::YAML->name ? EggFormat::YAML : EggFormat::JSON;
            $result = EggUpdater::update($egg, new RemoteEgg($test->content, $format, (string) $test->hash));
            $ok = $result->ok;
            $reason = $ok ? '' : $result->message;
        }
        $test->delete();
        if ($egg === null) {
            return [];
        }
        self::remember($egg, $ok, $reason);
        if ($ok) {
            Notifier::rootAdmins(
                __('simplify-egg-updates::egg-updates.tests.passed_title', ['egg' => $egg->name]),
                __('simplify-egg-updates::egg-updates.tests.passed_body'),
                'success',
            );

            return [__('simplify-egg-updates::egg-updates.tests.passed_title', ['egg' => $egg->name])];
        }
        Notifier::rootAdmins(
            __('simplify-egg-updates::egg-updates.tests.failed_title', ['egg' => $egg->name]),
            __('simplify-egg-updates::egg-updates.tests.failed_body', ['reason' => $reason])
                .($log !== '' ? ' '.__('simplify-egg-updates::egg-updates.tests.last_lines', ['lines' => $log]) : ''),
            'danger',
        );

        return [$egg->name.': '.$reason];
    }

    private static function remember(Egg $egg, bool $ok, string $reason): void
    {
        $state = EggUpdate::for($egg->id);
        if ($state->exists || ! $ok) {
            $state->fill(['test_ok' => $ok, 'test_reason' => $ok ? null : $reason, 'tested_at' => now()])->save();
        }
    }

    /** Removes the test server, its port and the imported copy. */
    private static function cleanUp(EggTest $test): void
    {
        $server = $test->server_id ? Server::query()->find($test->server_id) : null;
        // Only ever the test server itself, never a server that took its id.
        if (self::isTestServer($server)) {
            try {
                app(DaemonServerRepository::class)->setServer($server)->power('kill');
            } catch (\Throwable) {
                // Already stopped, or Wings is unreachable: the forced delete below still removes it from the panel.
            }
            try {
                app(ServerDeletionService::class)->withForce()->handle($server);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        if ($test->allocation_id) {
            Allocation::query()->whereKey($test->allocation_id)->whereNull('server_id')->delete();
        }
        $copy = $test->copy_egg_id ? Egg::query()->find($test->copy_egg_id) : null;
        if ($copy !== null && ! Server::query()->where('egg_id', $copy->id)->exists()) {
            $copy->delete();
        }
    }

    private static function logTail(Server $server): string
    {
        try {
            $lines = preg_split('/\R/', trim(app(DaemonServerRepository::class)->setServer($server)->getInstallLogs())) ?: [];

            return mb_strimwidth(implode(' | ', array_slice(array_filter($lines), -3)), 0, 300, '…');
        } catch (\Throwable) {
            return '';
        }
    }
}
