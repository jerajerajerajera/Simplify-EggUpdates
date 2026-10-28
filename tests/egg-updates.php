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

    use App\Enums\EggFormat;
    use Simplify\EggUpdates\Enums\Mode;
    use Simplify\EggUpdates\Services\Definition;

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

    $check(array_map(fn (Mode $mode) => $mode->value, Mode::cases()) === ['ask', 'auto', 'off'], 'modes');
    $check(Mode::tryFrom('nonsense') === null && ! Mode::Ask->updatesUnusedEggs() && Mode::Auto->updatesUnusedEggs(), 'only auto updates unused eggs');

    $check(Definition::reason(new RuntimeException("  <b>Not found</b>\n  at the URL.  ")) === 'Not found at the URL', 'a reason is one plain line');

    if ($failures !== []) {
        echo "FAIL egg updates:\n  ".implode("\n  ", $failures)."\n";
        exit(1);
    }
    echo "PASS egg updates: hash, format, names, modes and reasons\n";
}
