<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin;

use App\Filament\Admin\Resources\Eggs\EggResource;
use App\Models\Egg;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Simplify\Core\Addons\Addon;
use Simplify\EggUpdates\Addon\EggUpdatesProblems;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
use Simplify\EggUpdates\Models\EggBackup;
use Simplify\EggUpdates\Models\EggUpdate;
use Simplify\EggUpdates\Services\EggTester;
use Simplify\EggUpdates\Services\EggUpdater;
use Simplify\EggUpdates\Services\Result;
use Simplify\EggUpdates\Services\ServerMover;
use Simplify\EggUpdates\Services\TestEnvironment;
use Simplify\EggUpdates\Services\UpdateChecker;

/**
 * The Updates block on Pelican's Eggs page: eggs with a new version, eggs whose update link fails, and eggs that were
 * updated here and can be put back. Its buttons Check now and Update all sit in the Eggs page's title bar.
 */
final class UpdatesTable
{
    /** Only root admins see and use Egg Updates. */
    public static function canManage(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    /** Whether the block has anything to show: a row, or a test that runs. */
    public static function hasRows(): bool
    {
        return self::query()->exists() || EggTester::busy();
    }

    /** @return Builder<Egg> */
    public static function query(): Builder
    {
        return Egg::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', self::available()->select('egg_id'))
                ->orWhereIn('id', EggBackup::query()->select('egg_id'))
                // An update URL that keeps failing, for eggs that servers use.
                ->orWhere(fn (Builder $query) => $query->has('servers')->whereIn('id', self::unreachable()->select('egg_id'))));
    }

    public static function checked(): string
    {
        $checked = UpdateChecker::checkedAt();

        return $checked === null
            ? __('simplify-eggupdates::egg-updates.page.not_checked')
            : __('simplify-eggupdates::egg-updates.page.checked', ['time' => $checked->diffForHumans()]);
    }

