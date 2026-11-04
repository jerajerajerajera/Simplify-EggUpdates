<?php

// Pure parts of Egg Updates, without a panel: Pelican's hash, the update URL format, names in a sentence, the modes.
declare(strict_types=1);

namespace App\Enums {
    enum EggFormat
    {
        case JSON;
        case YAML;
    }
}

namespace {
    function __(string $key): string
    {
        return $key;
    }

    require __DIR__.'/../src/Services/Definition.php';
    require __DIR__.'/../src/Enums/Mode.php';
    require __DIR__.'/../src/Services/EggTester.php';
    require __DIR__.'/../src/Services/ServerMover.php';
    require __DIR__.'/../src/Services/TestEnvironment.php';

    use App\Enums\EggFormat;
    use Simplify\EggUpdates\Enums\Mode;
    use Simplify\EggUpdates\Services\Definition;
    use Simplify\EggUpdates\Services\EggTester;
    use Simplify\EggUpdates\Services\ServerMover;
    use Simplify\EggUpdates\Services\TestEnvironment;

    $failures = [];
    $check = function (bool $ok, string $what) use (&$failures) {
        if (! $ok) {
            $failures[] = $what;
        }
    };

    $egg = ['meta' => ['version' => 'PLCN_v3'], 'exported_at' => '2026-10-01T10:00:00+00:00', 'name' => 'Paper'];
    $check(Definition::hash($egg) === md5(json_encode(['meta' => ['version' => 'PLCN_v3'], 'name' => 'Paper'])), 'the hash leaves out exported_at, like Pelican');
    $check(Definition::hash($egg) === Definition::hash(['exported_at' => 'another day'] + $egg), 'a new export date is not an update');
    $check(Definition::hash($egg) !== Definition::hash(['name' => 'Paper 2'] + $egg), 'a changed definition is an update');

    $check(Definition::format('https://example.com/eggs/paper/egg-paper.yaml') === EggFormat::YAML, 'yaml');
    $check(Definition::format('https://example.com/egg.YML?raw=1') === EggFormat::YAML, 'yml with a query');
    $check(Definition::format('https://example.com/egg-paper.json') === EggFormat::JSON, 'json');

    $check(Definition::names(['Paper'], 'and') === 'Paper', 'one name');
    $check(Definition::names(['Paper', 'Forge'], 'en') === 'Paper en Forge', 'two names');
    $check(Definition::names(['Paper', 'Fabric', 'Forge'], 'and') === 'Paper, Fabric and Forge', 'three names');

    $check(array_map(fn (Mode $mode) => $mode->value, Mode::cases()) === ['ask', 'auto', 'test', 'off'], 'modes');
    $check(Mode::tryFrom('nonsense') === null, 'unknown mode');
    $check(! Mode::Ask->updatesUnusedEggs() && Mode::Auto->updatesUnusedEggs() && Mode::Test->updatesUnusedEggs() && ! Mode::Off->updatesUnusedEggs(), 'auto and test update unused eggs');
    $check(Mode::Test->testsUsedEggs() && ! Mode::Auto->testsUsedEggs() && ! Mode::Ask->testsUsedEggs(), 'only test tests used eggs');

    // Test servers take the highest free port of the test range.
    $check(EggTester::freePort([]) === 45999, 'first test port');
    $check(EggTester::freePort([45999, '45998']) === 45997, 'taken test ports are skipped');
    $check(EggTester::freePort(range(45000, 45999)) === null, 'no free test port');
    $check(str_starts_with(EggTester::MARK, 'simplify-egg-check-'), 'test servers are marked for Simplify');

    // Move servers: a command or image from the previous version that the update changed follows it; own ones stay.
    $previous = ['startup_commands' => ['Default' => 'java -jar old.jar', 'Aikar' => 'java -XX:+Aikar -jar old.jar'], 'docker_images' => ['Java 17' => 'yolks:java_17', 'Java 21' => 'yolks:java_21']];
    $servers = [
        ['id' => 1, 'startup' => 'java -jar old.jar', 'image' => 'yolks:java_17'],
        ['id' => 2, 'startup' => 'java -XX:+Aikar -jar old.jar', 'image' => 'yolks:java_21'],
        ['id' => 3, 'startup' => 'my own command', 'image' => 'my/image'],
        ['id' => 4, 'startup' => 'java -jar new.jar', 'image' => 'yolks:java_21'],
    ];
    $plan = ServerMover::moves($servers, $previous, ['Default' => 'java -jar new.jar', 'Aikar' => 'java -XX:+Aikar -jar new.jar'], ['Java 21' => 'yolks:java_21', 'Java 25' => 'yolks:java_25']);
    $check($plan === [1 => ['startup' => 'java -jar new.jar', 'image' => 'yolks:java_21'], 2 => ['startup' => 'java -XX:+Aikar -jar new.jar']], 'the right servers move: '.json_encode($plan));
    $plan = ServerMover::moves([['id' => 5, 'startup' => 'old', 'image' => 'x']], ['startup' => 'old'], ['Default' => 'new'], ['Java' => 'x']);
    $check($plan === [5 => ['startup' => 'new']], 'the single startup of an older export moves to the new default');
    $check(ServerMover::moves($servers, [], ['Default' => 'x'], ['Java' => 'y']) === [], 'nothing moves without an earlier version');
    $check(ServerMover::moves([['id' => 6, 'startup' => null, 'image' => null]], $previous, [], []) === [], 'a server without command or image stays');

    $check(Definition::reason(new RuntimeException("  <b>Not found</b>\n  at the URL.  ")) === 'Not found at the URL', 'a reason is one plain line');

    // EU-B2: an empty default (FTB's modpack ID) takes the value the egg's own servers use; a filled default stays first.
    $check(TestEnvironment::order('', ['123', '45']) === ['123', '45', ''], 'an empty default takes the value the servers use: '.json_encode(TestEnvironment::order('', ['123', '45'])));
    $check(TestEnvironment::order('latest', ['1.20']) === ['latest', '1.20'], 'a filled default stays first');
    $check(TestEnvironment::order('', []) === [''], 'without servers the empty default stays');

    // EU-B3: the test server runs on the image the egg's servers use (CurseForge lists Java 8 first), if the new version still has it.
    $images = ['Java 8' => 'yolks:java_8', 'Java 21' => 'yolks:java_21'];
    $check(TestEnvironment::image($images, ['yolks:java_21', 'yolks:java_8']) === 'yolks:java_21', 'the test server does not use the image the servers use');
    $check(TestEnvironment::image($images, ['yolks:java_16']) === 'yolks:java_8', 'an image the new version dropped is not replaced by its default');
    $check(TestEnvironment::image($images, []) === 'yolks:java_8', 'without servers the default image is not used');

    // Settings live on the Egg updates page in Simplify, never behind a gear on Pelican's Plugins page.
    $plugin = file_get_contents(__DIR__.'/../src/SimplifyEggUpdatesPlugin.php');
    $check(! str_contains($plugin, 'HasPluginSettings'), "the plugin class has Pelican's own settings gear (HasPluginSettings)");

    if ($failures !== []) {
        echo "FAIL egg updates:\n  ".implode("\n  ", $failures)."\n";
        exit(1);
    }
    echo "PASS egg updates: hash, format, names, modes, reasons, test ports, server moves, test values and no Pelican settings gear\n";
}
