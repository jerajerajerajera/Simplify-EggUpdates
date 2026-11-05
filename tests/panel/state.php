<?php

// Settings and data of Egg Updates as a user has them, for the update, reinstall and downgrade steps in checks.yml.
// Uses only the database, never the plugin's own classes, so it works whichever version is installed. Run from the
// panel folder: php <checkout>/tests/panel/state.php fill|check|clean
//   fill:  the mode Test first, an egg held after Put back, a kept previous version of it
//   check: all of that is still there (exit 1 and the reason when not)
//   clean: removes it again, so the smoke starts from an empty Egg Updates

use App\Models\Egg;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$name = 'CI kept egg';
$definition = '{"name":"CI kept egg","ci":"the previous version"}';
$egg = Egg::query()->where('name', $name)->first();

switch ($argv[1] ?? '') {
    case 'fill':
        $egg ??= Egg::factory()->create(['name' => $name]);
        DB::table('simplify_egg_update_settings')->updateOrInsert(['key' => 'mode'], ['value' => 'test']);
        DB::table('simplify_egg_updates')->updateOrInsert(['egg_id' => $egg->id], ['held' => true, 'applied_hash' => str_repeat('a', 32)]);
        DB::table('simplify_egg_update_backups')->insert(['egg_id' => $egg->id, 'definition' => $definition, 'created_at' => now()]);
        echo "Filled in: mode Test first, {$name} held with a kept previous version\n";
        break;
    case 'check':
        $lost = array_keys(array_filter([
            'the mode' => DB::table('simplify_egg_update_settings')->where('key', 'mode')->value('value') !== 'test',
            'the held egg' => $egg === null || ! DB::table('simplify_egg_updates')->where('egg_id', $egg->id)->where('held', true)->exists(),
            'the kept previous version' => $egg === null || DB::table('simplify_egg_update_backups')->where('egg_id', $egg->id)->value('definition') !== $definition,
        ]));
        if ($lost !== []) {
            echo 'Lost: '.implode(', ', $lost)."\n";
            exit(1);
        }
        echo "Kept: the mode, the held egg and its previous version\n";
        break;
    case 'clean':
        if ($egg !== null) {
            DB::table('simplify_egg_update_backups')->where('egg_id', $egg->id)->delete();
            DB::table('simplify_egg_updates')->where('egg_id', $egg->id)->delete();
            $egg->delete();
        }
        DB::table('simplify_egg_update_settings')->where('key', 'mode')->delete();
        break;
    default:
        echo "Usage: state.php fill|check|clean\n";
        exit(2);
}
