<?php
use App\Models\BorrowTransaction;
use App\Enums\BorrowerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\{Computed, Title, Url};
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Borrow Log')] class extends Component {
    use WithPagination;
    #[Url(except: '')]
    public string $search = '';
    #[Url(except: 'all')]
    public string $status = 'all';
    #[Url(except: '')]
    public string $borrowerType = '';
    public function boot(): void
    {
        Gate::authorize('viewAny', BorrowTransaction::class);
    }
    public function updated(): void
    {
        $this->resetPage();
    }
    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'borrowerType');
        $this->resetPage();
    }
    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        $query = BorrowTransaction::with(['items.equipment', 'recordedBy'])
            ->when(
                $this->search !== '',
                fn(Builder $q) => $q->where(
                    fn(Builder $s) => $s
                        ->where('borrower_name', 'like', '%' . $this->search . '%')
                        ->orWhere('borrower_identifier', 'like', '%' . $this->search . '%')
                        ->orWhereHas('items.equipment', fn(Builder $e) => $e->where('name', 'like', '%' . $this->search . '%')->orWhere('asset_tag', 'like', '%' . $this->search . '%')),
                ),
            )
            ->when($this->borrowerType !== '', fn(Builder $q) => $q->where('borrower_type', $this->borrowerType));
        if (in_array($this->status, ['borrowed', 'partially_returned', 'overdue'], true)) {
            $query->whereHas('items', fn(Builder $q) => $q->whereColumn('returned_quantity', '<', 'quantity'));
            if ($this->status === 'overdue') {
                $query->where('expected_return_at', '<', now());
            } else {
                $query->where('expected_return_at', '>=', now());
                if ($this->status === 'borrowed') {
                    $query->whereDoesntHave('items', fn(Builder $q) => $q->where('returned_quantity', '>', 0));
                } else {
                    $query->whereHas('items', fn(Builder $q) => $q->where('returned_quantity', '>', 0));
                }
            }
        } elseif ($this->status === 'returned') {
            $query->has('items')->whereDoesntHave('items', fn(Builder $q) => $q->whereColumn('returned_quantity', '<', 'quantity'));
        }
        return $query->orderByDesc('borrowed_at')->orderByDesc('id')->paginate(15);
    }
}; ?>
<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading">
        <div>
            <h1>{{ __('Borrow Log') }}</h1>
            <p>{{ __('Follow every borrow record, from handover to the last unit returned.') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <flux:button icon="printer" :href="route('borrowing.report.print', ['search' => $search, 'status' => $status, 'borrower_type' => $borrowerType])" target="_blank">{{ __('Print Report') }}</flux:button>
            <flux:button icon="arrow-down-tray" :href="route('borrowing.report.export', ['search' => $search, 'status' => $status, 'borrower_type' => $borrowerType])">{{ __('Export to Excel') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('borrowing.create')" wire:navigate>{{ __('Record borrowing') }}</flux:button>
        </div>
    </header>
    <div class="eq-inventory">
        <div class="eq-stock-tabs overflow-x-auto" role="group" aria-label="{{ __('Borrowing status') }}">
            @foreach (['all' => 'All records', 'borrowed' => 'Borrowed', 'partially_returned' => 'Partially returned', 'overdue' => 'Overdue', 'returned' => 'Returned'] as $value => $label)
                <button type="button" wire:click="$set('status', '{{ $value }}')"
                    aria-pressed="{{ $status === $value ? 'true' : 'false' }}">{{ __($label) }}</button>
            @endforeach
        </div>
        <div class="eq-search-row">
            <div class="eq-search">
                <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass"
                    :aria-label="__('Search borrowing history')"
                    :placeholder="__('Find a borrower, identifier, equipment, or asset tag')" clearable />
            </div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="borrowerType" :aria-label="__('Borrower type')">
                    <option value="">{{ __('All borrower types') }}</option>
                    @foreach (BorrowerType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>
        <div class="eq-results-heading">
            <p><strong>{{ number_format($this->transactions->total()) }}</strong> {{ __('borrowing transactions') }}
            </p><button class="eq-text-action" type="button" wire:click="clearFilters">{{ __('Reset view') }}</button>
        </div>
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="clims-table eq-equipment-table w-full min-w-[850px] text-sm">
                <caption class="sr-only">{{ __('Borrowing transactions') }}</caption>
                <thead>
                    <tr>
                        <th>{{ __('Borrower / borrow') }}</th>
                        <th>{{ __('Equipment') }}</th>
                        <th>{{ __('Due back') }}</th>
                        <th>{{ __('Outstanding') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th scope="col" class="text-center!">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->transactions as $loan)
                        <tr wire:key="loan-{{ $loan->id }}">
                            <td><a class="eq-asset-name" href="{{ route('borrowing.show', $loan) }}"
                                    wire:navigate>{{ $loan->borrower_name }}</a>
                                <p class="eq-asset-meta">#{{ $loan->id }} · {{ $loan->borrower_type->label() }} ·
                                    {{ $loan->borrower_identifier }}</p>
                            </td>
                            <td><span class="eq-asset-name">{{ $loan->items->first()?->equipment->name }}</span>
                                @if ($loan->items->count() > 1)
                                    <p class="eq-asset-meta">+{{ $loan->items->count() - 1 }}
                                        {{ __('more equipment records') }}</p>
                                @endif
                            </td>
                            <td>{{ $loan->expected_return_at->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i A') }}
                            </td>
                            <td class="eq-numeric">{{ $loan->outstandingQuantity() }}</td>
                            <td>
                                <flux:badge size="sm"
                                    :color="$loan->isOverdue() ? 'red' : ($loan->outstandingQuantity() === 0 ? 'teal' : 'zinc')">
                                    {{ $loan->statusLabel() }}</flux:badge>
                            </td>
                            <td class="text-center">
                                <flux:button size="sm" variant="ghost" :href="route('borrowing.show', $loan)"
                                    wire:navigate>{{ __('View Details') }}</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="eq-empty-state">
                                    <h2>{{ __('No borrowing transactions found') }}</h2>
                                    <p>{{ __('Record a borrow transaction or adjust your search and filters.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="eq-pagination">{{ $this->transactions->links() }}</div>
    </div>
</section>
