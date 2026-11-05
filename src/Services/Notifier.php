<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

use App\Filament\Admin\Resources\Eggs\EggResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Notifications in Pelican's bell for every root admin, with a button to Pelican's Eggs page, where the updates are. */
final class Notifier
{
    public static function rootAdmins(string $title, string $body, string $status = 'info'): void
    {
        try {
            $notification = Notification::make()
                ->status($status)
                ->title($title)
                ->body($body)
                ->actions([
                    Action::make('review')
                        ->label(__('simplify-eggupdates::egg-updates.title'))
                        ->url(EggResource::getUrl('index', panel: 'admin')),
                ]);
            foreach (User::query()->get()->filter(fn (User $user) => $user->isRootAdmin()) as $user) {
                $user->notifyNow($notification->toDatabase());
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
