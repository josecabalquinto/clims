<?php

use App\Enums\EquipmentCondition;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Equipment')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';
    #[Url(except: '')]
    public string $category = '';
    #[Url(except: 'all')]
    public string $availability = 'all';
    #[Url(except: '')]
    public string $condition = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', Equipment::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'availability', 'condition'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'availability', 'condition');
        $this->resetPage();
    }

    #[Computed]
    public function equipment(): LengthAwarePaginator
    {
        return Equipment::with('category')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $search) => $search
                ->where('asset_tag', 'like', '%'.$this->search.'%')
                ->orWhere('name', 'like', '%'.$this->search.'%')
                ->orWhere('serial_number', 'like', '%'.$this->search.'%')))
            ->when($this->category !== '', fn (Builder $query) => $query->where('category_id', (int) $this->category))
            ->when($this->condition !== '', fn (Builder $query) => $query->where('condition', $this->condition))
            ->when($this->availability === 'available', fn (Builder $query) => $query->where('available_quantity', '>', 0))
            ->when($this->availability === 'defective', fn (Builder $query) => $query->where('defective_quantity', '>', 0))
            ->when($this->availability === 'borrowed', fn (Builder $query) => $query->whereHas('borrowItems', fn (Builder $items) => $items->whereColumn('returned_quantity', '<', 'quantity')))
            ->orderBy('name')->orderBy('id')->paginate(15);
    }

    #[Computed]
    public function categories(): Collection
    {
        return EquipmentCategory::orderBy('name')->get();
    }

}; ?>

