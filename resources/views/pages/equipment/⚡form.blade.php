<?php

use App\Enums\EquipmentCondition;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Services\ManageEquipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $equipmentId = null;

    public string $asset_tag = '';
    public string $name = '';
    public string $category_id = '';
    public string $description = '';
    public string $serial_number = '';
    public string $quantity = '1';
    public string $defective_quantity = '0';
    public string $condition = 'good';
    public string $date_acquired = '';
    public string $remarks = '';

    public function boot(): void
    {
        if ($this->equipmentId) {
            Gate::authorize('update', Equipment::findOrFail($this->equipmentId));
        } else {
            Gate::authorize('create', Equipment::class);
        }
    }

    public function mount(?Equipment $equipment = null): void
    {
        if ($equipment?->exists) {
            Gate::authorize('update', $equipment);
            $this->equipmentId = $equipment->id;
            foreach (['asset_tag', 'name', 'category_id', 'description', 'serial_number', 'quantity', 'defective_quantity', 'condition', 'remarks'] as $field) {
                $this->{$field} = (string) ($equipment->{$field} ?? '');
            }
            $this->date_acquired = $equipment->date_acquired?->toDateString() ?? '';
        }
    }

    #[Computed]
    public function currentEquipment(): ?Equipment
    {
        return $this->equipmentId ? Equipment::findOrFail($this->equipmentId) : null;
    }

    #[Computed]
    public function categories(): Collection
    {
        $current = $this->currentEquipment?->category_id;

        return EquipmentCategory::where(fn (Builder $query) => $query->where('is_active', true)
            ->when($current, fn (Builder $query) => $query->orWhereKey($current)))
            ->orderBy('name')->get();
    }

    public function save(ManageEquipment $service): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof \App\Models\User, 403);
        $record = $service->save($actor, $this->only([
            'asset_tag', 'name', 'category_id', 'description', 'serial_number',
            'quantity', 'defective_quantity', 'condition', 'date_acquired', 'remarks',
        ]), $this->currentEquipment);
        session()->flash('equipment-status', $this->equipmentId ? __('Equipment updated.') : __('Equipment registered.'));
        $this->redirectRoute('equipment.show', ['equipment' => $record->id], navigate: true);
    }

    public function render(): View
    {
        return $this->view()->title($this->equipmentId ? __('Edit equipment') : __('Register equipment'));
    }
}; ?>

