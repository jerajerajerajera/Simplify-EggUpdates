<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Models\Egg;
use Illuminate\Support\Facades\Cache;
use Simplify\EggUpdates\Models\EggUpdate;

/**
 * A short log a root admin can copy for support: versions, the mode, the eggs in the list and the last events. Only in
 * version 1.1.5, to help testing; addresses, e-mail, internal links, tokens and passwords are left out.
 */
final class DebugLog
{
    private const KEY = 'simplify-eggupdates.debug-log';

    private const EVENTS = 50;

    public static function add(string $event): void
    {
        try {
            $events = Cache::get(self::KEY, []);
            $events[] = now()->format('Y-m-d H:i').' '.self::clean($event);
            Cache::forever(self::KEY, array_slice(is_array($events) ? $events : [], -self::EVENTS));
        } catch (\Throwable) {
            // The log must never break what it describes.
        }
    }

    public static function report(): string
    {
        $plugin = json_decode((string) @file_get_contents(__DIR__.'/../../plugin.json'), true);
        $lines = [
            'Simplify Egg Updates '.($plugin['version'] ?? '?').' · Pelican '.config('app.version').' · PHP '.PHP_VERSION,
            'Mode: '.Settings::mode()->value.' · last check '.(UpdateChecker::checkedAt()?->diffForHumans() ?? 'never'),
        ];
        foreach (EggUpdate::query()->get() as $state) {
            $egg = Egg::query()->withCount('servers')->find($state->egg_id);
            if ($egg === null || ($state->found_hash === null && $state->test_ok !== false && $state->link_failures === 0)) {
                continue;
            }
            $lines[] = $egg->name.': '.implode(' · ', array_filter([
                $state->found_hash !== null ? ($state->held ? 'put back' : 'update available') : 'current',
                $egg->servers_count.' servers',
                $state->test_ok === false ? 'last test failed: '.$state->test_reason : null,
                $state->link_failures > 0 ? 'update link fails ('.$state->link_failures.'x): '.$state->link_error : null,
            ]));
        }

        return self::clean(implode("\n", [...$lines, ...Cache::get(self::KEY, [])]));
    }

    /** Leaves out what must not be pasted anywhere: public egg links stay, so the egg can be found. */
    public static function clean(string $text): string
    {
        $text = (string) preg_replace_callback('#\bhttps?://[^\s"\'<>|]+#i', [self::class, 'url'], $text);

        return (string) preg_replace([
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            '/\b(token|password|passwd|pass|secret|api[_-]?key|key|auth\w*)(\s*[=:]\s*)\S+/i',
            '/\b\d{1,3}(\.\d{1,3}){3}\b/',
            '/(?<![\w:])(?=[0-9a-f:]*[a-f])(?=(?:[0-9a-f]{0,4}:){2})(?:[0-9a-f]{0,4}:){2,7}[0-9a-f]{0,4}(?![\w:])/i',
            '/\b[A-Za-z0-9+_=-]{32,}/',
        ], ['[email]', '$1$2[hidden]', '[ip]', '[ip]', '[hidden]'], $text);
    }

    /** @param  array<int, string>  $match */
    private static function url(array $match): string
    {
        $public = '#^https?://([a-z0-9-]+\.)*(github\.com|githubusercontent\.com|pelican\.dev)(/|$)#i';

        return preg_match($public, $match[0]) ? (string) preg_replace('/\?.*$/', '', $match[0]) : '[url]';
    }
}
