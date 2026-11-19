<?php
use App\Models\BorrowTransaction;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\{Computed, Locked, Title};
use Livewire\Component;

use App\Services\ManageBorrowing;
use Illuminate\Support\Facades\Auth;

new #[Title('Record return')] class extends Component {
    #[Locked]
    public int $transactionId;
    public string $returned_at = '';
    public string $remarks = '';
    public array $items = [];
    public function boot(): void { Gate::authorize('viewAny', BorrowTransaction::class); }
    public function mount(BorrowTransaction $transaction): void {
        Gate::authorize('returnEquipment', $transaction);
        $this->transactionId = $transaction->id;
        $this->returned_at = now()->setTimezone(config('clims.display_timezone'))->format('Y-m-d\TH:i');
        foreach ($transaction->items as $item) {
            if ($item->outstandingQuantity()>0) {
                $this->items[] = ['borrow_item_id'=>$item->id, 'quantity'=>'0', 'defective_quantity'=>'0'];
            }
        }
    }
    #[Computed]
    public function record(): BorrowTransaction {
        $record = BorrowTransaction::with('items.equipment')->findOrFail($this->transactionId);
        Gate::authorize('returnEquipment', $record);
        return $record;
    }
    public function save(ManageBorrowing $service): void {
        $service->returnEquipment(Auth::user(), $this->record, $this->only(['returned_at','remarks','items']));
        session()->flash('borrowing-status', 'Equipment return recorded successfully.');
        $this->redirectRoute('borrowing.show', $this->transactionId, navigate: true);
    }
}; ?>
<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading"><div><h1>{{ __('Record return') }}</h1><p>{{ __('Borrow') }} #{{ $transactionId }} · {{ $this->record->borrower_name }}</p></div><flux:button :href="route('borrowing.show', $transactionId)" wire:navigate>{{ __('Back to details') }}</flux:button></header>
    @if ($this->record->outstandingQuantity()===0)
        <div class="eq-editor p-8"><h2 class="text-lg font-semibold text-brand-deep">{{ __('All equipment has been returned.') }}</h2></div>
    @else
    <form wire:submit="save" class="eq-editor">
        <div class="eq-editor-grid">
            <div class="eq-editor-main space-y-5">
                <h2 class="text-lg font-semibold text-brand-deep">{{ __('What came back?') }}</h2><p class="text-sm text-zinc-500">{{ __('Enter units received now. Leave zero for items still with the borrower. Defective units are included in the returned quantity.') }}</p>
                <flux:error name="items" />
                @foreach ($items as $index=>$line)
                    @php($item = $this->record->items->firstWhere('id', (int) ($line['borrow_item_id'] ?? 0)))
                    <div class="rounded-xl border border-line p-5 space-y-4" wire:key="return-line-{{ $index }}">
                        <div><h3 class="font-semibold text-brand-deep">{{ $item?->equipment->name ?? __('Unknown borrowed item') }}</h3><p class="eq-asset-meta">{{ $item?->equipment->asset_tag }} · {{ $item?->outstandingQuantity() ?? 0 }} {{ __('outstanding') }}</p></div>
                        <flux:error name="items.{{ $index }}.borrow_item_id" />
                        <div class="eq-field-pair"><flux:input wire:model="items.{{ $index }}.quantity" type="number" min="0" step="1" :label="__('Units returned now')" required /><flux:input wire:model="items.{{ $index }}.defective_quantity" type="number" min="0" step="1" :label="__('Of these, defective')" required /></div>
                    </div>
                @endforeach
                <flux:textarea wire:model="remarks" :label="__('Return notes (optional)')" rows="3" maxlength="5000" />
            </div>
            <aside class="eq-stock-rail space-y-5"><h2 class="text-lg font-semibold text-brand-deep">{{ __('Return details') }}</h2><flux:input wire:model="returned_at" type="datetime-local" :label="__('Returned at')" required /><p class="text-xs text-zinc-500">{{ config('clims.display_timezone') }}</p><p class="eq-stock-note">{{ __('Usable returns become available immediately. Defective returns remain on hand but cannot be borrowed.') }}</p></aside>
        </div>
        <footer class="eq-editor-actions"><flux:button :href="route('borrowing.show', $transactionId)" wire:navigate>{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Confirm return') }}</flux:button></footer>
    </form>
    @endif
</section>