<section class="eq-module flex min-w-0 flex-col gap-6">
    <header class="eq-page-heading">
        <div>
            <h1>{{ $equipmentId ? __('Edit equipment') : __('Register equipment') }}</h1>
            <p>{{ $equipmentId ? __('Keep this equipment record accurate and ready for daily use.') : __('Give each asset a clear identity and a place in your laboratory.') }}</p>
        </div>
        <flux:button variant="ghost" icon="arrow-left" :href="route('equipment.index')" wire:navigate>{{ __('Back to equipment') }}</flux:button>
    </header>

    <x-equipment.navigation />

    @if ($this->categories->isEmpty())
        <div role="status" class="eq-setup-notice">
            <flux:icon.tag class="size-5 shrink-0" />
            <p>{{ __('Add at least one active category before registering equipment. Use the category management link above to get started.') }}</p>
        </div>
    @endif

    <form
        wire:submit="save"
        class="eq-editor"
        x-data="{
            borrowed: @js($this->currentEquipment?->outstandingQuantity() ?? 0),
            remaining(total, defective) {
                if (String(total).trim() === '' || String(defective).trim() === '') return null;
                const t = Number(total), d = Number(defective);
                return Number.isSafeInteger(t) && t > 0 && t <= 4294967295 && Number.isSafeInteger(d) && d >= 0 && d <= 4294967295 ? t - d - this.borrowed : null;
            }
        }"
    >
        <div class="eq-editor-grid">
            <div class="eq-editor-main">
                <section class="eq-form-section" aria-labelledby="equipment-identity">
                    <div class="eq-form-section-heading">
                        <span class="eq-section-icon"><flux:icon.computer-desktop class="size-5" /></span>
                        <div>
                            <h2 id="equipment-identity">{{ __('Equipment identity') }}</h2>
                            <p>{{ __('The details that distinguish this equipment from the rest.') }}</p>
                        </div>
                    </div>
                    <div class="eq-fields">
                        <flux:input wire:model="name" :label="__('Equipment name')" :placeholder="__('e.g. Computer laboratory monitor')" maxlength="255" required />
                        <div class="eq-field-pair">
                            <flux:input wire:model="asset_tag" :label="__('Asset tag')" :description="__('Unique inventory ID, saved in uppercase.')" placeholder="LAB-MON-001" maxlength="100" required />
                            <flux:input wire:model="serial_number" :label="__('Serial number (optional)')" :description="__('For a single item with quantity 1.')" :placeholder="__('Manufacturer serial number')" maxlength="255" />
                        </div>
                    </div>
                </section>

                <section class="eq-form-section" aria-labelledby="equipment-assignment">
                    <div class="eq-form-section-heading">
                        <span class="eq-section-icon"><flux:icon.map-pin class="size-5" /></span>
                        <div>
                            <h2 id="equipment-assignment">{{ __('Equipment category') }}</h2>
                            <p>{{ __('All equipment is assigned to Computer Lab 2.') }}</p>
                        </div>
                    </div>
                    <div class="eq-fields">
                        <flux:select wire:model="category_id" :label="__('Category')" required>
                            <option value="">{{ __('Choose a category') }}</option>
                            @foreach ($this->categories as $choice)
                                <option value="{{ $choice->id }}" wire:key="category-choice-{{ $choice->id }}">{{ $choice->name }}{{ $choice->is_active ? '' : __(' (inactive, current assignment)') }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </section>

                <details class="eq-additional-details" @if ($date_acquired || $description || $remarks || $errors->hasAny(['date_acquired', 'description', 'remarks'])) open @endif>
                    <summary>
                        <span><strong>{{ __('Acquisition and notes') }}</strong><small>{{ __('Optional details for a more complete record') }}</small></span>
                        <flux:icon.chevron-down class="eq-disclosure-chevron size-4" />
                    </summary>
                    <div class="eq-fields">
                        <div class="max-w-xs"><flux:input wire:model="date_acquired" :label="__('Date acquired (optional)')" type="date" /></div>
                        <flux:textarea wire:model="description" :label="__('Description (optional)')" :placeholder="__('Model, specifications, or distinguishing features')" rows="3" maxlength="5000" />
                        <flux:textarea wire:model="remarks" :label="__('Remarks (optional)')" :placeholder="__('Any other details laboratory personnel should know')" rows="3" maxlength="5000" />
                    </div>
                </details>
            </div>

            <aside class="eq-stock-rail" aria-labelledby="equipment-stock">
                <div class="eq-stock-heading">
                    <h2 id="equipment-stock">{{ __('Stock balance') }}</h2>
                    <flux:icon.archive-box class="size-5" />
                </div>

                <div class="eq-stock-preview" aria-live="polite" aria-atomic="true">
                    <span class="eq-stock-preview-number" x-bind:data-wide="String(Math.abs(remaining($wire.quantity, $wire.defective_quantity) ?? 0)).length > 6" x-bind:data-invalid="remaining($wire.quantity, $wire.defective_quantity) !== null && remaining($wire.quantity, $wire.defective_quantity) < 0" x-text="remaining($wire.quantity, $wire.defective_quantity) === null ? '--' : new Intl.NumberFormat().format(remaining($wire.quantity, $wire.defective_quantity))">{{ $this->currentEquipment?->available_quantity ?? 1 }}</span>
                    <p>{{ __('units available to borrow') }}</p>
                </div>

                <div class="eq-stock-inputs">
                    <flux:input wire:model="quantity" :label="__('Total quantity')" type="number" min="1" max="4294967295" step="1" required />
                    <div class="eq-borrowed-line">
                        <span>{{ __('Currently borrowed') }}</span>
                        <strong>{{ $this->currentEquipment?->outstandingQuantity() ?? 0 }}</strong>
                    </div>
                    <flux:input wire:model="defective_quantity" :label="__('Defective units on hand')" type="number" min="0" max="4294967295" step="1" required />
                </div>
                <p class="eq-stock-note">{{ __('Borrowed and defective units are excluded from available stock.') }}</p>
                <p class="eq-stock-warning" x-cloak x-show="remaining($wire.quantity, $wire.defective_quantity) !== null && remaining($wire.quantity, $wire.defective_quantity) < 0">{{ __('Total stock must cover borrowed and defective units.') }}</p>

                <div class="eq-condition-picker">
                    <flux:radio.group wire:model="condition" variant="segmented" :label="__('Condition grade')">
                        @foreach (EquipmentCondition::cases() as $choice)
                            <flux:radio :value="$choice->value" wire:key="condition-choice-{{ $choice->value }}">{{ __($choice->label()) }}</flux:radio>
                        @endforeach
                    </flux:radio.group>
                    <p>{{ __('Describe the general condition. Record unusable units in the defective count above.') }}</p>
                </div>
            </aside>
        </div>

        <footer class="eq-editor-actions">
            <flux:button variant="ghost" :href="$equipmentId ? route('equipment.show', $equipmentId) : route('equipment.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save" :disabled="$this->categories->isEmpty()">{{ $equipmentId ? __('Save changes') : __('Register equipment') }}</flux:button>
        </footer>
    </form>
</section>
