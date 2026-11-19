@props(['title', 'description', 'singular', 'records'])
@php($isLocation = $singular === __('location'))

<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add :type', ['type' => $singular]) }}</flux:button>
    </header>

    <x-equipment.navigation :active="$isLocation ? 'locations' : 'categories'" />

    <flux:error name="deletion" />

    <div class="eq-inventory">
        <div class="eq-stock-tabs" role="group" aria-label="{{ __('Status') }}">
            @foreach (['all' => __('All entries'), 'active' => __('Active'), 'inactive' => __('Inactive')] as $value => $label)
                <button type="button" wire:key="status-tab-{{ $value }}" wire:click="$set('status', '{{ $value }}')" aria-pressed="{{ $this->status === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="eq-search-row">
            <div class="eq-search">
                <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" :aria-label="__('Search')" :placeholder="$isLocation ? __('Find a laboratory or storage area') : __('Find an equipment category')" clearable />
            </div>
            <span class="eq-record-count">{{ trans_choice(':count entry|:count entries', $records->total(), ['count' => number_format($records->total())]) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="clims-table eq-equipment-table w-full min-w-[650px] text-start text-sm">
                <caption class="sr-only">{{ $title }}</caption>
                <thead><tr><th scope="col">{{ __('Name') }}</th><th scope="col">{{ __('Equipment records') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="reference-{{ $record->id }}">
                            <td>
                                <div class="eq-asset-cell">
                                    <span class="eq-asset-icon" aria-hidden="true"><flux:icon :icon="$isLocation ? 'map-pin' : 'tag'" class="size-5" /></span>
                                    <div>
                                        <span class="font-semibold">{{ $record->name }}</span>
                                        @if ($record->description)
                                            <p class="mt-1 max-w-md text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $record->description }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td><a class="eq-assignment-count" href="{{ route('equipment.index', [$isLocation ? 'location' : 'category' => $record->id]) }}" aria-label="{{ __('View equipment assigned to :name', ['name' => $record->name]) }}" wire:navigate>{{ number_format($record->equipment_count) }}<flux:icon.computer-desktop class="size-3.5" /></a></td>
                            <td><span class="eq-status-label" data-active="{{ $record->is_active ? 'true' : 'false' }}"><span aria-hidden="true"></span>{{ $record->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $record->id }})" :aria-label="__('Edit :name', ['name' => $record->name])">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="setActive({{ $record->id }}, {{ $record->is_active ? 'false' : 'true' }})" wire:confirm="{{ $record->is_active ? __('Deactivate this entry? Existing equipment will keep its assignment, but it will not be offered for new assignments.') : __('Activate this entry for new equipment assignments?') }}" :aria-label="__(':action :name', ['action' => $record->is_active ? __('Deactivate') : __('Activate'), 'name' => $record->name])">
                                        {{ $record->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                    <span title="{{ $record->equipment_count > 0 ? __('Assigned to equipment. Deactivate this entry instead.') : __('Delete this entry permanently') }}">
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $record->id }})" wire:confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $record->name]) }}" :disabled="$record->equipment_count > 0" :class="$record->equipment_count > 0 ? '' : 'text-red-600! dark:text-red-400!'" :aria-label="__('Delete :name', ['name' => $record->name])">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">
                            <div class="eq-empty-state">
                                <span class="eq-asset-icon"><flux:icon :icon="$isLocation ? 'map-pin' : 'tag'" class="size-6" /></span>
                                <h2>{{ __('No matching entries') }}</h2>
                                <p>{{ __('Adjust your filters or add an entry to get started.') }}</p>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="eq-pagination">{{ $records->links() }}</div>
    </div>

    <flux:modal wire:model="showEditor" class="eq-reference-modal w-full max-w-lg!">
        <form wire:submit="save">
            <div class="eq-modal-heading">
                <span class="eq-modal-icon"><flux:icon :icon="$isLocation ? 'map-pin' : 'tag'" class="size-6" /></span>
                <h2>{{ $this->editingId ? __('Edit :type', ['type' => $singular]) : __('Add :type', ['type' => $singular]) }}</h2>
                <p>{{ $isLocation ? __('Give laboratory personnel a clear place to assign equipment.') : __('A recognizable category makes your inventory easier to navigate.') }}</p>
            </div>
            <div class="eq-fields">
                <flux:input wire:model="name" :label="__('Name')" :placeholder="$isLocation ? __('e.g. Computer Lab 1') : __('e.g. Monitors')" maxlength="255" required />
                <flux:textarea wire:model="description" :label="__('Description (optional)')" :placeholder="$isLocation ? __('Room details or storage instructions') : __('What kind of equipment belongs here?')" rows="3" maxlength="2000" />
                <div class="eq-activation">
                    <flux:switch wire:model="is_active" :label="__('Active for new assignments')" />
                    <p>{{ __('Existing equipment keeps its assignment when this entry is inactive.') }}</p>
                </div>
                <flux:error name="is_active" />
            </div>
            <footer class="eq-modal-actions">
                <flux:button type="button" variant="ghost" wire:click="$set('showEditor', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('Save changes') }}</flux:button>
            </footer>
        </form>
    </flux:modal>
</section>
