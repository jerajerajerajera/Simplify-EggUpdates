<?php

// Loads the plugin inside a real Pelican panel: the page renders, a check finds a new version at a (fake) update URL,
// updating keeps the previous version, Put back returns it, the settings save and the scheduled command updates an egg.
// Run from the panel folder after `php artisan p:plugin:install simplify-eggupdates`:
// php plugins/simplify-eggupdates/tests/panel/smoke.php

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Models\Role;
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
use Simplify\EggUpdates\Addon\EggUpdatesAddon;
use Simplify\EggUpdates\Enums\Mode;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
use Simplify\EggUpdates\Models\EggBackup;
use Simplify\EggUpdates\Models\EggTest;
use Simplify\EggUpdates\Models\EggUpdate;
use Simplify\EggUpdates\Services\EggTester;
use Simplify\EggUpdates\Services\EggUpdater;
use Simplify\EggUpdates\Services\ServerMover;
use Simplify\EggUpdates\Services\Settings;
use Simplify\EggUpdates\Services\UpdateChecker;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Laravel's console handler would print an uncaught error and still exit 0, which CI reads as a pass.
set_exception_handler(function (Throwable $e) {
    echo 'FAIL uncaught '.get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    exit(1);
});

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
function must(bool $ok, string $why): void
{
    if (! $ok) {
        throw new RuntimeException($why);
    }
}

$admin = User::where('email', 'smoke-egg-updates@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-egg-updates@example.com']);
$admin->syncRoles(Role::getRootAdmin());
$egg = Egg::factory()->create(['name' => 'Smoke Paper', 'update_url' => 'https://eggs.example.test/egg-smoke-paper.json']);
// The fake update URL publishes the same egg under a new name: a new version.
$remote = json_decode(app(EggExporterService::class)->handle($egg->id, EggFormat::JSON), true);
$remote['name'] = 'Smoke Paper 2';
Http::fake(['eggs.example.test/*' => function () use (&$remote) {
    return Http::response(json_encode($remote));
}]);
Filament::setCurrentPanel(Filament::getPanel('admin'));

step('the smoke user is a root admin', function () use ($admin) {
    must($admin->fresh()->isRootAdmin(), 'not a root admin');
});
step('the page renders without updates', function () use ($admin) {
    $response = http($admin, EggUpdates::getUrl(panel: 'admin'));
    must($response->getStatusCode() === 200, 'HTTP '.$response->getStatusCode().($response->exception ? ': '.$response->exception->getMessage() : ''));
});
step('Check now finds the new version and tells root admins', function () use ($admin, $egg) {
    Livewire::actingAs($admin)->test(EggUpdates::class)->callAction('check')->assertHasNoErrors();
    must(EggUpdate::query()->find($egg->id)?->found_hash !== null, 'the update was not found');
    must(cache()->get('eggs.'.$egg->uuid.'.update') === true, 'Pelican\'s own Update button does not follow');
});
step('the page lists the update', function () use ($admin) {
    $response = http($admin, EggUpdates::getUrl(panel: 'admin'));
    must($response->getStatusCode() === 200, 'HTTP '.$response->getStatusCode());
    must(str_contains($response->getContent(), 'Smoke Paper'), 'the egg is not listed');
});
step('updating keeps the previous version', function () use ($egg) {
    $result = EggUpdater::update($egg);
    must($result->ok, $result->message);
    must($egg->fresh()->name === 'Smoke Paper 2', 'the egg did not change');
    must(EggBackup::query()->where('egg_id', $egg->id)->count() === 1, 'no backup kept');
    must(EggUpdate::query()->find($egg->id)->found_hash === null, 'still listed as an update');
});
step('a new check does not offer the installed version again', function () {
    must(UpdateChecker::check() === [], 'offered again');
});
step('Put back returns the previous version, held', function () use ($egg) {
    $result = EggUpdater::putBack($egg->fresh());
    must($result->ok, $result->message);
    must($egg->fresh()->name === 'Smoke Paper', 'the old name is not back');
    must(EggUpdate::query()->find($egg->id)->held === true, 'not held');
    must(EggBackup::query()->where('egg_id', $egg->id)->count() === 0, 'the used backup stays');
});
step('Test first without a node reports why and leaves nothing behind', function () use ($egg) {
    $eggs = Egg::query()->count();
    $result = EggTester::queue($egg->fresh());
    must($result->ok, $result->message);
    $state = EggUpdate::query()->find($egg->id);
    must($state->test_ok === false, 'the failed test is not remembered');
    must(EggTest::query()->doesntExist(), 'the test is still queued');
    must(Egg::query()->count() === $eggs, 'the imported copy was not removed');
    must($egg->fresh()->name === 'Smoke Paper', 'the egg changed without a test');
});
step('the settings save', function () use ($admin) {
    auth()->login($admin);
    Settings::saveForm(['mode' => 'off']);
    must(Settings::mode() === Mode::Off, 'mode not saved directly');
    // Filament's fillForm() does not reach a header action's form here, so set its state the way the browser does.
    Livewire::actingAs($admin)->test(EggUpdates::class)
        ->mountAction('settings')
        ->set('mountedActions.0.data.mode', 'test')
        ->callMountedAction()
        ->assertHasNoFormErrors();
    must(Settings::mode() === Mode::Test, 'mode not saved from the page: '.Settings::mode()->value);
});
step('Simplify\'s setup asks when eggs are updated and saves it', function () use ($admin) {
    if (! class_exists('Simplify\Core\Addons\Addon')) {
        require __DIR__.'/stubs/Addon.php';
    }
    $addon = app(EggUpdatesAddon::class);
    $customer = User::where('email', 'smoke-egg-updates-customer@example.com')->first()
        ?? User::factory()->create(['email' => 'smoke-egg-updates-customer@example.com']);
    auth()->setUser($customer);
    must($addon->setupFields() === [], 'a customer gets the setup fields');
    auth()->setUser($admin);
    must(count($addon->setupFields()) === 1 && $addon->setupState() === ['mode' => 'test'], 'the setup field or value is missing');
    $addon->saveSetup(['mode' => 'ask']);
    must(Settings::mode() === Mode::Ask, 'the setup answer was not saved');
    $addon->saveSetup(['mode' => 'test']);
});
step('the scheduled command finds a newer version and updates the egg without servers', function () use (&$remote, $egg) {
    $remote['name'] = 'Smoke Paper 3';
    must(Artisan::call('simplify-eggupdates:check', ['--force' => true]) === 0, Artisan::output());
    must($egg->fresh()->name === 'Smoke Paper 3', 'not updated automatically: '.trim(Artisan::output()));
});
step('Move servers compares with the kept version', function () use ($egg) {
    must(ServerMover::previous($egg->fresh()) !== null, 'no previous version after the automatic update');
    must(ServerMover::count($egg->fresh()) === 0, 'an egg without servers has servers to move');
    $result = ServerMover::move($egg->fresh());
    must($result->ok, $result->message);
});
step('the root admin got notifications', function () use ($admin) {
    must($admin->fresh()->notifications()->count() > 0, 'no notification in the bell');
});
echo $failed ? "Some steps failed\n" : "All steps passed\n";
exit($failed ? 1 : 0);
