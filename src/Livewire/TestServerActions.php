<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Livewire;

use App\Filament\Admin\Resources\Eggs\EggResource;
use App\Models\Server;
use Filament\Notifications\Notification;
use Livewire\Component;
use Simplify\EggUpdates\Filament\Admin\UpdatesTable;
use Simplify\EggUpdates\Models\EggTest;
use Simplify\EggUpdates\Services\EggTester;
use Simplify\EggUpdates\Services\Result;

/**
 * Force kill and Retry on the locked page of a Test first server (Addon::lockedServer, Simplify's API 10). Simplify
 * greys out everything else on the page and mounts this component in the button row with the server's uuid.
 */
final class TestServerActions extends Component
{
    public string $server = '';

    public function mount(string $server): void
    {
        abort_unless(UpdatesTable::canManage(), 403);
        $this->server = $server;
    }

    public function kill(): void
    {
        $result = $this->run(fn (Server $server) => EggTester::kill($server));
        $this->redirect(EggResource::getUrl('index', panel: 'admin'));
        if ($result !== null) {
            Notification::make()->title($result)->success()->send();
        }
    }

    public function retry(): void
    {
        $result = $this->run(fn (Server $server) => EggTester::retry($server));
        if ($result !== null) {
            Notification::make()->title($result)->success()->send();
        }
        $this->redirect(self::newServerUrl() ?? EggResource::getUrl('index', panel: 'admin'));
    }

    /** @param  callable(Server): (Result|null)  $action */
    private function run(callable $action): ?string
    {
        abort_unless(UpdatesTable::canManage(), 403);
        $server = Server::query()->where('uuid', $this->server)->first();
        // Only ever a test server: any other server gives null and nothing happens.
        $result = EggTester::isTestServer($server) ? $action($server) : null;

        return $result?->message;
    }

    /** The page of the new test server after Retry, when it was made right away. */
    private static function newServerUrl(): ?string
    {
        $server = Server::query()->find(EggTest::query()->whereNotNull('server_id')->value('server_id'));
        if (! EggTester::isTestServer($server)) {
            return null;
        }
        try {
            return route('filament.server.pages.console', ['tenant' => $server]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function render(): mixed
    {
        $server = Server::query()->where('uuid', $this->server)->first();

        return view('simplify-eggupdates::livewire.test-server-actions', [
            'egg' => EggTester::testedEgg($server)?->name ?? '',
        ]);
    }
}
