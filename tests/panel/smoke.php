<?php

// Loads the plugin inside a real Pelican panel: the page renders, a check finds a new version at a (fake) update URL,
// updating keeps the previous version, Put back returns it, the settings save and the scheduled command updates an egg.
// Then every function once more through the page's own buttons: each mode, Update all, Move servers with servers,
// and Test first against a fake Wings until the test server, its port and the copy are gone again.
// Run from the panel folder after `php artisan p:plugin:install simplify-eggupdates`:
// php plugins/simplify-eggupdates/tests/panel/smoke.php

use App\Enums\EggFormat;
use App\Enums\ServerState;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Services\Eggs\Sharing\EggExporterService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
use Simplify\EggUpdates\Services\Runner;
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
/** An egg with one startup command and one image, whose update URL publishes it with $change applied. */
function remoteEgg(string $name, array &$remotes, array $change): Egg
{
    $egg = Egg::factory()->create([
        'name' => $name,
        'update_url' => 'https://eggs.example.test/'.Str::slug($name).'.json',
        'startup_commands' => ['Default' => 'java -jar old.jar'],
        'docker_images' => ['Java 17' => 'ghcr.io/smoke/java:17'],
    ]);
    $remotes[Str::slug($name)] = array_replace(json_decode(app(EggExporterService::class)->handle($egg->id, EggFormat::JSON), true), $change);

    return $egg;
}
/** @return list<string> titles of the user's notifications in Pelican's bell */
function bell(User $user): array
{
    return $user->fresh()->notifications()->get()->map(fn ($notification) => (string) ($notification->data['title'] ?? ''))->all();
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
// Further eggs publish their own definition under their slug; Smoke Paper uses $remote.
$remotes = [];
// A fake Wings for the test node: it accepts every call and reports the state set in $wings.
$wings = 'running';
Http::fake([
    'eggs.example.test/*' => function ($request) use (&$remote, &$remotes) {
        return Http::response(json_encode($remotes[basename($request->url(), '.json')] ?? $remote));
    },
    'wings.smoke.test*' => function ($request) use (&$wings) {
        if (str_ends_with($request->url(), '/install-logs')) {
            return Http::response(['data' => "Pulling image\nInstall failed"]);
        }

        return $request->method() === 'GET' ? Http::response(['state' => $wings, 'utilization' => []]) : Http::response([], 204);
    },
]);
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
    // The phone cell: servers and since on one line under the name.
    must(str_contains($response->getContent(), 'No servers · '), 'the phone summary is missing');
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
step('Off checks nothing, Ask only tells, Auto updates eggs no server uses', function () use ($admin, &$remotes) {
    $egg = remoteEgg('Smoke Modes', $remotes, ['name' => 'Smoke Modes 2']);
    Settings::saveForm(['mode' => 'off']);
    Runner::run();
    must(EggUpdate::query()->find($egg->id)?->found_hash === null, 'Off still checked');
    Settings::saveForm(['mode' => 'ask']);
    $before = count(bell($admin));
    Runner::run(true);
    must(EggUpdate::query()->find($egg->id)?->found_hash !== null, 'Ask did not find the update');
    must($egg->fresh()->name === 'Smoke Modes', 'Ask updated without asking');
    must(count(bell($admin)) > $before, 'Ask did not tell the root admin');
    must(Runner::handle([$egg->fresh()], Mode::Auto) !== [], 'Auto did nothing');
    must($egg->fresh()->name === 'Smoke Modes 2', 'Auto did not update an egg without servers');
    // The notification names the egg as it is called after the update.
    must(in_array('Smoke Modes 2 egg updated', bell($admin), true), 'no "updated" notification: '.implode(' / ', bell($admin)));
});
step('the buttons Check now, Update all, Put back and Update on the page', function () use ($admin, &$remotes) {
    $first = remoteEgg('Smoke Alpha', $remotes, ['name' => 'Smoke Alpha 2']);
    $second = remoteEgg('Smoke Beta', $remotes, ['name' => 'Smoke Beta 2']);
    Settings::saveForm(['mode' => 'ask']);
    $page = Livewire::actingAs($admin)->test(EggUpdates::class)->callAction('check')->assertHasNoErrors()
        ->assertActionVisible('update_all')
        ->callAction('update_all')->assertHasNoErrors();
    must($first->fresh()->name === 'Smoke Alpha 2' && $second->fresh()->name === 'Smoke Beta 2', 'Update all left an egg behind');
    $page->callAction(TestAction::make('put_back')->table($first->fresh()))->assertHasNoErrors();
    must($first->fresh()->name === 'Smoke Alpha', 'Put back on the page did not restore the egg');
    must(EggUpdate::query()->find($first->id)->held === true, 'not held after Put back');
    $page->callAction(TestAction::make('update')->table($first->fresh()))->assertHasNoErrors();
    must($first->fresh()->name === 'Smoke Alpha 2', 'Update on the page did not update the egg');
});
step('Move servers on the page moves servers off the removed image and command, and keeps their own', function () use ($admin, &$remotes) {
    $egg = remoteEgg('Smoke Move', $remotes, [
        'startup_commands' => ['Default' => 'java -jar new.jar'],
        'docker_images' => ['Java 21' => 'ghcr.io/smoke/java:21'],
    ]);
    $old = Server::factory()->create(['egg_id' => $egg->id, 'startup' => 'java -jar old.jar', 'image' => 'ghcr.io/smoke/java:17']);
    $own = Server::factory()->create(['egg_id' => $egg->id, 'startup' => 'java -jar mine.jar', 'image' => 'ghcr.io/me/java:17']);
    Settings::saveForm(['mode' => 'ask']);
    Runner::run(true);
    $result = EggUpdater::update($egg->fresh());
    must($result->ok, $result->message);
    must(ServerMover::count($egg->fresh()) === 1, 'expected one server to move, got '.ServerMover::count($egg->fresh()));
    Livewire::actingAs($admin)->test(EggUpdates::class)
        ->assertActionVisible(TestAction::make('move')->table($egg->fresh()))
        ->callAction(TestAction::make('move')->table($egg->fresh()))->assertHasNoErrors();
    $old->refresh();
    must($old->startup === 'java -jar new.jar' && $old->image === 'ghcr.io/smoke/java:21', 'not moved: '.$old->startup.' '.$old->image);
    $own->refresh();
    must($own->startup === 'java -jar mine.jar' && $own->image === 'ghcr.io/me/java:17', 'a server\'s own command or image changed');
    must(ServerMover::count($egg->fresh()) === 0, 'still servers to move');
});
// Test first on a node with a fake Wings: one test that passes and one whose install fails. Both must leave no test
// server, no test port and no imported copy behind.
$node = Node::factory()->create(['fqdn' => 'wings.smoke.test', 'memory' => 0, 'disk' => 0, 'cpu' => 0]);
$tested = remoteEgg('Smoke Tested', $remotes, ['name' => 'Smoke Tested 2']);
Server::factory()->create(['egg_id' => $tested->id]);
$leftovers = function () {
    $servers = Server::query()->where('external_id', 'like', EggTester::MARK.'%')->count();
    $ports = Allocation::query()->whereBetween('port', EggTester::PORTS)->count();
    $copies = Egg::query()->where('name', 'like', '%(update test)')->count();

    return $servers + $ports + $copies + EggTest::query()->count();
};
step('Test first on the page makes a test server on a test port from a copy', function () use ($admin, $tested, $node) {
    Settings::saveForm(['mode' => 'ask']);
    Runner::run(true);
    must(EggUpdate::query()->find($tested->id)?->found_hash !== null, 'the update was not found');
    Livewire::actingAs($admin)->test(EggUpdates::class)
        ->callAction(TestAction::make('test')->table($tested->fresh()))->assertHasNoErrors();
    $test = EggTest::query()->where('egg_id', $tested->id)->first();
    must($test?->stage === 'installing', 'not installing: '.($test?->stage ?? 'no test').' '.EggUpdate::query()->find($tested->id)?->test_reason);
    $server = Server::query()->find($test->server_id);
    must(EggTester::isTestServer($server) && (int) $server->node_id === (int) $node->id, 'no test server on the node');
    $port = Allocation::query()->find($test->allocation_id)?->port;
    must($port >= EggTester::PORTS[0] && $port <= EggTester::PORTS[1], 'port '.$port.' is outside the test range');
    must((int) $server->egg_id === (int) $test->copy_egg_id && (int) $test->copy_egg_id !== (int) $tested->id, 'the test server does not use a copy');
    must($tested->fresh()->name === 'Smoke Tested', 'the real egg changed before the test');
});
step('a passing test updates the egg, cleans up and tells the root admin', function () use ($admin, $tested, $leftovers, &$wings) {
    $test = EggTest::query()->where('egg_id', $tested->id)->firstOrFail();
    Server::query()->whereKey($test->server_id)->update(['status' => null]);
    EggTester::tick();
    must(EggTester::stage($tested) === 'starting', 'not starting after the install: '.EggTester::stage($tested));
    $wings = 'running';
    EggTester::tick();
    must($tested->fresh()->name === 'Smoke Tested 2', 'not updated after a passing test');
    must(EggUpdate::query()->find($tested->id)->test_ok === true, 'the passed test is not remembered');
    must($leftovers() === 0, 'left behind after the test: '.$leftovers());
    must(collect(bell($admin))->contains(fn ($title) => str_contains($title, 'Smoke Tested')), 'no notification about the test');
});
step('a failing test keeps the egg, says why and cleans up', function () use ($admin, $tested, $leftovers, &$remotes) {
    // In the mode Test first, the scheduled run itself starts the test of a used egg.
    $remotes['smoke-tested']['name'] = 'Smoke Tested 3';
    Settings::saveForm(['mode' => 'test']);
    Runner::run(true);
    $test = EggTest::query()->where('egg_id', $tested->id)->firstOrFail();
    Server::query()->whereKey($test->server_id)->update(['status' => ServerState::InstallFailed]);
    $before = count(bell($admin));
    EggTester::tick();
    must($tested->fresh()->name === 'Smoke Tested 2', 'updated after a failed test');
    $state = EggUpdate::query()->find($tested->id);
    must($state->test_ok === false && $state->test_reason !== null, 'the failure is not remembered');
    must($state->found_hash !== null, 'the update is no longer listed');
    must($leftovers() === 0, 'left behind after the failed test: '.$leftovers());
    must(count(bell($admin)) > $before, 'no notification about the failed test');
});
echo $failed ? "Some steps failed\n" : "All steps passed\n";
exit($failed ? 1 : 0);
