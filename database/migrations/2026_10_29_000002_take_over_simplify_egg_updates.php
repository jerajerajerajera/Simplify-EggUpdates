<?php

use App\Models\Plugin;
use App\Services\Helpers\PluginService;
use Illuminate\Database\Migrations\Migration;

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

        // Its code may still be needed in this request, so the folder goes once the request is done. deletePlugin only
        // removes the files: an uninstall would roll back the migrations and drop the tables with the settings.
        app()->terminating(fn () => app(PluginService::class)->deletePlugin($old));
    }

    public function down(): void {}
};
