<?php

use App\Models\Equipment;
use App\Models\User;
use Livewire\Livewire;

test('camera preview is rendered before mobile camera playback starts', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('borrowing.create'))
        ->assertOk()
        ->assertSee('autoplay playsinline muted x-show="running || starting"', false);
});

test('scanning adds available equipment while preserving manual entries and borrower fields', function () {
    $manual = Equipment::factory()->create(['quantity' => 8, 'available_quantity' => 8]);
    $scanned = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 5]);
    $this->actingAs(User::factory()->create());
    $page = Livewire::test('pages::borrowing.create')->set('borrower_name', 'Borrower retained')
        ->set('items', [['equipment_id' => (string) $manual->id, 'quantity' => '3']])
        ->call('addScannedEquipment', $scanned->qrPayload())
        ->assertSet('borrower_name', 'Borrower retained')
        ->assertSet('items', [['equipment_id' => (string) $manual->id, 'quantity' => '3'], ['equipment_id' => (string) $scanned->id, 'quantity' => '1']]);
    $page->assertReturned([
        'status' => 'added', 'message' => 'Added to your borrowing list. Review the quantity before saving.',
        'equipment' => ['id' => $scanned->id, 'name' => $scanned->name, 'asset_tag' => $scanned->asset_tag, 'available' => 5],
    ]);
    expect($scanned->fresh()->available_quantity)->toBe(5);
    $this->assertDatabaseCount('borrow_transactions', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('asset tag entry fills an empty row with a quantity of one', function () {
    $equipment = Equipment::factory()->create(['asset_tag' => 'LAB-MON-001']);
    $this->actingAs(User::factory()->create());
    Livewire::test('pages::borrowing.create')->call('addScannedEquipment', '  lab-mon-001  ')
        ->assertSet('items', [['equipment_id' => (string) $equipment->id, 'quantity' => '1']]);
});

test('duplicate scans do not add rows or increment an existing quantity', function () {
    $equipment = Equipment::factory()->create(['quantity' => 8, 'available_quantity' => 8]);
    $this->actingAs(User::factory()->create());
    Livewire::test('pages::borrowing.create')->set('items', [['equipment_id' => (string) $equipment->id, 'quantity' => '3']])
        ->call('addScannedEquipment', $equipment->qrPayload())->assertCount('items', 1)->assertSet('items.0.quantity', '3')
        ->assertReturned(['status' => 'duplicate', 'message' => 'Already in your borrowing list. Change its quantity in the form if needed.', 'equipment' => ['id' => $equipment->id, 'name' => $equipment->name, 'asset_tag' => $equipment->asset_tag, 'available' => 8]]);
});

test('unavailable equipment is identified but never selected', function () {
    $equipment = Equipment::factory()->create(['quantity' => 2, 'available_quantity' => 0, 'defective_quantity' => 2]);
    $this->actingAs(User::factory()->create());
    Livewire::test('pages::borrowing.create')->call('addScannedEquipment', $equipment->qrPayload())
        ->assertSet('items.0.equipment_id', '')
        ->assertReturned(['status' => 'unavailable', 'message' => 'No usable units are available for this equipment.', 'equipment' => ['id' => $equipment->id, 'name' => $equipment->name, 'asset_tag' => $equipment->asset_tag, 'available' => 0]]);
});

test('invalid codes and unrelated links cannot change the borrowing form', function (string $code, string $status) {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test('pages::borrowing.create')->call('addScannedEquipment', $code)
        ->assertSet('items', [['equipment_id' => '', 'quantity' => '1']])
        ->assertReturned(fn (array $result): bool => $result['status'] === $status && is_string($result['message']) && $result['message'] !== '');
    expect($page->get('items'))->toHaveCount(1);
    $message = match ($status) {
        'not_found' => 'No equipment matches this code. Check the label or select the equipment manually.',
        default => null,
    };
    if ($message) {
        $page->assertReturned(['status' => $status, 'message' => $message]);
    }
    $this->assertDatabaseCount('borrow_transactions', 0);
})->with([
    ['CLIMS:equipment:9999999', 'not_found'], ['UNKNOWN-TAG', 'not_found'],
    ['', 'invalid'], [str_repeat('a', 256), 'invalid'], ['CLIMS:equipment:0', 'invalid'],
    ['CLIMS:equipment:-1', 'invalid'], ['CLIMS:equipment:1<script>', 'invalid'],
    ['CLIMS:equipment:999999999999999999999999', 'invalid'], ['https://unrelated.example/equipment/1', 'invalid'],
]);

test('scan selection enforces the line limit but can fill an empty slot', function () {
    $equipment = Equipment::factory()->create();
    $filled = array_map(fn ($id) => ['equipment_id' => (string) $id, 'quantity' => '1'], range(1000, 1049));
    $this->actingAs(User::factory()->create());
    $page = Livewire::test('pages::borrowing.create')->set('items', $filled)
        ->call('addScannedEquipment', $equipment->qrPayload())->assertCount('items', 50)
        ->assertReturned(['status' => 'full', 'message' => 'This borrow record already has 50 equipment records. Remove an item before adding another.']);
    $filled[20] = ['equipment_id' => '', 'quantity' => '1'];
    $page->set('items', $filled)->call('addScannedEquipment', $equipment->qrPayload())
        ->assertCount('items', 50)->assertSet('items.20.equipment_id', (string) $equipment->id);
});

test('QR payloads remain valid after an asset tag rename and individually tracked records stay at quantity one', function () {
    $equipment = Equipment::factory()->create(['quantity' => 1, 'available_quantity' => 1, 'serial_number' => 'MON-SERIAL-100']);
    $code = $equipment->qrPayload();
    $equipment->update(['asset_tag' => 'RENAMED-100']);
    expect($equipment->fresh()->qrPayload())->toBe($code);
    $this->actingAs(User::factory()->create());
    Livewire::test('pages::borrowing.create')->call('addScannedEquipment', $code)->assertSet('items.0.quantity', '1');
    $this->get(route('equipment.show', $equipment))->assertOk()->assertSee('QR label')->assertSee('Equipment QR code');
});

test('inactive users cannot use a scanner left open while their account was active', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $this->actingAs($actor);
    $page = Livewire::test('pages::borrowing.create');
    User::whereKey($actor->id)->update(['is_active' => false]);
    $page->call('addScannedEquipment', $equipment->qrPayload())->assertForbidden();
});

test('scanned selections still undergo the current stock check when borrowing is saved', function () {
    $equipment = Equipment::factory()->create(['quantity' => 1, 'available_quantity' => 1]);
    $this->actingAs(User::factory()->create());
    $page = Livewire::test('pages::borrowing.create')->set('borrower_name', 'Borrower')
        ->set('borrower_identifier', '123456789012')->set('grade_level', 'Grade 10')->set('section', 'Rizal')
        ->call('addScannedEquipment', $equipment->qrPayload());
    Equipment::whereKey($equipment->id)->update(['available_quantity' => 0, 'defective_quantity' => 1]);
    $page->call('save')->assertHasErrors('items.0.quantity');
    $this->assertDatabaseCount('borrow_transactions', 0);
});
