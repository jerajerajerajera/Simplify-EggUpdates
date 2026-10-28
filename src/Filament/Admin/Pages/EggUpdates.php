<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin\Pages;

use App\Models\Egg;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Simplify\EggUpdates\Models\EggBackup;
use Simplify\EggUpdates\Models\EggUpdate;
use Simplify\EggUpdates\Services\EggTester;
use Simplify\EggUpdates\Services\EggUpdater;
use Simplify\EggUpdates\Services\Result;
use Simplify\EggUpdates\Services\ServerMover;
use Simplify\EggUpdates\Services\Settings;
use Simplify\EggUpdates\Services\UpdateChecker;

/**
 * Eggs with a new version, and eggs that were updated here and can be put back.
 */
final class EggUpdates extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-egg';

    protected static ?string $slug = 'egg-updates';

    protected static ?int $navigationSort = 20;

    protected string $view = 'simplify-egg-updates::egg-updates';

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-egg-updates::egg-updates.title');
    }

    /** In the Simplify group of Pelican's menu, with the other Simplify add-ons (Simplify's ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::available()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function getTitle(): string
    {
        return __('simplify-egg-updates::egg-updates.title');
    }

    public function getSubheading(): ?string
    {
        $checked = UpdateChecker::checkedAt();

        return $checked === null
            ? __('simplify-egg-updates::egg-updates.page.not_checked')
            : __('simplify-egg-updates::egg-updates.page.checked', ['time' => $checked->diffForHumans()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Egg::query()
                ->where(fn (Builder $query) => $query
                    ->whereIn('id', self::available()->select('egg_id'))
                    ->orWhereIn('id', EggBackup::query()->select('egg_id')))
                ->withCount('servers'))
            ->defaultSort('name')
            ->paginated(false)
            ->poll(fn () => EggTester::busy() ? '10s' : null)
            ->columns([
                TextColumn::make('name')
                    ->label(__('simplify-egg-updates::egg-updates.page.egg'))
                    ->description(fn (Egg $egg) => $egg->author)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('servers_count')
                    ->label(__('simplify-egg-updates::egg-updates.page.servers'))
                    ->formatStateUsing(fn (int $state) => trans_choice('simplify-egg-updates::egg-updates.page.server_count', $state, ['count' => $state])),
                TextColumn::make('status')
                    ->label(__('simplify-egg-updates::egg-updates.page.status'))
                    ->badge()
                    ->state(fn (Egg $egg) => self::status($egg))
                    ->formatStateUsing(fn (string $state) => __('simplify-egg-updates::egg-updates.status.'.$state))
                    ->description(fn (Egg $egg) => self::testFailure($egg))
                    ->color(fn (string $state) => match ($state) {
                        'updated' => 'success',
                        'held' => 'gray',
                        'available' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('since')
                    ->label(__('simplify-egg-updates::egg-updates.page.since'))
                    ->state(fn (Egg $egg) => self::since($egg))
                    ->since(),
            ])
            ->recordActions([
                Action::make('test')
                    ->label(__('simplify-egg-updates::egg-updates.actions.test'))
                    ->icon('tabler-flask')
                    ->color('gray')
                    ->button()
                    ->visible(fn (Egg $egg) => in_array(self::status($egg), ['available', 'held'], true))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Egg $egg) => __('simplify-egg-updates::egg-updates.actions.test_heading', ['egg' => $egg->name]))
                    ->modalDescription(__('simplify-egg-updates::egg-updates.actions.test_description'))
                    ->action(fn (Egg $egg) => self::send(EggTester::queue($egg))),
                Action::make('update')
                    ->label(__('simplify-egg-updates::egg-updates.actions.update'))
                    ->icon('tabler-download')
                    ->button()
                    ->visible(fn (Egg $egg) => in_array(self::status($egg), ['available', 'held'], true))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Egg $egg) => __('simplify-egg-updates::egg-updates.actions.update_heading', ['egg' => $egg->name]))
                    ->modalDescription(fn (Egg $egg) => __($egg->servers_count > 0
                        ? 'simplify-egg-updates::egg-updates.actions.update_servers'
                        : 'simplify-egg-updates::egg-updates.actions.update_kept'))
                    ->action(fn (Egg $egg) => self::send(EggUpdater::update($egg))),
                Action::make('move')
                    ->label(fn (Egg $egg) => __('simplify-egg-updates::egg-updates.actions.move', ['count' => ServerMover::count($egg)]))
                    ->icon('tabler-arrows-right')
                    ->color('gray')
                    ->button()
                    ->visible(fn (Egg $egg) => $egg->servers_count > 0 && ServerMover::count($egg) > 0)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Egg $egg) => __('simplify-egg-updates::egg-updates.actions.move_heading', ['egg' => $egg->name]))
                    ->modalDescription(__('simplify-egg-updates::egg-updates.actions.move_description'))
                    ->action(fn (Egg $egg) => self::send(ServerMover::move($egg))),
                Action::make('put_back')
                    ->label(__('simplify-egg-updates::egg-updates.actions.put_back'))
                    ->icon('tabler-arrow-back-up')
                    ->color('gray')
                    ->button()
                    ->visible(fn (Egg $egg) => EggBackup::query()->where('egg_id', $egg->id)->exists())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Egg $egg) => __('simplify-egg-updates::egg-updates.actions.put_back_heading', ['egg' => $egg->name]))
                    ->modalDescription(__('simplify-egg-updates::egg-updates.actions.put_back_description'))
                    ->action(fn (Egg $egg) => self::send(EggUpdater::putBack($egg))),
            ])
            ->emptyStateIcon('tabler-egg')
            ->emptyStateHeading(__('simplify-egg-updates::egg-updates.page.empty'))
            ->emptyStateDescription(__('simplify-egg-updates::egg-updates.page.empty_help'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check')
                ->label(__('simplify-egg-updates::egg-updates.actions.check'))
                ->icon('tabler-refresh')
                ->color('gray')
                ->action(function () {
                    UpdateChecker::check();
                    $count = self::available()->count();
                    Notification::make()
                        ->title($count === 0
                            ? __('simplify-egg-updates::egg-updates.messages.up_to_date')
                            : trans_choice('simplify-egg-updates::egg-updates.messages.available', $count, ['count' => $count]))
                        ->success()
                        ->send();
                }),
            Action::make('update_all')
                ->label(__('simplify-egg-updates::egg-updates.actions.update_all'))
                ->icon('tabler-download')
                ->visible(fn () => self::available()->count() > 1)
                ->requiresConfirmation()
                ->modalHeading(fn () => __('simplify-egg-updates::egg-updates.actions.update_all_heading', ['count' => self::available()->count()]))
                ->modalDescription(__('simplify-egg-updates::egg-updates.actions.update_all_description'))
                ->action(fn () => $this->updateAll()),
            Action::make('settings')
                ->label(__('simplify-egg-updates::egg-updates.settings.title'))
                ->icon('tabler-settings')
                ->color('gray')
                ->fillForm(fn () => Settings::formData())
                ->schema(Settings::form())
                ->action(function (array $data) {
                    Settings::saveForm($data);
                    Notification::make()->title(__('simplify-egg-updates::egg-updates.settings.saved'))->success()->send();
                }),
        ];
    }

    public function updateAll(): void
    {
        abort_unless(self::canAccess(), 403);
        $done = 0;
        $failed = [];
        foreach (Egg::query()->whereIn('id', self::available()->select('egg_id'))->get() as $egg) {
            $result = EggUpdater::update($egg);
            $result->ok ? $done++ : $failed[] = $result->message;
        }
        if ($failed === []) {
            Notification::make()
                ->title(trans_choice('simplify-egg-updates::egg-updates.messages.updated_count', $done, ['count' => $done]))
                ->body(__('simplify-egg-updates::egg-updates.actions.update_kept'))
                ->success()
                ->send();

            return;
        }
        Notification::make()
            ->title(__('simplify-egg-updates::egg-updates.messages.some_failed', ['done' => $done, 'failed' => count($failed)]))
            ->body(implode(' ', $failed))
            ->danger()
            ->send();
    }

    /** @return Builder<EggUpdate> */
    private static function available(): Builder
    {
        return EggUpdate::query()->whereNotNull('found_hash');
    }

    /** available, held (put back by hand), updated, or a test stage: queued, installing, starting. */
    private static function status(Egg $egg): string
    {
        $stage = EggTester::stage($egg);
        if ($stage !== null) {
            return $stage;
        }
        $state = EggUpdate::query()->find($egg->id);
        if ($state?->found_hash === null) {
            return 'updated';
        }

        return $state->held ? 'held' : 'available';
    }

    /** Why the last test of the listed update failed, while the update is still listed. */
    private static function testFailure(Egg $egg): ?string
    {
        $state = EggUpdate::query()->find($egg->id);
        if ($state?->found_hash === null || $state->test_ok !== false || EggTester::stage($egg) !== null) {
            return null;
        }

        return __('simplify-egg-updates::egg-updates.tests.failed_short', ['reason' => $state->test_reason]);
    }

    private static function since(Egg $egg): mixed
    {
        $state = EggUpdate::query()->find($egg->id);
        if ($state?->found_at !== null) {
            return $state->found_at;
        }

        return EggUpdater::latest($egg)?->created_at;
    }

    private static function send(Result $result): void
    {
        Notification::make()->title($result->message)->status($result->ok ? 'success' : 'danger')->send();
    }
}
