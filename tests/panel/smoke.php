<?php

// Loads the plugin inside a real Pelican panel: the page renders, a check finds a new version at a (fake) update URL,
// updating keeps the previous version, Put back returns it, the settings save and the scheduled command runs.
// Run from the panel folder after `php artisan p:plugin:install simplify-egg-updates`:
// php plugins/simplify-egg-updates/tests/panel/smoke.php

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Models\User;
use App\Services\Eggs\Sharing\EggExporterService;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Simplify\EggUpdates\Enums\Mode;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
use Simplify\EggUpdates\Models\EggBackup;
use Simplify\EggUpdates\Models\EggUpdate;
use Simplify\EggUpdates\Services\EggUpdater;
use Simplify\EggUpdates\Services\Settings;
use Simplify\EggUpdates\Services\UpdateChecker;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failed = false;
function step(string $what, callable $run): void
{
    global $failed;
    try {
        $run();
        echo "PASS $what\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "FAIL $what: ".get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    }
}
function http(User $user, string $url): TestResponse
{
    $client = new class(app())
    {
        use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $client->actingAs($user)->get($url);
}
function expect(bool $ok, string $why): void
{
    if (! $ok) {
        throw new RuntimeException($why);
    }
}

$admin = User::where('email', 'smoke-egg-updates@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-egg-updates@example.com', 'root_admin' => true]);
$egg = Egg::factory()->create(['name' => 'Smoke Paper', 'update_url' => 'https://eggs.example.test/egg-smoke-paper.json']);
// The fake update URL publishes the same egg under a new name: a new version.
$remote = json_decode(app(EggExporterService::class)->handle($egg->id, EggFormat::JSON), true);
$remote['name'] = 'Smoke Paper 2';
Http::fake(['eggs.example.test/*' => Http::response(json_encode($remote))]);
Filament::setCurrentPanel(Filament::getPanel('admin'));

step('the page renders without updates', function () use ($admin) {
    $response = http($admin, EggUpdates::getUrl(panel: 'admin'));
    expect($response->getStatusCode() === 200, 'HTTP '.$response->getStatusCode().($response->exception ? ': '.$response->exception->getMessage() : ''));
});
step('Check now finds the new version and tells root admins', function () use ($admin, $egg) {
    Livewire::actingAs($admin)->test(EggUpdates::class)->callAction('check')->assertHasNoErrors();
    expect(EggUpdate::query()->find($egg->id)?->found_hash !== null, 'the update was not found');
    expect(cache()->get('eggs.'.$egg->uuid.'.update') === true, 'Pelican\'s own Update button does not follow');
});
step('the page lists the update', function () use ($admin) {
    $response = http($admin, EggUpdates::getUrl(panel: 'admin'));
    expect($response->getStatusCode() === 200, 'HTTP '.$response->getStatusCode());
    expect(str_contains($response->getContent(), 'Smoke Paper'), 'the egg is not listed');
});
step('updating keeps the previous version', function () use ($egg) {
    $result = EggUpdater::update($egg);
    expect($result->ok, $result->message);
    expect($egg->fresh()->name === 'Smoke Paper 2', 'the egg did not change');
    expect(EggBackup::query()->where('egg_id', $egg->id)->count() === 1, 'no backup kept');
    expect(EggUpdate::query()->find($egg->id)->found_hash === null, 'still listed as an update');
});
step('a new check does not offer the installed version again', function () use ($egg) {
    expect(UpdateChecker::check() === [], 'offered again');
});
step('Put back returns the previous version, held', function () use ($egg) {
    $result = EggUpdater::putBack($egg->fresh());
    expect($result->ok, $result->message);
    expect($egg->fresh()->name === 'Smoke Paper', 'the old name is not back');
    expect(EggUpdate::query()->find($egg->id)->held === true, 'not held');
    expect(EggBackup::query()->where('egg_id', $egg->id)->count() === 0, 'the used backup stays');
});
step('the settings save from the page', function () use ($admin) {
    Livewire::actingAs($admin)->test(EggUpdates::class)->callAction('settings', data: ['mode' => 'auto'])->assertHasNoErrors();
    expect(Settings::mode() === Mode::Auto, 'mode not saved');
});
step('the scheduled command runs', function () {
    expect(Artisan::call('simplify-egg-updates:check', ['--force' => true]) === 0, Artisan::output());
});
step('the root admin got notifications', function () use ($admin) {
    expect($admin->fresh()->notifications()->count() > 0, 'no notification in the bell');
});
exit($failed ? 1 : 0);
