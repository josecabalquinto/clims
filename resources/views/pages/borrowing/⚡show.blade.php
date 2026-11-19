<?php
use App\Models\BorrowTransaction;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\{Computed, Locked, Title};
use Livewire\Component;

new #[Title('Borrowing details')] class extends Component {
    #[Locked]
    public int $transactionId;
    public function boot(): void { Gate::authorize('viewAny', BorrowTransaction::class); }
    public function mount(BorrowTransaction $transaction): void {
        Gate::authorize('view', $transaction);
        $this->transactionId = $transaction->id;
    }
    #[Computed]
    public function record(): BorrowTransaction {
        $record = BorrowTransaction::with(['recordedBy', 'items.equipment', 'items.returns.recordedBy'])->findOrFail($this->transactionId);
        Gate::authorize('view', $record);
        return $record;
    }
}; ?>
<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading"><div><p>{{ __('BORROW') }} #{{ $transactionId }}</p><h1>{{ $this->record->borrower_name }}</h1><p>{{ $this->record->borrower_type->label() }}@if($this->record->borrower_type === \App\Enums\BorrowerType::Student) · {{ $this->record->borrower_identifier }} · {{ $this->record->grade_level }} · {{ $this->record->section }}@endif</p></div><div class="flex flex-wrap gap-3"><flux:button :href="route('borrowing.index')" wire:navigate>{{ __('Back to Borrow Log') }}</flux:button>@if ($this->record->outstandingQuantity()>0)<flux:button variant="primary" :href="route('borrowing.return', $transactionId)" wire:navigate>{{ __('Record return') }}</flux:button>@endif</div></header>
    <div class="eq-editor p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><flux:badge :color="$this->record->isOverdue() ? 'red' : 'teal'">{{ $this->record->statusLabel() }}</flux:badge><p class="text-sm text-zinc-500">{{ $this->record->outstandingQuantity() }} {{ __('units outstanding') }}</p></div>
        <dl class="grid gap-6 sm:grid-cols-3">
            <div><dt class="text-xs text-zinc-500">{{ __('Borrowed at') }}</dt><dd class="mt-2 text-sm font-medium">{{ $this->record->borrowed_at->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i A') }}</dd></div>
            <div><dt class="text-xs text-zinc-500">{{ __('Expected return') }}</dt><dd class="mt-2 text-sm font-medium">{{ $this->record->expected_return_at->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i A') }}</dd></div>
            <div><dt class="text-xs text-zinc-500">{{ __('Recorded by') }}</dt><dd class="mt-2 text-sm font-medium">{{ $this->record->recordedBy->name }}</dd></div>
            @if ($this->record->returned_at)<div><dt class="text-xs text-zinc-500">{{ __('Completed at') }}</dt><dd class="mt-2 text-sm">{{ $this->record->returned_at->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i A') }}</dd></div>@endif
        </dl>
        @if ($this->record->remarks)<p class="mt-6 whitespace-pre-wrap break-words border-t border-line pt-5 text-sm">{{ $this->record->remarks }}</p>@endif
        <p class="mt-4 text-xs text-zinc-500">{{ __('Times shown in') }} {{ config('clims.display_timezone') }}</p>
    </div>
    <div class="eq-inventory overflow-x-auto"><table class="clims-table eq-equipment-table w-full min-w-[600px] text-sm"><caption class="p-5 text-left font-semibold text-brand-deep">{{ __('Equipment on this borrow record') }}</caption><thead><tr><th>{{ __('Equipment') }}</th><th>{{ __('Borrowed') }}</th><th>{{ __('Returned') }}</th><th>{{ __('Outstanding') }}</th></tr></thead><tbody>@foreach ($this->record->items as $item)<tr wire:key="borrow-item-{{ $item->id }}"><td><a class="eq-asset-name" href="{{ route('equipment.show', $item->equipment) }}" wire:navigate>{{ $item->equipment->name }}</a><p class="eq-asset-meta">{{ $item->equipment->asset_tag }}</p></td><td>{{ $item->quantity }}</td><td>{{ $item->returned_quantity }}</td><td>{{ $item->outstandingQuantity() }}</td></tr>@endforeach</tbody></table></div>
    <div class="eq-editor p-6"><h2 class="mb-5 text-lg font-semibold text-brand-deep">{{ __('Return history') }}</h2>
        @forelse ($this->record->items->flatMap(fn ($item) => $item->returns)->sortByDesc('returned_at') as $event)
            <article class="border-t border-line py-4" wire:key="return-event-{{ $event->id }}"><div class="flex flex-wrap justify-between gap-3"><p class="text-sm font-semibold">{{ $this->record->items->firstWhere('id', $event->borrow_item_id)->equipment->name }} · {{ $event->quantity }} {{ __('returned') }}</p><time class="text-xs text-zinc-500">{{ $event->returned_at->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i A') }}</time></div><p class="mt-2 text-xs text-zinc-500">{{ $event->defective_quantity }} {{ __('defective units received') }} · {{ __('Processed by') }} {{ $event->recordedBy->name }}</p>@if ($event->remarks)<p class="mt-3 whitespace-pre-wrap break-words text-sm">{{ $event->remarks }}</p>@endif</article>
        @empty
            <p class="text-sm text-zinc-500">{{ __('No returns recorded yet.') }}</p>
        @endforelse
    </div>
</section>

