<?php

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Activity log details')] class extends Component {
    #[Locked]
    public int $logId;

    public function boot(): void
    {
        Auth::user()?->refresh();
        Gate::authorize('viewAny', ActivityLog::class);
    }

    public function mount(ActivityLog $log): void
    {
        Gate::authorize('view', $log);
        $this->logId = $log->id;
    }

    #[Computed]
    public function record(): ActivityLog
    {
        $record = ActivityLog::with('user')->findOrFail($this->logId);
        Gate::authorize('view', $record);

        return $record;
    }

    /** @return list<array{field: string, before: string, after: string}> */
    #[Computed]
    public function changes(): array
    {
        return $this->record->displayChanges();
    }
}; ?>

<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading">
        <div><h1>{{ __(ActivityLog::actionLabel($this->record->action)) }}</h1><p>{{ __('Activity log') }} #{{ $logId }}</p></div>
        <flux:button :href="route('activity-logs.index')" wire:navigate>{{ __('Back to activity logs') }}</flux:button>
    </header>
    <div class="eq-editor p-6 sm:p-7">
        <div class="mb-6 flex items-center gap-2 text-sm text-brand-deep"><flux:icon.lock-closed class="size-4" />{{ __('Read-only audit record') }}</div>
        <dl class="grid gap-6 sm:grid-cols-2">
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Affected record') }}</dt><dd class="mt-2 break-words text-sm font-medium">{{ $this->record->subjectLabel() }}</dd></div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Responsible user') }}</dt><dd class="mt-2 break-words text-sm font-medium">{{ $this->record->user?->name ?? __('System') }}</dd></div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Recorded at') }}</dt><dd class="mt-2 text-sm">{{ $this->record->created_at->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i:s A') }}<span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ config('clims.display_timezone') }}</span></dd></div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Action reference') }}</dt><dd class="mt-2 break-words text-sm">{{ $this->record->action }}</dd></div>
        </dl>
    </div>
    <div class="eq-inventory min-w-0">
        <div class="p-6"><h2 class="text-lg font-semibold text-brand-deep">{{ __('Recorded changes') }}</h2><p class="mt-2 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('A dash means no value was recorded for that side. “Not set” means an explicitly empty value. Record IDs refer to the values stored at the time.') }}</p></div>
        <div class="overflow-x-auto">
            <table class="clims-table eq-equipment-table w-full min-w-[620px] table-fixed text-sm">
                <caption class="sr-only">{{ __('Before and after values for this activity') }}</caption>
                <thead><tr><th scope="col" class="w-40">{{ __('Field') }}</th><th scope="col">{{ __('Before') }}</th><th scope="col" class="text-start!">{{ __('After') }}</th></tr></thead>
                <tbody>
                    @forelse ($this->changes as $change)
                        <tr wire:key="change-{{ $loop->index }}"><td class="align-top! font-medium">{{ __($change['field']) }}</td><td class="align-top! whitespace-pre-wrap break-words">{{ $change['before'] }}</td><td class="align-top! whitespace-pre-wrap break-words">{{ $change['after'] }}</td></tr>
                    @empty
                        <tr><td colspan="3"><div class="eq-empty-state"><h3>{{ __('No displayable changes recorded') }}</h3><p>{{ __('This activity has no approved field changes to display.') }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
