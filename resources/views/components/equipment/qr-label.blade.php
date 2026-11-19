<flux:modal name="equipment-qr-label" class="scan-modal" aria-labelledby="qr-label-heading">
    <div class="scan-sheet">
        <header class="scan-sheet-heading"><div><h2 id="qr-label-heading">{{ __('Equipment QR label') }}</h2><p>{{ __('Print this label and attach it to the equipment.') }}</p></div></header>
        <div class="scan-label-preview">
            <strong>{{ config('app.name') }}</strong>
            <p>{{ $this->record->name }}</p>
            <img x-show="image" x-bind:src="image || undefined" alt="{{ __('Equipment QR code') }}" width="240" height="240" />
            <p x-show="loading" aria-live="polite">{{ __('Generating QR code…') }}</p>
            <strong>{{ $this->record->asset_tag }}</strong>
        </div>
        <p class="scan-camera-hint">{{ __('Scan this label when borrowing. For batch equipment, choose the quantity in the form.') }}</p>
        <p class="scan-feedback" x-show="error" x-cloak x-text="error" role="alert"></p>
        <div class="scan-label-actions"><flux:button type="button" variant="primary" icon="printer" x-on:click="print" x-bind:disabled="!image">{{ __('Print label') }}</flux:button><flux:button type="button" icon="arrow-down-tray" x-on:click="download" x-bind:disabled="!image">{{ __('Download QR') }}</flux:button></div>
    </div>
</flux:modal>
