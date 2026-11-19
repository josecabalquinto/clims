<?php

use App\Models\BorrowItem;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public string $equipmentSearch = '';
    public string $equipmentCategory = '';

    public function boot(): void
    {
        Auth::user()?->refresh();
        Gate::authorize('access-application');
    }

    /** @return array{total: int, records: int, available: int, borrowed: int, defective: int, active: int, overdue: int} */
    #[Computed]
    public function totals(): array
    {
        $stock = Equipment::query()->selectRaw('COUNT(*) AS records, COALESCE(SUM(quantity), 0) AS total, COALESCE(SUM(available_quantity), 0) AS available, COALESCE(SUM(defective_quantity), 0) AS defective')->toBase()->first();
        $loans = BorrowTransaction::query()
            ->whereHas('items', fn(Builder $items) => $items->whereColumn('returned_quantity', '<', 'quantity'))
            ->selectRaw('COUNT(*) AS active, COALESCE(SUM(CASE WHEN expected_return_at < ? THEN 1 ELSE 0 END), 0) AS overdue', [now()])
            ->toBase()
            ->first();

        return [
            'total' => (int) $stock->total,
            'records' => (int) $stock->records,
            'available' => (int) $stock->available,
            'borrowed' => (int) BorrowItem::query()->selectRaw('COALESCE(SUM(quantity - returned_quantity), 0) AS outstanding')->toBase()->value('outstanding'),
            'defective' => (int) $stock->defective,
            'active' => (int) $loans->active,
            'overdue' => (int) $loans->overdue,
        ];
    }

    /** @return Collection<int, BorrowTransaction> */
    #[Computed]
    public function recentLoans(): Collection
    {
        return BorrowTransaction::with(['items.equipment', 'recordedBy'])
            ->orderByDesc('borrowed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, EquipmentCategory> */
    #[Computed]
    public function equipmentCategories(): Collection
    {
        return EquipmentCategory::query()->whereHas('equipment')->orderBy('name')->get();
    }

    /** @return Collection<int, Equipment> */
    #[Computed]
    public function filteredEquipment(): Collection
    {
        return Equipment::query()->with('category')
            ->when($this->equipmentSearch !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', '%'.$this->equipmentSearch.'%')
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', '%'.$this->equipmentSearch.'%'))))
            ->when($this->equipmentCategory !== '', fn (Builder $query) => $query->where('category_id', (int) $this->equipmentCategory))
            ->orderBy('name')->orderBy('id')->get();
    }

    #[Computed]
    public function filteredQuantity(): int
    {
        return (int) $this->filteredEquipment->sum('quantity');
    }

    /** @return array<int, array{id: int, name: string, asset_tag: string, units: int}> */
    #[Computed]
    public function mostBorrowed(): array
    {
        return BorrowItem::query()
            ->with('equipment')
            ->select('equipment_id')
            ->selectRaw('SUM(quantity) AS borrowed_units')
            ->whereHas('transaction', fn(Builder $query) => $query->whereBetween('borrowed_at', [now()->subDays(30), now()]))
            ->groupBy('equipment_id')
            ->orderByDesc('borrowed_units')
            ->orderBy('equipment_id')
            ->limit(5)
            ->get()
            ->map(
                fn(BorrowItem $item): array => [
                    'id' => $item->equipment_id,
                    'name' => $item->equipment->name,
                    'asset_tag' => $item->equipment->asset_tag,
                    'units' => (int) $item->getAttribute('borrowed_units'),
                ],
            )
            ->all();
    }

    public function refreshDashboard(): void
    {
        Gate::authorize('access-application');
        unset($this->totals, $this->recentLoans, $this->mostBorrowed, $this->equipmentCategories, $this->filteredEquipment, $this->filteredQuantity);
    }
}; ?>

<section class="eq-module dash-workspace">
    <header class="eq-page-heading">
        <div>
            <h1>{{ __('Dashboard') }}</h1>
            <p>{{ __('Equipment availability and borrowing at a glance.') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <flux:button icon="arrow-path" wire:click="refreshDashboard" wire:loading.attr="disabled"
                wire:target="refreshDashboard">{{ __('Refresh') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('borrowing.create')" wire:navigate>
                {{ __('Record borrowing') }}</flux:button>
        </div>
    </header>

    <section class="dash-inventory" aria-labelledby="inventory-summary-heading" wire:loading.class="opacity-60">
        <div class="dash-section-heading">
            <h2 id="inventory-summary-heading">{{ __('Equipment overview') }}</h2>
            <span>{{ trans_choice('Physical units across :count inventory record|Physical units across :count inventory records', $this->totals['records'], ['count' => number_format($this->totals['records'])]) }}</span>
        </div>
        <div class="dash-totals" aria-label="{{ __('Laboratory totals') }}">
            @foreach ([['key' => 'total', 'label' => 'Total equipment', 'href' => route('equipment.index')], ['key' => 'available', 'label' => 'Available units', 'href' => route('equipment.index', ['availability' => 'available'])], ['key' => 'borrowed', 'label' => 'Borrowed units', 'href' => route('equipment.index', ['availability' => 'borrowed'])], ['key' => 'defective', 'label' => 'Defective units', 'href' => route('equipment.index', ['availability' => 'defective'])]] as $metric)
                <a href="{{ $metric['href'] }}" wire:navigate wire:key="metric-{{ $metric['key'] }}" class="dash-total">
                    <span>{{ __($metric['label']) }}</span>
                    <strong>{{ number_format($this->totals[$metric['key']]) }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    <section class="eq-inventory" aria-labelledby="dashboard-equipment-heading">
        <div class="dash-section-heading p-5">
            <div><h2 id="dashboard-equipment-heading">{{ __('Find equipment') }}</h2><span>{{ __('Search and category filters work together.') }}</span></div>
            <strong>{{ trans_choice(':count total unit|:count total units', $this->filteredQuantity, ['count' => number_format($this->filteredQuantity)]) }}</strong>
        </div>
        <div class="eq-search-row px-5 pb-5">
            <div class="eq-search"><flux:input wire:model.live.debounce.300ms="equipmentSearch" type="search" icon="magnifying-glass" :placeholder="__('Search by equipment name or category')" clearable /></div>
            <div class="w-full sm:w-56"><flux:select wire:model.live="equipmentCategory" :aria-label="__('Filter equipment by category')"><option value="">{{ __('All equipment') }}</option>@foreach($this->equipmentCategories as $category)<option value="{{ $category->id }}" wire:key="dashboard-category-{{ $category->id }}">{{ $category->name }}</option>@endforeach</flux:select></div>
        </div>
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="clims-table w-full min-w-[560px] text-sm"><thead><tr><th>{{ __('Equipment') }}</th><th>{{ __('Category') }}</th><th>{{ __('Total quantity') }}</th><th>{{ __('Available') }}</th></tr></thead><tbody>
                @forelse($this->filteredEquipment as $item)<tr wire:key="dashboard-equipment-{{ $item->id }}"><td><a class="eq-asset-name" href="{{ route('equipment.show', $item) }}" wire:navigate>{{ $item->name }}</a></td><td>{{ $item->category->name }}</td><td class="eq-numeric">{{ number_format($item->quantity) }}</td><td class="eq-numeric">{{ number_format($item->available_quantity) }}</td></tr>@empty<tr><td colspan="4"><div class="eq-empty-state"><h3>{{ __('No equipment found') }}</h3><p>{{ __('Try another name or category.') }}</p></div></td></tr>@endforelse
            </tbody></table>
        </div>
    </section>

    <div class="dash-panels">
        <div class="dash-charts">
            <section class="dash-panel" aria-labelledby="status-chart-heading">
                <div class="dash-section-heading">
                    <h2 id="status-chart-heading">{{ __('Equipment status') }}</h2>
                    <span>{{ __('Current physical units') }}</span>
                </div>
                @php
                    $statusTotal = $this->totals['available'] + $this->totals['borrowed'] + $this->totals['defective'];
                    $segments = [
                        ['key' => 'available', 'label' => __('Available'), 'color' => '#087f80'],
                        ['key' => 'borrowed', 'label' => __('Borrowed'), 'color' => '#d59a28'],
                        ['key' => 'defective', 'label' => __('Defective'), 'color' => '#c45562'],
                    ];
                    $offset = 0;
                @endphp
                <figure class="dash-status-chart" wire:loading.class="opacity-60">
                    <div class="dash-donut">
                        <svg viewBox="0 0 180 180" role="img" aria-labelledby="status-chart-title">
                            <title id="status-chart-title">
                                {{ __('Available: :available, borrowed: :borrowed, defective: :defective', ['available' => $this->totals['available'], 'borrowed' => $this->totals['borrowed'], 'defective' => $this->totals['defective']]) }}
                            </title>
                            <circle cx="90" cy="90" r="70" fill="none" stroke="var(--color-line)"
                                stroke-width="20" />
                            @foreach ($segments as $segment)
                                @php($share = $statusTotal > 0 ? ($this->totals[$segment['key']] / $statusTotal) * 100 : 0)
                                @if ($share > 0)
                                    <circle cx="90" cy="90" r="70" fill="none"
                                        stroke="{{ $segment['color'] }}" stroke-width="20" pathLength="100"
                                        stroke-dasharray="{{ $share }} {{ 100 - $share }}"
                                        stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 90 90)">
                                        <title>{{ $segment['label'] }}:
                                            {{ number_format($this->totals[$segment['key']]) }}
                                            ({{ number_format($share, 1) }}%)</title>
                                    </circle>
                                @endif
                                @php($offset += $share)
                            @endforeach
                        </svg>
                        <div class="dash-donut-center" aria-hidden="true">
                            <strong>{{ number_format($statusTotal) }}</strong><span>{{ __('units') }}</span></div>
                    </div>
                    <figcaption class="dash-chart-legend">
                        @foreach ($segments as $segment)
                            <a href="{{ route('equipment.index', ['availability' => $segment['key']]) }}"
                                wire:navigate>
                                <span class="dash-legend-dot" style="background-color: {{ $segment['color'] }}"
                                    aria-hidden="true"></span>
                                <span>{{ $segment['label'] }}</span>
                                <strong>{{ number_format($this->totals[$segment['key']]) }}</strong>
                                <small>{{ $statusTotal > 0 ? number_format(($this->totals[$segment['key']] / $statusTotal) * 100, 1) : '0' }}%</small>
                            </a>
                        @endforeach
                    </figcaption>
                </figure>
                @if ($statusTotal === 0)
                    <p class="dash-chart-note">{{ __('No equipment registered yet.') }}</p>
                @endif
            </section>

            <section class="dash-panel" aria-labelledby="borrowed-chart-heading">
                <div class="dash-section-heading">
                    <h2 id="borrowed-chart-heading">{{ __('Most borrowed equipment') }}</h2>
                    <span>{{ __('Last 30 days') }}</span>
                </div>
                <p class="dash-chart-note">{{ __('Top five by units borrowed, including items already returned.') }}
                </p>
                <ol class="dash-ranking" aria-label="{{ __('Most borrowed equipment in the last 30 days') }}"
                    wire:loading.class="opacity-60">
                    @forelse ($this->mostBorrowed as $equipment)
                        <li wire:key="popular-equipment-{{ $equipment['id'] }}">
                            <div class="dash-ranking-label">
                                <a href="{{ route('equipment.show', $equipment['id']) }}" class="eq-asset-name"
                                    wire:navigate>{{ $equipment['name'] }}</a>
                                <strong>{{ trans_choice(':count unit|:count units', $equipment['units'], ['count' => number_format($equipment['units'])]) }}</strong>
                            </div>
                            <p class="dash-detail">{{ $equipment['asset_tag'] }}</p>
                            <div class="dash-bar-track" aria-hidden="true">
                                <div class="dash-bar-fill"
                                    style="width: {{ ($equipment['units'] / max(1, $this->mostBorrowed[0]['units'])) * 100 }}%">
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="eq-empty-state">
                            <h3>{{ __('No borrowing in the last 30 days') }}</h3>
                            <p>{{ __('Borrowing activity will appear here as equipment is borrowed.') }}</p>
                        </li>
                    @endforelse
                </ol>
            </section>
        </div>

        <section class="dash-panel" aria-labelledby="dashboard-loans-heading">
            <div class="dash-section-heading">
                <h2 id="dashboard-loans-heading">{{ __('Recent borrowing') }}</h2>
                <a href="{{ route('borrowing.index') }}" class="eq-text-action"
                    wire:navigate>{{ __('View Borrow Log') }}</a>
            </div>
            <div class="dash-loan-counts" wire:loading.class="opacity-60">
                <a href="{{ route('borrowing.index') }}"
                    wire:navigate><strong>{{ number_format($this->totals['active']) }}</strong><span>{{ __('Active borrows') }}</span></a>
                <a href="{{ route('borrowing.index', ['status' => 'overdue']) }}" wire:navigate
                    data-overdue="{{ $this->totals['overdue'] > 0 ? 'true' : 'false' }}">
                    <strong>{{ number_format($this->totals['overdue']) }}</strong><span>{{ __('Overdue borrows') }}</span>
                </a>
            </div>
            <ul class="dash-activity" aria-label="{{ __('Recent borrowing transactions') }}"
                wire:loading.class="opacity-60">
                @forelse ($this->recentLoans as $loan)
                    <li wire:key="recent-loan-{{ $loan->id }}">
                        <div class="dash-recent-heading">
                            <a class="eq-asset-name" href="{{ route('borrowing.show', $loan) }}"
                                wire:navigate>{{ $loan->borrower_name }}</a>
                            <flux:badge size="sm"
                                :color="$loan->isOverdue() ? 'red' : ($loan->outstandingQuantity() === 0 ? 'teal' : 'zinc')">
                                {{ $loan->statusLabel() }}</flux:badge>
                        </div>
                        <p class="dash-loan-equipment">
                            {{ $loan->items->first()?->equipment->name ?? __('No equipment recorded') }}
                            @if ($loan->items->count() > 1)
                                <span>{{ __('+:count more', ['count' => $loan->items->count() - 1]) }}</span>
                            @endif
                        </p>
                        <p class="dash-detail">
                            {{ trans_choice(':count unit outstanding|:count units outstanding', $loan->outstandingQuantity(), ['count' => number_format($loan->outstandingQuantity())]) }}
                        </p>
                        <div class="dash-activity-meta">
                            <span>{{ __('Borrow') }} #{{ $loan->id }}</span>
                            <time datetime="{{ $loan->expected_return_at->toIso8601String() }}">{{ __('Due') }}
                                {{ $loan->expected_return_at->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i A') }}</time>
                        </div>
                    </li>
                @empty
                    <li class="eq-empty-state">
                        <h3>{{ __('No borrowing recorded yet') }}</h3>
                        <p>{{ __('Record a borrow transaction to track equipment handovers and returns.') }}</p>
                    </li>
                @endforelse
            </ul>
        </section>
    </div>

    <p class="dash-footnote">{{ __('Times shown in') }} {{ config('clims.display_timezone') }}.</p>
</section>
