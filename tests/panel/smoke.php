<?php

// Loads the plugin inside a real Pelican panel: the page renders, a check finds a new version at a (fake) update URL,
// updating keeps the previous version, Put back returns it, the settings save and the scheduled command updates an egg.
// Then every function once more through the page's own buttons: each mode, Update all, Move servers with servers,
// and Test first against a fake Wings until the test server, its port and the copy are gone again.
// Run from the panel folder after `php artisan p:plugin:install simplify-eggupdates`:
// php plugins/simplify-eggupdates/tests/panel/smoke.php

use App\Contracts\Plugins\HasPluginSettings;
use App\Enums\EggFormat;
use App\Enums\ServerState;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\User;
use App\Services\Eggs\Sharing\EggExporterService;
use App\Services\Eggs\Sharing\EggImporterService;
use App\Services\Servers\ServerDeletionService;
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
use Simplify\EggUpdates\Services\Definition;
use Simplify\EggUpdates\Services\EggTester;
use Simplify\EggUpdates\Services\EggUpdater;
use Simplify\EggUpdates\Services\RemoteEgg;
use Simplify\EggUpdates\Services\Runner;
use Simplify\EggUpdates\Services\ServerMover;
use Simplify\EggUpdates\Services\Settings;
use Simplify\EggUpdates\Services\TestEnvironment;
use Simplify\EggUpdates\Services\UpdateChecker;
use Simplify\EggUpdates\SimplifyEggUpdatesPlugin;
use Symfony\Component\Yaml\Yaml;

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
// Update URLs (by slug) that answer 404, like an egg whose maker moved or removed it.
$broken = [];
// A fake Wings for the test node: it accepts every call and reports the state set in $wings.
$wings = 'running';
Http::fake([
    'eggs.example.test/*' => function ($request) use (&$remote, &$remotes, &$broken) {
        if (in_array(basename($request->url(), '.json'), $broken, true)) {
            return Http::response('Not Found', 404);
        }

        return Http::response(json_encode($remotes[basename($request->url(), '.json')] ?? $remote));
    },
    'wings.smoke.test*' => function ($request) use (&$wings) {
        if (str_ends_with($request->url(), '/install-logs')) {
            return Http::response(['data' => "Pulling image from 10.20.30.40 for smoke@example.com token=s3cr3t-smoke\nInstall failed"]);
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
step('Move servers asks for a field the update made required and fills it on servers without a value', function () use ($admin, &$remotes) {
    $egg = remoteEgg('Smoke Fields', $remotes, []);
    $export = json_decode(app(EggExporterService::class)->handle($egg->id, EggFormat::JSON), true);
    $field = fn (string $env, string $default) => ['name' => Str::headline(strtolower($env)), 'description' => 'Smoke', 'env_variable' => $env, 'default_value' => $default, 'user_viewable' => true, 'user_editable' => true, 'rules' => ['required', 'string', 'max:40'], 'sort' => 1];
    // Like Paper's User Agent: required, without a default. And one with a good default, which needs nothing.
    $remotes['smoke-fields'] = array_replace($export, ['variables' => [...$export['variables'], $field('USER_AGENT', ''), $field('WORLD_TYPE', 'normal')]]);
    $empty = Server::factory()->create(['egg_id' => $egg->id, 'startup' => 'java -jar old.jar', 'image' => 'ghcr.io/smoke/java:17']);
    $own = Server::factory()->create(['egg_id' => $egg->id, 'startup' => 'java -jar old.jar', 'image' => 'ghcr.io/smoke/java:17']);
    Settings::saveForm(['mode' => 'ask']);
    Runner::run(true);
    $result = EggUpdater::update($egg->fresh());
    must($result->ok, $result->message);
    $agent = $egg->fresh()->variables()->where('env_variable', 'USER_AGENT')->firstOrFail();
    ServerVariable::query()->create(['server_id' => $own->id, 'variable_id' => $agent->id, 'variable_value' => 'mine']);
    must(array_keys(ServerMover::fields($egg->fresh())) === ['USER_AGENT'], 'asked for: '.implode(', ', array_keys(ServerMover::fields($egg->fresh()))));
    must(ServerMover::count($egg->fresh()) === 1, 'expected one server without a value, got '.ServerMover::count($egg->fresh()));
    Livewire::actingAs($admin)->test(EggUpdates::class)
        ->assertActionVisible(TestAction::make('move')->table($egg->fresh()))
        ->mountAction(TestAction::make('move')->table($egg->fresh()))
        ->set('mountedActions.0.data.USER_AGENT', 'smoke agent')
        ->callMountedAction()
        ->assertHasNoFormErrors();
    $value = fn (Server $server) => ServerVariable::query()->where('server_id', $server->id)->where('variable_id', $agent->id)->value('variable_value');
    must($value($empty) === 'smoke agent', 'not filled: '.var_export($value($empty), true));
    must($value($own) === 'mine', 'a server\'s own value changed: '.var_export($value($own), true));
    must(ServerMover::count($egg->fresh()) === 0, 'still servers to move');
});
step('an egg whose update link stopped working shows as Can\'t check, after two checks, and goes away when it works', function () use ($admin, &$remotes, &$broken) {
    $egg = remoteEgg('Smoke Broken', $remotes, []);
    remoteEgg('Smoke Broken Unused', $remotes, []);
    Server::factory()->create(['egg_id' => $egg->id]);
    $broken = ['smoke-broken', 'smoke-broken-unused'];
    $badge = EggUpdates::getNavigationBadge();
    $bell = count(bell($admin));
    $page = fn () => http($admin, EggUpdates::getUrl(panel: 'admin'))->getContent();
    UpdateChecker::check();
    must(! str_contains($page(), 'Smoke Broken'), 'listed after one failed check');
    UpdateChecker::check();
    $html = $page();
    must(str_contains($html, 'Smoke Broken') && str_contains($html, '404'), 'not listed with the reason after two failed checks');
    must(! str_contains($html, 'Smoke Broken Unused'), 'an egg without servers is listed');
    must(EggUpdates::getNavigationBadge() === $badge, 'counted in the menu badge');
    must(count(bell($admin)) === $bell, 'a notification was sent');
    $broken = [];
    UpdateChecker::check();
    must(! str_contains($page(), 'Smoke Broken'), 'still listed after the link works again');
});
// Test first on a node with a fake Wings: one test that passes and one whose install fails. Both must leave no test
// server, no test port and no imported copy behind.
$node = Node::factory()->create(['fqdn' => 'wings.smoke.test']);
$tested = remoteEgg('Smoke Tested', $remotes, ['name' => 'Smoke Tested 2']);
// Required variables without a default, like the real Paper egg: one the servers already fill in, and one the new
// version adds (Paper's User Agent). The test server must still be made.
$level = $tested->variables()->create(['name' => 'Level Name', 'description' => 'Smoke', 'env_variable' => 'LEVEL_NAME', 'default_value' => '', 'user_viewable' => true, 'user_editable' => true, 'rules' => ['required', 'string', 'max:64'], 'sort' => 1]);
// Like a Steam game: the server needs more than the default test size, and unlimited CPU.
$owned = Server::factory()->create(['egg_id' => $tested->id, 'node_id' => $node->id, 'memory' => 6144, 'disk' => 30720, 'cpu' => 0]);
ServerVariable::query()->create(['server_id' => $owned->id, 'variable_id' => $level->id, 'variable_value' => 'smoke world']);
$export = json_decode(app(EggExporterService::class)->handle($tested->id, EggFormat::JSON), true);
$agent = array_replace(collect($export['variables'])->firstWhere('env_variable', 'LEVEL_NAME'), ['name' => 'User Agent', 'env_variable' => 'USER_AGENT', 'rules' => ['required'], 'user_viewable' => false, 'user_editable' => false, 'sort' => 2]);
$remotes['smoke-tested'] = array_replace($export, ['name' => 'Smoke Tested 2', 'features' => ['eula'], 'variables' => [...$export['variables'], $agent]]);
// Like the real panel: every node is fully allocated on paper, without overallocation. A test server only runs a few
// minutes, so Test first must still find a node instead of saying that no node has room.
Node::query()->update(['memory' => 1024, 'disk' => 1024, 'cpu' => 100, 'memory_overallocate' => 0, 'disk_overallocate' => 0, 'cpu_overallocate' => 0]);
// Factory servers get random ports, some in the test range: only ports made after this point count.
$portsBefore = Allocation::query()->whereBetween('port', EggTester::PORTS)->pluck('id')->all();
$leftovers = function () use ($portsBefore) {
    $servers = Server::query()->where('external_id', 'like', EggTester::MARK.'%')->count();
    $ports = Allocation::query()->whereBetween('port', EggTester::PORTS)->whereNotIn('id', $portsBefore)->count();
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
    $values = ServerVariable::query()->where('server_id', $server->id)->with('variable')->get()->mapWithKeys(fn (ServerVariable $value) => [$value->variable->env_variable => $value->variable_value]);
    must((int) $server->memory >= 6144 && (int) $server->disk >= 30720 && (int) $server->cpu === 0, 'the test server is smaller than the egg\'s server: '.$server->memory.' MB, '.$server->disk.' MB disk, '.$server->cpu.'% CPU');
    must($values['LEVEL_NAME'] === 'smoke world', 'the test server does not use the value the servers use: '.$values['LEVEL_NAME']);
    must($values['USER_AGENT'] === TestEnvironment::FILLER, 'the new User Agent has no value: '.$values['USER_AGENT']);
});
step('a passing test updates the egg, cleans up and tells the root admin', function () use ($admin, $tested, $leftovers, &$wings) {
    $test = EggTest::query()->where('egg_id', $tested->id)->firstOrFail();
    Server::query()->whereKey($test->server_id)->update(['status' => null]);
    EggTester::tick();
    must(EggTester::stage($tested) === 'starting', 'not starting after the install: '.EggTester::stage($tested));
    // Like Paper: the egg asks for the EULA. Only the temporary test server accepts it, before its first start.
    $eula = Http::recorded(fn ($request) => str_contains($request->url(), '/files/write') && str_contains(urldecode($request->url()), 'eula.txt') && trim($request->body()) === 'eula=true');
    must($eula->isNotEmpty(), 'the test server did not accept the EULA before it started');
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
step('No settings gear on Pelican\'s Plugins page: settings live on the Egg updates page (EU-B1)', function () {
    must(! is_subclass_of(SimplifyEggUpdatesPlugin::class, HasPluginSettings::class), 'the plugin still implements HasPluginSettings');
});
step('Copy log is gone again after 1.1.5 (B7)', function () use ($admin) {
    $html = Livewire::actingAs($admin)->test(EggUpdates::class)->assertHasNoErrors()->html();
    must(str_contains($html, 'Check now') && ! str_contains($html, 'Copy log'), 'the Copy log button is still in the title bar');
    must(! view()->exists('simplify-eggupdates::debug-log'), 'the Copy log view is still there');
    must(! class_exists('Simplify\EggUpdates\Services\DebugLog'), 'the DebugLog class is still there');
});
// Test first on every public egg (pelican-eggs, cloned by CI into PUBLIC_EGGS): each one as the installed egg, used
// by a server without any own values, and a new version at its update URL. Test first must make the test server,
// start it and update the egg, and leave nothing behind. Eggs Pelican itself cannot import are listed, not failed.
$public = getenv('PUBLIC_EGGS') ?: '';
if ($public !== '' && is_dir($public)) {
    $published = [];
    Http::fake(['public-eggs.test/*' => function ($request) use (&$published) {
        return Http::response($published[basename((string) parse_url($request->url(), PHP_URL_PATH))] ?? '');
    }]);
    step('Test first works for every public egg', function () use ($public, &$published, &$wings, $node, $leftovers) {
        $files = collect(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public, FilesystemIterator::SKIP_DOTS))))
            ->keys()->filter(fn ($path) => ! preg_match('#/\.git(hub)?/#', $path) && preg_match('#/egg-[^/]+\.(json|ya?ml)$#', $path))->sort()->values();
        $failures = $unimportable = [];
        foreach ($files as $i => $path) {
            $name = substr($path, strlen($public) + 1);
            $format = Definition::format($path);
            try {
                $real = app(EggImporterService::class)->fromContent((string) file_get_contents($path), $format, new Egg);
            } catch (Throwable $e) {
                $unimportable[] = $name.': '.Definition::reason($e);

                continue;
            }
            $file = $i.'.'.($format === EggFormat::YAML ? 'yaml' : 'json');
            $real->forceFill(['update_url' => 'https://public-eggs.test/'.$file])->save();
            $definition = RemoteEgg::parse((string) file_get_contents($path), $format);
            $definition['name'] = $real->name.' 2';
            $published[$file] = $format === EggFormat::YAML ? Yaml::dump($definition, 8, 2) : json_encode($definition);
            $used = Server::factory()->create(['egg_id' => $real->id, 'node_id' => $node->id]);
            $before = $leftovers();
            $wings = 'starting';
            try {
                EggTester::queue($real->fresh());
                $test = EggTest::query()->where('egg_id', $real->id)->first();
                if ($test === null) {
                    throw new RuntimeException('not made: '.EggUpdate::query()->find($real->id)?->test_reason);
                }
                Server::query()->whereKey($test->server_id)->update(['status' => null]);
                EggTester::tick();
                $wings = 'running';
                EggTester::tick();
                $state = EggUpdate::query()->find($real->id);
                if ($state?->test_ok !== true || $real->fresh()->name !== $definition['name']) {
                    throw new RuntimeException('not passed: '.$state?->test_reason);
                }
            } catch (Throwable $e) {
                $failures[] = $name.': '.Definition::reason($e);
                EggTest::query()->where('egg_id', $real->id)->get()->each(function (EggTest $test) {
                    (new ReflectionMethod(EggTester::class, 'cleanUp'))->invoke(null, $test);
                    $test->delete();
                });
            }
            // Passed or failed, Test first itself must leave nothing behind.
            if ($leftovers() !== $before) {
                $failures[] = $name.': left behind: '.($leftovers() - $before);
            }
            app(ServerDeletionService::class)->withForce()->handle($used);
            // The factory gives the server a random port, sometimes one in the test range.
            Allocation::query()->whereKey($used->allocation_id)->delete();
            EggBackup::query()->where('egg_id', $real->id)->delete();
            EggUpdate::query()->whereKey($real->id)->delete();
            $real->variables()->delete();
            $real->delete();
        }
        echo '     '.$files->count().' public eggs, '.count($unimportable)." that Pelican cannot import:\n";
        foreach ($unimportable as $line) {
            echo "       $line\n";
        }
        foreach ($failures as $line) {
            echo "     FAILED $line\n";
        }
        must($files->count() > 500, 'too few public eggs: '.$files->count());
        must($failures === [], count($failures).' of '.$files->count().' public eggs fail Test first');
    });
}
echo $failed ? "Some steps failed\n" : "All steps passed\n";
exit($failed ? 1 : 0);
