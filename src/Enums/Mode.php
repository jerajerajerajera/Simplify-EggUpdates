<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Enums;

/** What happens when an egg has a new version. */
enum Mode: string
{
    case Ask = 'ask';
    case Auto = 'auto';
    case Test = 'test';
    case Off = 'off';

    public function label(): string
    {
        return __('simplify-eggupdates::egg-updates.modes.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $mode) {
            $options[$mode->value] = $mode->label();
        }

        return $options;
    }

    /** Eggs that no server uses are updated without asking. */
    public function updatesUnusedEggs(): bool
    {
        return $this === self::Auto || $this === self::Test;
    }

    /** Eggs that servers use are updated once a test server installed and started with the new version. */
    public function testsUsedEggs(): bool
    {
        return $this === self::Test;
    }
}
