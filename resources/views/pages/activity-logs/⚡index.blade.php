<?php

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('View Log Details')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';
    #[Url(except: '')]
    public string $module = '';
    #[Url(except: '')]
    public string $actor = '';
    #[Url(except: '')]
    public string $action = '';
    #[Url(except: '')]
    public string $fromDate = '';
    #[Url(except: '')]
    public string $toDate = '';

    public function boot(): void
    {
        Auth::user()?->refresh();
        Gate::authorize('viewAny', ActivityLog::class);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
        if (in_array($property, ['fromDate', 'toDate'], true)) {
            $this->validate([
                'fromDate' => ['nullable', 'date_format:Y-m-d'],
                'toDate' => ['nullable', 'date_format:Y-m-d', ...($this->validDate($this->fromDate) ? ['after_or_equal:fromDate'] : [])],
            ], ['toDate.after_or_equal' => 'The end date must be on or after the start date.']);
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'module', 'actor', 'action', 'fromDate', 'toDate');
        $this->resetValidation();
        $this->resetPage();
    }

    private function validDate(string $date): bool
    {
        return validator(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->passes();
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $query = ActivityLog::with('user');
        if ($this->search !== '') {
            $query->where(function (Builder $query): void {
                $needle = '%'.$this->search.'%';
                $query->where('action', 'like', $needle)
                    ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', $needle));
                $matchingActions = array_keys(array_filter($this->actions, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower(trim($this->search)))));
                $query->orWhereIn('action', $matchingActions);
                foreach (['name', 'asset_tag', 'borrower_name'] as $field) {
                    $query->orWhere('new_values->'.$field, 'like', $needle)->orWhere('old_values->'.$field, 'like', $needle);
                }
                if (ctype_digit(trim($this->search))) {
                    $query->orWhere('subject_id', (int) $this->search);
                }
            });
        }
        $types = match ($this->module) {
            'equipment' => ['equipment', 'equipment_category', 'equipment_location'],
            'borrowing' => ['borrow_transaction', 'borrow_item', 'borrow_return'],
            'users' => ['user'],
            '' => null,
            default => [],
        };
        if ($types !== null) {
            $query->whereIn('subject_type', $types);
        }
        if ($this->actor === 'system') {
            $query->whereNull('user_id');
        } elseif ($this->actor !== '') {
            $query->where('user_id', (int) $this->actor);
        }
        if ($this->action !== '') {
            $query->where('action', $this->action);
        }
        if ($this->validDate($this->fromDate)) {
            $query->where('created_at', '>=', CarbonImmutable::parse($this->fromDate, config('clims.display_timezone'))->startOfDay()->utc());
        }
        if ($this->validDate($this->toDate)) {
            $query->where('created_at', '<', CarbonImmutable::parse($this->toDate, config('clims.display_timezone'))->startOfDay()->addDay()->utc());
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function actors(): Collection
    {
        return User::select(['id', 'name', 'is_active'])->whereHas('activityLogs')->orderBy('name')->orderBy('id')->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function actions(): array
    {
        return ActivityLog::query()->distinct()->orderBy('action')->pluck('action')
            ->mapWithKeys(fn (string $action): array => [$action => __(ActivityLog::actionLabel($action))])->all();
    }
}; ?>

<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading">
        <div><h1>{{ __('View Log Details') }}</h1><p>{{ __('A read-only record of inventory, borrowing, and account changes.') }}</p></div>
        <span class="eq-location"><flux:icon.lock-closed class="size-4" />{{ __('Read only') }}</span>
    </header>

    <div class="eq-inventory">
        <div class="eq-stock-tabs" role="group" aria-label="{{ __('Activity module') }}">
            @foreach (['' => 'All activity', 'equipment' => 'Equipment', 'borrowing' => 'Borrowing', 'users' => 'Users'] as $value => $label)
                <button type="button" wire:click="$set('module', '{{ $value }}')" aria-pressed="{{ $module === $value ? 'true' : 'false' }}">{{ __($label) }}</button>
            @endforeach
        </div>
        <div class="eq-search-row" x-data="{ filtersOpen: false }" x-on:keydown.escape.window="if (filtersOpen) { filtersOpen = false; $refs.filterTrigger.focus() }">
            <div class="eq-search"><flux:input type="search" wire:model.live.debounce.300ms="search" icon="magnifying-glass" :aria-label="__('Search activity logs')" :placeholder="__('Find a user, action, recorded name, asset tag, or record ID')" clearable /></div>
            <div class="eq-filter-anchor" x-on:click.outside="filtersOpen = false">
                <button class="eq-filter-trigger" type="button" x-ref="filterTrigger" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="activity-filters">
                    <flux:icon.adjustments-horizontal class="size-4" />{{ __('Filters') }}
                    @if ($actor !== '' || $action !== '' || $fromDate !== '' || $toDate !== '')<span class="eq-filter-count">{{ (int) ($actor !== '') + (int) ($action !== '') + (int) ($fromDate !== '') + (int) ($toDate !== '') }}</span>@endif
                </button>
                <div id="activity-filters" class="eq-filter-panel" x-show="filtersOpen" x-cloak>
                    <div class="eq-filter-panel-heading"><span>{{ __('Refine activity') }}</span><button type="button" x-on:click="filtersOpen = false; $refs.filterTrigger.focus()" aria-label="{{ __('Close filters') }}"><flux:icon.x-mark class="size-4" /></button></div>
                    <flux:select wire:model.live="actor" :label="__('Responsible user')">
                        <option value="">{{ __('All users') }}</option><option value="system">{{ __('System / no user') }}</option>
                        @foreach ($this->actors as $user)<option value="{{ $user->id }}">{{ $user->name }}{{ $user->is_active ? '' : __(' (inactive)') }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model.live="action" :label="__('Action')">
                        <option value="">{{ __('All actions') }}</option>
                        @foreach ($this->actions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </flux:select>
                    <flux:input type="date" wire:model.live="fromDate" :label="__('From date')" />
                    <flux:input type="date" wire:model.live="toDate" :label="__('Through date')" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ config('clims.display_timezone') }}</p>
                    <button type="button" class="eq-text-action" wire:click="clearFilters">{{ __('Clear filters') }}</button>
                </div>
            </div>
        </div>
        <div class="eq-results-heading">
            <p><strong>{{ number_format($this->logs->total()) }}</strong> {{ trans_choice('activity record|activity records', $this->logs->total()) }}</p>
            @if ($search !== '' || $module !== '' || $actor !== '' || $action !== '' || $fromDate !== '' || $toDate !== '')<button type="button" class="eq-text-action" wire:click="clearFilters">{{ __('Reset view') }}</button>@else<span>{{ __('Newest first') }}</span>@endif
        </div>
        @if ($actor !== '' || $action !== '' || $fromDate !== '' || $toDate !== '')
            <div class="eq-filter-chips" aria-label="{{ __('Active filters') }}">
                @if ($actor !== '')<button type="button" wire:click="$set('actor', '')">{{ $actor === 'system' ? __('System / no user') : ($this->actors->firstWhere('id', (int) $actor)?->name ?? __('User')) }}<flux:icon.x-mark class="size-3" /></button>@endif
                @if ($action !== '')<button type="button" wire:click="$set('action', '')">{{ __(ActivityLog::actionLabel($action)) }}<flux:icon.x-mark class="size-3" /></button>@endif
                @if ($fromDate !== '')<button type="button" wire:click="$set('fromDate', '')">{{ __('From') }} {{ $fromDate }}<flux:icon.x-mark class="size-3" /></button>@endif
                @if ($toDate !== '')<button type="button" wire:click="$set('toDate', '')">{{ __('Through') }} {{ $toDate }}<flux:icon.x-mark class="size-3" /></button>@endif
            </div>
        @endif
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="clims-table eq-equipment-table w-full min-w-[850px] text-sm">
                <caption class="sr-only">{{ __('Application activity logs') }}</caption>
                <thead><tr><th scope="col">{{ __('Activity') }}</th><th scope="col">{{ __('Affected record') }}</th><th scope="col">{{ __('Responsible user') }}</th><th scope="col">{{ __('When') }}</th><th scope="col" class="text-center!">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($this->logs as $log)
                        <tr wire:key="activity-{{ $log->id }}">
                            <td><a class="eq-asset-name" href="{{ route('activity-logs.show', $log) }}" wire:navigate>{{ __(ActivityLog::actionLabel($log->action)) }}</a><p class="eq-asset-meta">{{ __('Log') }} #{{ $log->id }}</p></td>
                            <td class="max-w-80 break-words">{{ $log->subjectLabel() }}</td>
                            <td>{{ $log->user?->name ?? __('System') }}</td>
                            <td class="whitespace-nowrap">{{ $log->created_at->setTimezone(config('clims.display_timezone'))->format('M j, Y') }}<p class="eq-asset-meta">{{ $log->created_at->setTimezone(config('clims.display_timezone'))->format('g:i A') }} {{ config('clims.display_timezone') }}</p></td>
                            <td class="text-center"><flux:button variant="ghost" size="sm" :href="route('activity-logs.show', $log)" :aria-label="__('View log :id', ['id' => $log->id])" wire:navigate>{{ __('View details') }}</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="eq-empty-state"><h2>{{ __('No activity records found') }}</h2><p>{{ __('Activity appears as personnel manage inventory, borrowing, and accounts. Adjust filters to review other records.') }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="eq-pagination">{{ $this->logs->links() }}</div>
    </div>
</section>

