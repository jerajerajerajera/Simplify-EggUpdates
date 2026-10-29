<?php

use App\Models\Plugin;
use App\Services\Helpers\PluginService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Queue;

/*
 * Until version 1.1.0 this plugin was called "simplify-egg-updates". Both copies use the namespace
 * Simplify\EggUpdates, so the old folder has to go or the panel loads the old code for the new plugin. Its tables
 * and settings stay: they have the same names, so the new plugin simply keeps using them.
 */
return new class extends Migration
{
    private const OLD = 'simplify-egg-updates';

    public function up(): void
    {
        $old = Plugin::query()->find(self::OLD);
        if ($old === null || $old->namespace !== 'Simplify\\EggUpdates') {
            return;
        }

        // Its code may still be needed now, so the folder goes once the install is done: at the end of the command or
        // request, or, when the panel installs it from Admin → Plugins or the Hub, after that queue job (a queue worker
        // never terminates). deletePlugin only removes the files: an uninstall would roll back the migrations and drop
        // the tables with the settings.
        $done = false;
        $delete = function () use ($old, &$done): void {
            if (! $done) {
                $done = true;
                app(PluginService::class)->deletePlugin($old);
            }
        };
        app()->terminating($delete);
        Queue::after($delete);
    }

    public function down(): void {}
};
