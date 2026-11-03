<?php

declare(strict_types=1);

namespace Simplify\EggUpdates;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;

final class SimplifyEggUpdatesPlugin implements Plugin
{
    public const ID = 'simplify-eggupdates';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->pages([EggUpdates::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
