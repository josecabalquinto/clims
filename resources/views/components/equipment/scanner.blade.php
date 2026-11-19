<flux:modal name="equipment-scanner" class="scan-modal" :closable="false" x-on:close.capture="closed" x-on:cancel.capture="closed" aria-labelledby="equipment-scan-heading">
    <div class="scan-sheet">
        <header class="scan-sheet-heading">
            <div><h2 id="equipment-scan-heading">{{ __('Scan equipment') }}</h2><p>{{ __('Add equipment to this borrowing form.') }}</p></div>
            <flux:modal.close><flux:button type="button" size="sm" variant="ghost" icon="x-mark" :aria-label="__('Close scanner')" /></flux:modal.close>
        </header>
        <div class="scan-mode-tabs" role="group" aria-label="{{ __('Scan method') }}">
            <button type="button" x-on:click="setMode('camera')" x-bind:aria-pressed="mode === 'camera'"><flux:icon.camera class="size-4" />{{ __('Camera') }}</button>
            <button type="button" x-on:click="setMode('entry')" x-bind:aria-pressed="mode === 'entry'"><flux:icon.qr-code class="size-4" />{{ __('Scanner / asset tag') }}</button>
        </div>

        <div x-show="mode === 'camera' && !result" class="scan-camera-section">
            <div class="scan-viewfinder" wire:ignore>
                <video x-ref="video" autoplay playsinline muted x-show="running || starting" aria-label="{{ __('Camera preview for equipment QR scanning') }}"></video>
                <div class="scan-frame" x-show="running" aria-hidden="true"></div>
                <div class="scan-camera-placeholder" x-show="!running">
                    <flux:icon.camera class="size-9" />
                    <strong x-text="starting ? 'Opening camera…' : 'Camera is off'"></strong>
                    <p>{{ __('Use the camera on this device to scan the equipment label.') }}</p>
                </div>
            </div>
            <p class="scan-camera-hint" x-show="running">{{ __('Keep one QR label inside the frame.') }}</p>
            <div class="scan-camera-actions" x-show="!result">
                <flux:button type="button" variant="primary" icon="camera" x-on:click="startCamera" x-bind:disabled="starting || busy" x-show="!running">{{ __('Start camera') }}</flux:button>
                <flux:button type="button" x-on:click="stopCamera" x-show="running">{{ __('Stop camera') }}</flux:button>
                <flux:button type="button" icon="arrow-path" x-on:click="switchCamera" x-show="running && cameras.length > 1" x-bind:disabled="starting">{{ __('Switch camera') }}</flux:button>
            </div>
        </div>

        <div x-show="mode === 'entry'" x-cloak class="scan-entry">
            <p>{{ __('Scan with a USB scanner, or type the equipment asset tag. Press Enter to add it.') }}</p>
            <flux:input x-ref="codeInput" x-model="code" x-on:keydown.enter.prevent.stop="submit(code)" :label="__('Equipment code or asset tag')" placeholder="LAB-MON-001" autocomplete="off" spellcheck="false" maxlength="255" />
            <flux:button type="button" variant="primary" x-on:click="submit(code)" x-bind:disabled="busy || !code.trim()">{{ __('Add equipment') }}</flux:button>
        </div>

        <div class="scan-feedback" x-show="error" x-cloak role="alert"><flux:icon.exclamation-circle class="size-5 shrink-0" /><p x-text="error"></p></div>
        <p class="scan-busy" x-show="busy" x-cloak role="status">{{ __('Checking equipment…') }}</p>

        <section class="scan-result" x-show="result" x-cloak aria-live="polite" x-bind:data-status="result?.status">
            <div class="scan-result-heading"><flux:icon.check-circle class="size-5" x-show="result?.status === 'added'" /><strong x-text="result?.status === 'added' ? 'Equipment added' : 'Check equipment'"></strong></div>
            <template x-if="result?.equipment"><div><h3 x-text="result.equipment.name"></h3><p class="scan-result-meta"><span x-text="result.equipment.asset_tag"></span><span x-text="result.equipment.available + ' available'"></span></p></div></template>
            <p x-text="result?.message"></p>
            <div class="scan-result-actions"><flux:button type="button" size="sm" variant="primary" x-on:click="scanAnother">{{ __('Scan another') }}</flux:button><flux:modal.close><flux:button type="button" size="sm">{{ __('Done') }}</flux:button></flux:modal.close></div>
        </section>

        <footer class="scan-sheet-footer"><p>{{ __('Scanning selects equipment. Stock is checked when you record the borrowing.') }}</p><flux:modal.close><button type="button" class="eq-text-action">{{ __('Continue manually') }}</button></flux:modal.close></footer>
    </div>
</flux:modal>