<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading">
        <div>
            <h1>{{ __('Equipment') }}</h1>
            <p>{{ __('A clear view of what your laboratory has, and what is ready to use.') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <flux:button icon="printer" :href="route('equipment.report.print', ['search' => $search, 'category' => $category, 'availability' => $availability, 'condition' => $condition])" target="_blank">{{ __('Print Report') }}</flux:button>
            <flux:button icon="arrow-down-tray" :href="route('equipment.report.export', ['search' => $search, 'category' => $category, 'availability' => $availability, 'condition' => $condition])">{{ __('Export to Excel') }}</flux:button>
            @can('create', Equipment::class)
                <flux:button variant="primary" icon="plus" :href="route('equipment.create')" wire:navigate>{{ __('Register equipment') }}</flux:button>
            @endcan
        </div>
    </header>

    <x-equipment.navigation />

    <div class="eq-inventory">
        <div class="eq-stock-tabs" role="group" aria-label="{{ __('Availability') }}">
            @foreach (['all' => __('All equipment'), 'available' => __('Available'), 'borrowed' => __('Borrowed'), 'defective' => __('Defective')] as $value => $label)
                <button type="button" wire:key="stock-tab-{{ $value }}" wire:click="$set('availability', '{{ $value }}')" aria-pressed="{{ $availability === $value ? 'true' : 'false' }}">
                    @if ($value !== 'all') <span class="eq-tab-dot" aria-hidden="true"></span> @endif
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="eq-search-row" x-data="{ filtersOpen: false }" x-on:keydown.escape.window="if (filtersOpen) { filtersOpen = false; $refs.filterTrigger.focus() }">
            <div class="eq-search">
                <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" :aria-label="__('Search equipment')" :placeholder="__('Find an asset tag, equipment name, or serial number')" clearable />
            </div>
            <div class="eq-filter-anchor" x-on:click.outside="filtersOpen = false">
                <button type="button" class="eq-filter-trigger" x-ref="filterTrigger" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="equipment-filters">
                    <flux:icon.adjustments-horizontal class="size-4" />
                    {{ __('Filters') }}
                    @if ($category !== '' || $condition !== '')
                        <span class="eq-filter-count">{{ (int) ($category !== '') + (int) ($condition !== '') }}</span>
                    @endif
                </button>
                <div id="equipment-filters" class="eq-filter-panel" x-show="filtersOpen" x-cloak>
                    <div class="eq-filter-panel-heading">
                        <span>{{ __('Refine your inventory') }}</span>
                        <button type="button" x-on:click="filtersOpen = false; $refs.filterTrigger.focus()" aria-label="{{ __('Close filters') }}"><flux:icon.x-mark class="size-4" /></button>
                    </div>
                    <flux:select wire:model.live="category" :label="__('Category')">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($this->categories as $choice)
                            <option value="{{ $choice->id }}" wire:key="category-filter-{{ $choice->id }}">{{ $choice->name }}{{ $choice->is_active ? '' : __(' (inactive)') }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="condition" :label="__('Condition')">
                        <option value="">{{ __('All conditions') }}</option>
                        @foreach (EquipmentCondition::cases() as $choice)
                            <option value="{{ $choice->value }}" wire:key="condition-filter-{{ $choice->value }}">{{ __($choice->label()) }}</option>
                        @endforeach
                    </flux:select>
                    <button type="button" class="eq-text-action" wire:click="clearFilters">{{ __('Clear filters') }}</button>
                </div>
            </div>
        </div>

        @if ($category !== '' || $condition !== '')
            <div class="eq-filter-chips" aria-label="{{ __('Active filters') }}">
                @if ($category !== '')
                    <button type="button" wire:click="$set('category', '')" aria-label="{{ __('Remove category filter') }}"><flux:icon.tag class="size-3.5" />{{ $this->categories->firstWhere('id', (int) $category)?->name ?? __('Category') }}<flux:icon.x-mark class="size-3" /></button>
                @endif
                @if ($condition !== '')
                    <button type="button" wire:click="$set('condition', '')" aria-label="{{ __('Remove condition filter') }}">{{ __(EquipmentCondition::tryFrom($condition)?->label() ?? $condition) }}<flux:icon.x-mark class="size-3" /></button>
                @endif
            </div>
        @endif

        <div class="eq-results-heading">
            <p><strong>{{ number_format($this->equipment->total()) }}</strong> {{ __('equipment records') }}</p>
            @if ($search !== '' || $category !== '' || $condition !== '' || $availability !== 'all')
                <button type="button" class="eq-text-action" wire:click="clearFilters">{{ __('Reset view') }}</button>
            @else
                <span>{{ __('Your laboratory inventory') }}</span>
            @endif
        </div>

        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="clims-table eq-equipment-table w-full min-w-[960px] text-sm">
                <caption class="sr-only">{{ __('Equipment inventory') }}</caption>
                <thead>
                    <tr><th scope="col">{{ __('Equipment') }}</th><th scope="col">{{ __('Total units') }}</th><th scope="col">{{ __('Available') }}</th><th scope="col">{{ __('Borrowed') }}</th><th scope="col">{{ __('Defective') }}</th><th scope="col">{{ __('Condition') }}</th><th scope="col">{{ __('Actions') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->equipment as $item)
                        <tr wire:key="equipment-{{ $item->id }}">
                            <td>
                                <div class="eq-asset-cell">
                                    <span class="eq-asset-icon" aria-hidden="true"><flux:icon.computer-desktop class="size-5" /></span>
                                    <div class="min-w-0">
                                        <a href="{{ route('equipment.show', $item) }}" class="eq-asset-name" wire:navigate>{{ $item->name }}</a>
                                        <p class="eq-asset-meta"><span>{{ $item->asset_tag }}</span><span>{{ $item->category->name }}</span></p>
                                    </div>
                                </div>
                            </td>
                            <td class="eq-numeric">{{ number_format($item->quantity) }}</td>
                            <td><span class="eq-available-count" data-available="{{ $item->available_quantity > 0 ? 'true' : 'false' }}">{{ number_format($item->available_quantity) }}</span></td>
                            <td class="eq-numeric">{{ number_format($item->outstandingQuantity()) }}</td>
                            <td class="eq-numeric">{{ number_format($item->defective_quantity) }}</td>
                            <td><span class="eq-condition-label">{{ __(EquipmentCondition::tryFrom($item->condition)?->label() ?? ucfirst($item->condition)) }}</span></td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="qr-code" :href="route('equipment.show', $item)" :aria-label="__('Open QR label for :name', ['name' => $item->name])" wire:navigate>{{ __('QR label') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('equipment.edit', $item)" :aria-label="__('Edit :name', ['name' => $item->name])" wire:navigate>{{ __('Edit') }}</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">
                            <div class="eq-empty-state">
                                <span class="eq-asset-icon"><flux:icon.archive-box class="size-6" /></span>
                                <h2>{{ __('No equipment found') }}</h2>
                                <p>{{ __('Register equipment or adjust your search and filters.') }}</p>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="eq-pagination">{{ $this->equipment->links() }}</div>
    </div>
</section>

