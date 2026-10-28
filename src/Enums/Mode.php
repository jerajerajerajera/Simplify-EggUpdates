<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Enums;

/** What happens when an egg has a new version. */
enum Mode: string
{
    case Ask = 'ask';
    case Auto = 'auto';
    case Off = 'off';

    public function label(): string
    {
        return __('simplify-egg-updates::egg-updates.modes.'.$this->value);
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
        return $this === self::Auto;
    }
}
