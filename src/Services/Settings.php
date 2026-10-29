<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use Filament\Forms\Components\Select;
use Simplify\EggUpdates\Enums\Mode;
use Simplify\EggUpdates\Models\Setting;

/** The add-on's settings, in its own table so they survive a cached config. */
final class Settings
{
    public static function mode(): Mode
    {
        $value = Setting::query()->whereKey('mode')->value('value');

        return Mode::tryFrom((string) $value) ?? Mode::Ask;
    }

    public static function saveMode(Mode $mode): void
    {
        abort_unless(user() === null || user()->isRootAdmin(), 403);

        Setting::query()->updateOrCreate(['key' => 'mode'], ['value' => $mode->value]);
    }

    /** @return array<string, mixed> */
    public static function formData(): array
    {
        return ['mode' => self::mode()->value];
    }

    /** @return list<Select> */
    public static function form(): array
    {
        return [
            Select::make('mode')
                ->label(__('simplify-eggupdates::egg-updates.settings.mode'))
                ->options(Mode::options())
                ->selectablePlaceholder(false)
                ->required()
                ->helperText(__('simplify-eggupdates::egg-updates.settings.mode_help')),
        ];
    }

    /** @param array<mixed> $data */
    public static function saveForm(array $data): void
    {
        self::saveMode(Mode::from((string) ($data['mode'] ?? Mode::Ask->value)));
    }
}