    /** The table of the Updates block, without heading. */
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn () => self::query()->withCount('servers'))
            ->defaultSort('name')
            ->paginated(false)
            ->poll(fn () => EggTester::busy() ? '10s' : null)
            ->columns([
                // On a phone one cell holds name, status and the rest, so the buttons stay in view.
                ViewColumn::make('summary')
                    ->label(__('simplify-eggupdates::egg-updates.page.egg'))
                    ->view('simplify-eggupdates::summary')
                    ->state(fn (Egg $egg) => [
                        'name' => $egg->name,
                        'status' => self::status($egg),
                        'color' => self::color(self::status($egg)),
                        'meta' => implode(' · ', array_filter([
                            trans_choice('simplify-eggupdates::egg-updates.page.server_count', $egg->servers_count, ['count' => $egg->servers_count]),
                            self::since($egg)?->diffForHumans(),
                            self::note($egg),
                        ])),
                        'explain' => self::explain($egg),
                    ])
                    ->hiddenFrom('sm'),
                TextColumn::make('name')
                    ->label(__('simplify-eggupdates::egg-updates.page.egg'))
                    ->description(fn (Egg $egg) => $egg->author)
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('servers_count')
                    ->label(__('simplify-eggupdates::egg-updates.page.servers'))
                    ->formatStateUsing(fn (int $state) => trans_choice('simplify-eggupdates::egg-updates.page.server_count', $state, ['count' => $state]))
                    ->visibleFrom('sm'),
                self::statusColumn('status')
                    ->visibleFrom('sm'),
                TextColumn::make('since')
                    ->label(__('simplify-eggupdates::egg-updates.page.since'))
                    ->state(fn (Egg $egg) => self::since($egg))
                    ->since()
                    ->visibleFrom('sm'),
            ])
            ->recordActions(self::recordActions())
            ->emptyStateIcon('tabler-egg')
            ->emptyStateHeading(__('simplify-eggupdates::egg-updates.page.empty'))
            ->emptyStateDescription(__('simplify-eggupdates::egg-updates.page.empty_help'));
    }

    /**
     * The status of an egg as a badge, with why its last test failed or its update link cannot be checked. In Pelican's
     * Eggs table it stays empty for eggs Egg Updates has nothing to say about.
     */
    public static function statusColumn(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('simplify-eggupdates::egg-updates.page.status'))
            ->badge()
            ->state(fn (Egg $egg) => self::rowStatus($egg))
            ->formatStateUsing(fn (?string $state) => $state === null ? null : __('simplify-eggupdates::egg-updates.status.'.$state))
            ->description(fn (Egg $egg) => self::note($egg) === null ? null : new HtmlString(e(self::note($egg)).' '.self::explain($egg)?->toHtml()))
            ->color(fn (?string $state) => $state === null ? null : self::color($state));
    }

    /**
     * Test first, Update, Move servers and Put back for one egg. In Pelican's Eggs table they get a prefix, so they
     * never take the place of one of Pelican's own buttons, and they are icons with a tooltip, like Pelican's.
     *
     * @return list<Action>
     */
    public static function recordActions(bool $inPelicanTable = false): array
    {
        $prefix = $inPelicanTable ? 'egg_updates_' : '';
        $actions = [
            Action::make($prefix.'test')
                ->label(__('simplify-eggupdates::egg-updates.actions.test'))
                ->icon('tabler-flask')
                ->color('gray')
                ->button()
                ->labeledFrom('sm')
                ->visible(fn (Egg $egg) => in_array(self::status($egg), ['available', 'held'], true))
                ->requiresConfirmation()
                ->modalHeading(fn (Egg $egg) => __('simplify-eggupdates::egg-updates.actions.test_heading', ['egg' => $egg->name]))
                ->modalDescription(fn (Egg $egg) => __('simplify-eggupdates::egg-updates.actions.test_description')
                    // Like Paper: the test server accepts the EULA before its first start (EggTester::acceptEula).
                    .(in_array('eula', $egg->inherit_features ?? [], true) ? ' '.__('simplify-eggupdates::egg-updates.actions.test_eula') : ''))
                ->action(fn (Egg $egg) => self::send(EggTester::queue($egg))),
            Action::make($prefix.'update')
                ->label(__('simplify-eggupdates::egg-updates.actions.update'))
                ->icon('tabler-download')
                ->button()
                ->labeledFrom('sm')
                ->visible(fn (Egg $egg) => in_array(self::status($egg), ['available', 'held'], true))
                ->requiresConfirmation()
                ->modalHeading(fn (Egg $egg) => __('simplify-eggupdates::egg-updates.actions.update_heading', ['egg' => $egg->name]))
                ->modalDescription(fn (Egg $egg) => __(self::servers($egg) > 0
                    ? 'simplify-eggupdates::egg-updates.actions.update_servers'
                    : 'simplify-eggupdates::egg-updates.actions.update_kept'))
                ->action(fn (Egg $egg) => self::send(EggUpdater::update($egg))),
            Action::make($prefix.'move')
                ->label(fn (Egg $egg) => __('simplify-eggupdates::egg-updates.actions.move', ['count' => ServerMover::count($egg)]))
                ->icon('tabler-arrows-right')
                ->color('gray')
                ->button()
                ->labeledFrom('sm')
                ->visible(fn (Egg $egg) => self::servers($egg) > 0 && ServerMover::count($egg) > 0)
                ->requiresConfirmation()
                ->modalHeading(fn (Egg $egg) => __('simplify-eggupdates::egg-updates.actions.move_heading', ['egg' => $egg->name]))
                ->modalDescription(__('simplify-eggupdates::egg-updates.actions.move_description'))
                // A field the update made required, for the servers without a value.
                ->schema(fn (Egg $egg) => array_map(fn (array $field) => TextInput::make($field['variable']->env_variable)
                    ->label($field['variable']->name)
                    ->helperText(__('simplify-eggupdates::egg-updates.actions.move_field_help', ['count' => count($field['servers'])]))
                    ->rules($field['variable']->rules ?: [])
                    ->required(), array_values(ServerMover::fields($egg))))
                ->fillForm(fn (Egg $egg) => array_map(fn (array $field) => TestEnvironment::value($field['variable']), ServerMover::fields($egg)))
                ->action(fn (Egg $egg, array $data) => self::send(ServerMover::move($egg, $data))),
            Action::make($prefix.'put_back')
                ->label(__('simplify-eggupdates::egg-updates.actions.put_back'))
                ->icon('tabler-arrow-back-up')
                ->color('gray')
                ->button()
                ->labeledFrom('sm')
                ->visible(fn (Egg $egg) => EggBackup::query()->where('egg_id', $egg->id)->exists())
                ->requiresConfirmation()
                ->modalHeading(fn (Egg $egg) => __('simplify-eggupdates::egg-updates.actions.put_back_heading', ['egg' => $egg->name]))
                ->modalDescription(__('simplify-eggupdates::egg-updates.actions.put_back_description'))
                ->action(fn (Egg $egg) => self::send(EggUpdater::putBack($egg))),
        ];
        foreach ($actions as $action) {
            // Every click checks root admin again: Pelican's Eggs page is open to other admins too.
            $action->authorize(fn () => self::canManage());
            if ($inPelicanTable) {
                $action->iconButton()->tooltip(fn (Action $action) => $action->getLabel());
            }
        }

        return $actions;
    }

    /** The status of an egg that is listed, or null for an egg Egg Updates has nothing to say about. */
    public static function rowStatus(Egg $egg): ?string
    {
        if (EggTester::stage($egg) === null && ! self::query()->whereKey($egg->id)->exists()) {
            return null;
        }

        return self::status($egg);
    }

    /**
     * Check now and Update all, and without Simplify the gear to the settings. Registered on Pelican's Eggs page, so
     * they are made once and every text is a closure (in the viewer's language); each one checks root admin itself, as
     * the Eggs page is open to other admins too.
     *
     * @return list<Action>
     */
    public static function headerActions(): array
    {
        return [
            Action::make('egg_updates_check')
                ->label(fn () => __('simplify-eggupdates::egg-updates.actions.check'))
                ->icon('tabler-refresh')
                ->color('gray')
                ->visible(fn () => self::canManage())
                ->action(function (Component $livewire) {
                    abort_unless(self::canManage(), 403);
                    UpdateChecker::check();
                    $count = self::available()->count();
                    Notification::make()
                        ->title($count === 0
                            ? __('simplify-eggupdates::egg-updates.messages.up_to_date')
                            : trans_choice('simplify-eggupdates::egg-updates.messages.available', $count, ['count' => $count]))
                        ->success()
                        ->send();
                    self::reload($livewire);
                }),
            Action::make('egg_updates_update_all')
                ->label(fn () => __('simplify-eggupdates::egg-updates.actions.update_all'))
                ->icon('tabler-download')
                ->visible(fn () => self::canManage() && self::available()->count() > 1)
                ->requiresConfirmation()
                ->modalHeading(fn () => __('simplify-eggupdates::egg-updates.actions.update_all_heading', ['count' => self::available()->count()]))
                ->modalDescription(fn () => __('simplify-eggupdates::egg-updates.actions.update_all_description'))
                ->action(function (Component $livewire) {
                    self::updateAll();
                    self::reload($livewire);
                }),
            // With Simplify the settings are under Simplify settings › Add-ons: one place, no gear here.
            Action::make('egg_updates_settings')
                ->label(fn () => __('simplify-eggupdates::egg-updates.settings.title'))
                ->hiddenLabel()
                ->tooltip(fn () => __('simplify-eggupdates::egg-updates.settings.title'))
                ->icon('tabler-settings')
                ->color('gray')
                ->visible(fn () => self::canManage() && ! class_exists(Addon::class))
                ->url(fn () => EggUpdates::getUrl(panel: 'admin')),
        ];
    }

    public static function updateAll(): void
    {
        abort_unless(self::canManage(), 403);
        $done = 0;
        $failed = [];
        foreach (Egg::query()->whereIn('id', self::available()->select('egg_id'))->get() as $egg) {
            $result = EggUpdater::update($egg);
            $result->ok ? $done++ : $failed[] = $result->message;
        }
        if ($failed === []) {
            Notification::make()
                ->title(trans_choice('simplify-eggupdates::egg-updates.messages.updated_count', $done, ['count' => $done]))
                ->body(__('simplify-eggupdates::egg-updates.actions.update_kept'))
                ->success()
                ->send();

            return;
        }
        Notification::make()
            ->title(__('simplify-eggupdates::egg-updates.messages.some_failed', ['done' => $done, 'failed' => count($failed)]))
            ->body(implode(' ', $failed))
            ->danger()
            ->send();
    }

    /** @return Builder<EggUpdate> */
    public static function available(): Builder
    {
        return EggUpdate::query()->whereNotNull('found_hash');
    }

    /** @return Builder<EggUpdate> */
    private static function unreachable(): Builder
    {
        return EggUpdate::query()->where('link_failures', '>=', EggUpdate::LINK_FAILURES);
    }

    /** available, held (put back by hand), updated, unreachable, or a test stage: queued, installing, starting. */
    private static function status(Egg $egg): string
    {
        $stage = EggTester::stage($egg);
        if ($stage !== null) {
            return $stage;
        }
        $state = EggUpdate::query()->find($egg->id);
        if ($state?->found_hash === null) {
            return $state !== null && $state->link_failures >= EggUpdate::LINK_FAILURES ? 'unreachable' : 'updated';
        }

        return $state->held ? 'held' : 'available';
    }

    private static function color(string $status): string
    {
        return match ($status) {
            'updated' => 'success',
            'held', 'unreachable' => 'gray',
            'available' => 'warning',
            default => 'info',
        };
    }

    /** Why the last test of the listed update failed, or why its update URL cannot be checked. */
    private static function note(Egg $egg): ?string
    {
        if (self::status($egg) === 'unreachable') {
            $error = (string) EggUpdate::query()->find($egg->id)?->link_error;

            return in_array($error, ['network', 'invalid'], true)
                ? __('simplify-eggupdates::egg-updates.page.link_'.$error)
                : __('simplify-eggupdates::egg-updates.page.link_http', ['status' => $error]);
        }
        $state = EggUpdate::query()->find($egg->id);
        if ($state?->found_hash === null || $state->test_ok !== false || EggTester::stage($egg) !== null) {
            return null;
        }

        return __('simplify-eggupdates::egg-updates.tests.failed_short', ['reason' => $state->test_reason]);
    }

    /**
     * Explain's question button right after "Last test failed", when Explain is installed (Simplify-Explain's README,
     * "Explain your plugin's errors"). Without Explain the hook renders nothing.
     */
    private static function explain(Egg $egg): ?Htmlable
    {
        $state = EggUpdate::query()->find($egg->id);
        if (self::status($egg) === 'unreachable' || $state?->test_ok !== false || empty($state->test_log) || self::note($egg) === null) {
            return null;
        }
        $id = EggUpdatesProblems::id($egg);

        return FilamentView::renderHook('simplify::problem.actions', scopes: ['simplify-problem', 'simplify-problem:'.$id]);
    }

    private static function since(Egg $egg): mixed
    {
        $state = EggUpdate::query()->find($egg->id);
        if ($state?->found_at !== null) {
            return $state->found_at;
        }
        if (self::status($egg) === 'unreachable') {
            return $state->link_failed_at;
        }

        return EggUpdater::latest($egg)?->created_at;
    }

    /** How many servers use the egg: counted by the list's query, or counted here. */
    private static function servers(Egg $egg): int
    {
        return (int) ($egg->servers_count ?? $egg->servers()->count());
    }

    private static function send(Result $result): void
    {
        Notification::make()->title($result->message)->status($result->ok ? 'success' : 'danger')->send();
    }

    /** Reloads the Eggs page, so the block appears, changes or goes away and Pelican's list shows the new names. */
    private static function reload(Component $livewire): void
    {
        $livewire->redirect(EggResource::getUrl('index', panel: 'admin'), navigate: true);
    }
}
