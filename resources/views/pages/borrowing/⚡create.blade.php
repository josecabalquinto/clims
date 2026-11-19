<?php
use App\Enums\BorrowerType;
use App\Models\{BorrowTransaction, Equipment};
use App\Services\ManageBorrowing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Livewire\Attributes\{Computed, Title};
use Livewire\Component;

new #[Title('Record borrowing')] class extends Component {
    public string $borrower_name = '';
    public string $borrower_type = 'student';
    public string $borrower_identifier = '';
    public string $grade_level = '';
    public string $section = '';
    public string $borrowed_at = '';
    public string $expected_return_at = '';
    public string $remarks = '';
    public array $items = [['equipment_id' => '', 'quantity' => '1']];
    public function boot(): void {
        Auth::user()?->refresh();
        Gate::authorize('create', BorrowTransaction::class);
    }
    public function mount(): void {
        $this->borrowed_at = now()->setTimezone(config('clims.display_timezone'))->format('Y-m-d\TH:i');
        $this->expected_return_at = now()->addDay()->setTimezone(config('clims.display_timezone'))->format('Y-m-d\TH:i');
    }
    public function addItem(): void {
        if (count($this->items) < 50) { $this->items[] = ['equipment_id' => '', 'quantity' => '1']; }
    }
    public function removeItem(int $index): void {
        if (! array_key_exists($index, $this->items)) { return; }

        if (count($this->items) === 1) {
            $this->items = [['equipment_id' => '', 'quantity' => '1']];
        } else {
            array_splice($this->items, $index, 1);
        }

        $this->resetValidation();
        unset($this->equipment);
    }

    /** @return array<string, mixed> */
    public function addScannedEquipment(string $code): array
    {
        Auth::user()?->refresh();
        Gate::authorize('create', BorrowTransaction::class);
        $code = trim($code);
        if ($code === '' || mb_strlen($code) > 255) {
            return ['status' => 'invalid', 'message' => __('Scan an equipment QR label or enter its asset tag.')];
        }
        if (str_starts_with($code, Equipment::QR_PREFIX)) {
            $id = substr($code, strlen(Equipment::QR_PREFIX));
            if (! ctype_digit($id) || ! filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                return ['status' => 'invalid', 'message' => __('This QR label is not valid. Try the asset tag instead.')];
            }
            $record = Equipment::find((int) $id);
        } else {
            if (str_contains($code, '://') || str_starts_with($code, 'CLIMS:')) {
                return ['status' => 'invalid', 'message' => __('Use a CLIMS equipment label or enter the asset tag. Website links are not equipment labels.')];
            }
            $record = Equipment::where('asset_tag', mb_strtoupper($code))->first();
        }
        if (! $record) {
            return ['status' => 'not_found', 'message' => __('No equipment matches this code. Check the label or select the equipment manually.')];
        }
        Gate::authorize('view', $record);
        $equipment = ['id' => $record->id, 'name' => $record->name, 'asset_tag' => $record->asset_tag, 'available' => $record->available_quantity];
        $empty = null;
        foreach ($this->items as $index => $line) {
            if (is_array($line) && (string) ($line['equipment_id'] ?? '') === (string) $record->id) {
                return ['status' => 'duplicate', 'message' => __('Already in your borrowing list. Change its quantity in the form if needed.'), 'equipment' => $equipment];
            }
            if (is_array($line) && ($line['equipment_id'] ?? '') === '' && $empty === null) {
                $empty = $index;
            }
        }
        if ($record->available_quantity < 1) {
            return ['status' => 'unavailable', 'message' => __('No usable units are available for this equipment.'), 'equipment' => $equipment];
        }
        if ($empty === null && count($this->items) >= 50) {
            return ['status' => 'full', 'message' => __('This borrow record already has 50 equipment records. Remove an item before adding another.')];
        }
        $index = $empty ?? count($this->items);
        $this->items[$index] = ['equipment_id' => (string) $record->id, 'quantity' => '1'];
        $this->resetValidation(['items', "items.{$index}.equipment_id", "items.{$index}.quantity"]);
        unset($this->equipment);

        return ['status' => 'added', 'message' => __('Added to your borrowing list. Review the quantity before saving.'), 'equipment' => $equipment];
    }
    #[Computed]
    public function equipment(): Collection {
        return Equipment::where(fn ($q) => $q->where('available_quantity', '>', 0)
            ->orWhereIn('id', array_column($this->items, 'equipment_id')))->orderBy('name')->get();
    }
    public function save(ManageBorrowing $service): void {
        $borrow = $service->create(Auth::user(), $this->only(['borrower_name', 'borrower_type', 'borrower_identifier', 'grade_level', 'section', 'borrowed_at', 'expected_return_at', 'remarks', 'items']));
        session()->flash('borrowing-status', 'Borrowing recorded successfully.');
        $this->redirectRoute('borrowing.show', $borrow, navigate: true);
    }
}; ?>
<section class="eq-module flex min-w-0 flex-col gap-6" x-data="equipmentScanner">
    <header class="eq-page-heading"><div><h1>{{ __('Record borrowing') }}</h1><p>{{ __('One borrower, one handover. Add every equipment item below.') }}</p></div><flux:button variant="ghost" :href="route('borrowing.index')" wire:navigate>{{ __('Back to history') }}</flux:button></header>
    <form wire:submit="save" class="eq-editor">
        <div class="eq-editor-grid">
            <div class="eq-editor-main space-y-8">
                <section class="eq-fields"><h2 class="text-lg font-semibold text-brand-deep">{{ __('Borrower details') }}</h2>
                    <flux:input wire:model="borrower_name" :label="__('Full name')" maxlength="255" required />
                    <flux:select wire:model.live="borrower_type" :label="__('Borrower type')">@foreach (BorrowerType::cases() as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</flux:select>
                    @if ($borrower_type === BorrowerType::Student->value)
                        <div class="eq-field-pair">
                            <flux:input wire:model="borrower_identifier" :label="__('LRN')" inputmode="numeric" pattern="[0-9]{12}" minlength="12" maxlength="12" required />
                            <flux:input wire:model="grade_level" :label="__('Grade level')" maxlength="50" required />
                        </div>
                        <flux:input wire:model="section" :label="__('Section')" maxlength="100" required />
                    @endif
                </section>
                <section class="eq-fields"><div class="scan-equipment-heading"><div><h2 class="text-lg font-semibold text-brand-deep">{{ __('Equipment to lend') }}</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Select equipment below, or scan its QR label.') }}</p></div><div class="scan-equipment-actions"><flux:button type="button" size="sm" icon="qr-code" x-on:click="open">{{ __('Scan QR') }}</flux:button><flux:button type="button" size="sm" icon="plus" wire:click="addItem">{{ __('Add item') }}</flux:button></div></div>
                    <flux:error name="items" />
                    <div class="eq-loan-list">
                        @foreach ($items as $index => $line)
                            <div wire:key="loan-line-{{ $index }}" class="eq-loan-line">
                                <span class="eq-loan-index" aria-hidden="true">{{ $index + 1 }}</span>
                                <div class="eq-loan-equipment">
                                    <flux:select wire:model.live="items.{{ $index }}.equipment_id" :label="__('Equipment item :number', ['number' => $index + 1])" required><option value="">{{ __('Choose equipment') }}</option>@foreach ($this->equipment as $choice)<option value="{{ $choice->id }}">{{ $choice->asset_tag }} · {{ $choice->name }} · {{ $choice->available_quantity }} {{ __('available') }}</option>@endforeach</flux:select>
                                </div>
                                <div class="eq-loan-quantity">
                                    <flux:input wire:model="items.{{ $index }}.quantity" type="number" min="1" step="1" :label="__('Units')" required />
                                </div>
                                <flux:button class="eq-loan-remove" type="button" size="sm" variant="ghost" icon="trash" wire:click="removeItem({{ $index }})" wire:loading.attr="disabled" wire:target="removeItem({{ $index }})" :aria-label="__('Remove equipment item :number', ['number' => $index + 1])">{{ __('Remove') }}</flux:button>
                            </div>
                        @endforeach
                    </div>
                </section>
                <flux:textarea wire:model="remarks" :label="__('Remarks (optional)')" rows="3" maxlength="5000" />
            </div>
            <aside class="eq-stock-rail space-y-5"><h2 class="text-lg font-semibold text-brand-deep">{{ __('Borrow schedule') }}</h2><p class="text-sm text-zinc-500">{{ __('Set a clear deadline for this handover. All times use') }} {{ config('clims.display_timezone') }}.</p>
                <flux:input wire:model="borrowed_at" type="datetime-local" :label="__('Borrowed at')" required />
                <flux:input wire:model="expected_return_at" type="datetime-local" :label="__('Expected return')" required />
                <p class="eq-stock-note">{{ __('Availability is checked again when you save. Only usable, available units can be borrowed.') }}</p>
            </aside>
        </div>
        <footer class="eq-editor-actions"><flux:button :href="route('borrowing.index')" wire:navigate>{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Record borrowing') }}</flux:button></footer>
    </form>
    <x-equipment.scanner />
</section>

