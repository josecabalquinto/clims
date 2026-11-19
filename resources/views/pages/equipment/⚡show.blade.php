<?php

use App\Models\Equipment;
use App\Enums\EquipmentCondition;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Equipment details')] class extends Component {
    #[Locked]
    public int $equipmentId;

    public function boot(): void
    {
        Gate::authorize('viewAny', Equipment::class);
    }

    public function mount(Equipment $equipment): void
    {
        Gate::authorize('view', $equipment);
        $this->equipmentId = $equipment->id;
    }

    #[Computed]
    public function record(): Equipment
    {
        $record = Equipment::with(['category', 'location'])->findOrFail($this->equipmentId);
        Gate::authorize('view', $record);

        return $record;
    }
}; ?>

@php
    $record = $this->record;
    $availabilityPercent = $record->quantity > 0 ? (int) round(($record->available_quantity / $record->quantity) * 100) : 0;
    $conditionLabel = __(EquipmentCondition::tryFrom($record->condition)?->label() ?? ucfirst($record->condition));
@endphp

<section class="eq-module eq-detail-page" x-data="equipmentQrLabel(@js(['payload' => $record->qrPayload(), 'name' => $record->name, 'assetTag' => $record->asset_tag]))">
    <x-equipment.navigation />

    <header class="eq-detail-hero">
        <div class="eq-detail-identity">
            <span class="eq-detail-icon" aria-hidden="true"><flux:icon.computer-desktop /></span>
            <div class="min-w-0">
                <div class="eq-detail-tag"><flux:icon.tag class="size-3.5" />{{ $record->asset_tag }}</div>
                <h1>{{ $record->name }}</h1>
                <div class="eq-detail-meta">
                    <span><flux:icon.squares-2x2 class="size-4" />{{ $record->category->name }}</span>
                    <span><flux:icon.map-pin class="size-4" />{{ $record->location->name }}</span>
                </div>
            </div>
        </div>
        <div class="eq-detail-actions">
            <flux:button variant="primary" icon="qr-code" x-on:click="open">{{ __('Open QR label') }}</flux:button>
            <flux:button icon="pencil-square" :href="route('equipment.edit', $equipmentId)" wire:navigate>{{ __('Edit equipment') }}</flux:button>
        </div>
    </header>

    <dl class="eq-stock-ledger" aria-label="{{ __('Equipment quantities') }}">
        @foreach ([
            ['label' => __('Total units'), 'value' => $record->quantity, 'state' => 'total'],
            ['label' => __('Available now'), 'value' => $record->available_quantity, 'state' => 'available'],
            ['label' => __('Currently borrowed'), 'value' => $record->outstandingQuantity(), 'state' => 'borrowed'],
            ['label' => __('Defective on hand'), 'value' => $record->defective_quantity, 'state' => 'defective'],
        ] as $stock)
            <div wire:key="stock-{{ $stock['state'] }}" data-state="{{ $stock['state'] }}">
                <dt><span aria-hidden="true"></span>{{ $stock['label'] }}</dt>
                <dd>{{ number_format($stock['value']) }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="eq-detail-layout">
        <article class="eq-detail-record">
            <div class="eq-detail-section-heading">
                <div><h2>{{ __('Asset record') }}</h2><p>{{ __('Identification, assignment, and lifecycle information.') }}</p></div>
            </div>

            <dl class="eq-detail-fields">
                <div><dt>{{ __('Category') }}</dt><dd>{{ $record->category->name }}{{ $record->category->is_active ? '' : __(' (inactive)') }}</dd></div>
                <div><dt>{{ __('Assigned location') }}</dt><dd>{{ $record->location->name }}{{ $record->location->is_active ? '' : __(' (inactive)') }}</dd></div>
                <div><dt>{{ __('Serial number') }}</dt><dd>{{ $record->serial_number ?? __('Not recorded') }}</dd></div>
                <div><dt>{{ __('Tracking method') }}</dt><dd>{{ $record->serial_number ? __('Individually tracked') : __('Quantity tracked') }}</dd></div>
                <div><dt>{{ __('Date acquired') }}</dt><dd>{{ $record->date_acquired?->format('M j, Y') ?? __('Not recorded') }}</dd></div>
                <div><dt>{{ __('Last updated') }}</dt><dd>{{ $record->updated_at?->timezone(config('clims.display_timezone'))->format('M j, Y, g:i a') }}</dd></div>
            </dl>

            <div class="eq-detail-notes">
                <section><h3>{{ __('Description') }}</h3><p>{{ $record->description ?? __('No description recorded.') }}</p></section>
                <section><h3>{{ __('Remarks') }}</h3><p>{{ $record->remarks ?? __('No remarks recorded.') }}</p></section>
            </div>
        </article>

        <aside class="eq-detail-status" aria-label="{{ __('Equipment status') }}">
            <div class="eq-detail-section-heading"><div><h2>{{ __('Current status') }}</h2><p>{{ __('A quick readiness check for laboratory use.') }}</p></div></div>
            <div class="eq-readiness-number"><strong>{{ $availabilityPercent }}%</strong><span>{{ __('of units available') }}</span></div>
            <div class="eq-readiness-track" role="progressbar" aria-label="{{ __('Available units') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $availabilityPercent }}"><span style="width: {{ $availabilityPercent }}%"></span></div>
            <p class="eq-readiness-copy">{{ trans_choice(':count unit is ready to borrow|:count units are ready to borrow', $record->available_quantity, ['count' => number_format($record->available_quantity)]) }}</p>

            <dl class="eq-status-list">
                <div><dt>{{ __('Condition grade') }}</dt><dd><span class="eq-condition-pill" data-condition="{{ $record->condition }}">{{ $conditionLabel }}</span></dd></div>
                <div><dt>{{ __('Asset tag') }}</dt><dd>{{ $record->asset_tag }}</dd></div>
                <div><dt>{{ __('QR label') }}</dt><dd><button type="button" x-on:click="open">{{ __('Print or download') }}</button></dd></div>
            </dl>
        </aside>
    </div>

    <x-equipment.qr-label />
</section>
