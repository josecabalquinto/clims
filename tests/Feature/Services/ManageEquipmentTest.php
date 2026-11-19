<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\BorrowItem;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ManageEquipment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function equipmentInput(array $overrides = []): array
{
    return array_replace([
        'asset_tag' => 'lab-100',
        'name' => 'Laboratory mouse',
        'category_id' => $overrides['category_id'] ?? EquipmentCategory::factory()->create()->id,
        'description' => 'USB mouse',
        'serial_number' => '',
        'quantity' => 10,
        'defective_quantity' => 2,
        'condition' => 'good',
        'date_acquired' => '2026-01-01',
        'remarks' => 'Stored in the laboratory.',
    ], $overrides);
}

test('the admin can register stock with server calculated availability and a complete audit', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $data = equipmentInput(['available_quantity' => 999, 'recorded_by' => 999, 'name' => '  Laboratory mouse  ']);

    $equipment = app(ManageEquipment::class)->save($actor, $data);

    $this->assertDatabaseHas('equipment', [
        'id' => $equipment->id, 'asset_tag' => 'LAB-100', 'name' => 'Laboratory mouse',
        'quantity' => 10, 'available_quantity' => 8, 'defective_quantity' => 2, 'serial_number' => null,
    ]);
    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($actor->id);
    expect($log->new_values)->toMatchArray(['description' => 'USB mouse', 'remarks' => 'Stored in the laboratory.', 'available_quantity' => 8]);
    expect($log->subject->is($equipment))->toBeTrue();
})->with(UserRole::cases());

test('stock adjustments preserve outstanding borrowing quantities', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 10, 'available_quantity' => 4, 'defective_quantity' => 1]);
    BorrowItem::factory()->for($equipment)->create(['quantity' => 7, 'returned_quantity' => 2]);
    $data = equipmentInput(['asset_tag' => $equipment->asset_tag, 'category_id' => $equipment->category_id, 'location_id' => $equipment->location_id, 'quantity' => 12, 'defective_quantity' => 2]);

    app(ManageEquipment::class)->save($actor, $data, $equipment);

    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'quantity' => 12, 'available_quantity' => 5, 'defective_quantity' => 2]);
    $this->assertDatabaseHas('borrow_items', ['equipment_id' => $equipment->id, 'quantity' => 7, 'returned_quantity' => 2]);
    $log = ActivityLog::sole();
    expect($log->old_values)->toMatchArray(['quantity' => 10, 'available_quantity' => 4, 'defective_quantity' => 1]);
    expect($log->new_values)->toMatchArray(['quantity' => 12, 'available_quantity' => 5, 'defective_quantity' => 2]);
});

test('total stock cannot fall below units still borrowed', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 0]);
    BorrowItem::factory()->for($equipment)->create(['quantity' => 5]);
    $data = equipmentInput(['asset_tag' => $equipment->asset_tag, 'category_id' => $equipment->category_id, 'location_id' => $equipment->location_id, 'quantity' => 4, 'defective_quantity' => 0]);

    expect(fn () => app(ManageEquipment::class)->save($actor, $data, $equipment))
        ->toThrow(ValidationException::class, 'Total quantity cannot be lower than the 5 units currently borrowed.');

    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'quantity' => 5, 'available_quantity' => 0]);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('defective quantity cannot consume units still borrowed', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 2]);
    BorrowItem::factory()->for($equipment)->create(['quantity' => 3]);
    $data = equipmentInput(['asset_tag' => $equipment->asset_tag, 'category_id' => $equipment->category_id, 'location_id' => $equipment->location_id, 'quantity' => 5, 'defective_quantity' => 3]);

    expect(fn () => app(ManageEquipment::class)->save($actor, $data, $equipment))
        ->toThrow(ValidationException::class, 'Defective quantity cannot exceed the units currently on hand.');

    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 2, 'defective_quantity' => 0]);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('invalid equipment submissions leave no equipment or audit records', function (array $overrides) {
    $actor = User::factory()->create();
    $data = equipmentInput($overrides);

    expect(fn () => app(ManageEquipment::class)->save($actor, $data))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('equipment', 0);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([
    'missing tag' => [['asset_tag' => '  ']],
    'missing name' => [['name' => '']],
    'missing category' => [['category_id' => '']],
    'unknown category' => [['category_id' => 999999]],
    'negative total' => [['quantity' => -1]],
    'zero total' => [['quantity' => 0]],
    'fractional total' => [['quantity' => 1.5]],
    'overflow total' => [['quantity' => 4294967296]],
    'negative defects' => [['defective_quantity' => -1]],
    'excess defects' => [['defective_quantity' => 11]],
    'serial on batch' => [['serial_number' => 'SERIAL-123']],
    'unknown condition' => [['condition' => 'unknown']],
    'invalid date' => [['date_acquired' => '2026-02-30']],
    'too long description' => [['description' => str_repeat('x', 5001)]],
]);

test('serial numbers are allowed for individual assets', function () {
    $actor = User::factory()->create();

    $equipment = app(ManageEquipment::class)->save($actor, equipmentInput(['quantity' => 1, 'defective_quantity' => 0, 'serial_number' => 'SERIAL-1']));

    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'serial_number' => 'SERIAL-1', 'quantity' => 1, 'available_quantity' => 1]);
});

test('normalized asset tags cannot duplicate an existing record', function () {
    $actor = User::factory()->create();
    Equipment::factory()->create(['asset_tag' => 'LAB-100']);

    expect(fn () => app(ManageEquipment::class)->save($actor, equipmentInput(['asset_tag' => ' lab-100 '])))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('equipment', 1);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('new equipment cannot be assigned to inactive catalog entries', function (string $field, string $model) {
    $actor = User::factory()->create();
    $inactive = $model::factory()->inactive()->create();
    $data = equipmentInput([$field => $inactive->id]);

    expect(fn () => app(ManageEquipment::class)->save($actor, $data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('equipment', 0);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([
    'category' => ['category_id', EquipmentCategory::class],
]);

test('existing inactive assignments may be retained but cannot be newly selected', function (string $field, string $model) {
    $actor = User::factory()->create();
    $current = $model::factory()->inactive()->create();
    $other = $model::factory()->inactive()->create();
    $equipment = Equipment::factory()->create([$field => $current->id]);
    $data = equipmentInput(['asset_tag' => $equipment->asset_tag, 'category_id' => $equipment->category_id, 'location_id' => $equipment->location_id, 'quantity' => 1, 'defective_quantity' => 0]);

    app(ManageEquipment::class)->save($actor, $data, $equipment);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, $field => $current->id]);
    $this->assertDatabaseCount('activity_logs', 1);

    expect(fn () => app(ManageEquipment::class)->save($actor, array_replace($data, [$field => $other->id]), $equipment))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, $field => $current->id]);
    $this->assertDatabaseCount('activity_logs', 1);
})->with([
    'category' => ['category_id', EquipmentCategory::class],
]);

test('saving unchanged equipment does not create a false activity', function () {
    $actor = User::factory()->create();
    $data = equipmentInput();
    $equipment = app(ManageEquipment::class)->save($actor, $data);

    app(ManageEquipment::class)->save($actor, $data, $equipment);

    $this->assertDatabaseCount('equipment', 1);
    $this->assertDatabaseCount('activity_logs', 1);
});

test('inventory changes roll back if activity recording fails', function () {
    $actor = User::factory()->create();
    $data = equipmentInput();
    $this->mock(ActivityLogger::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit storage unavailable'));

    expect(fn () => app(ManageEquipment::class)->save($actor, $data))->toThrow(RuntimeException::class, 'Audit storage unavailable');

    $this->assertDatabaseCount('equipment', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('a deactivated actor cannot create or update equipment through the service', function () {
    $actor = User::factory()->create();
    $data = equipmentInput();
    User::whereKey($actor->id)->update(['is_active' => false]);

    expect(fn () => app(ManageEquipment::class)->save($actor, $data))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('equipment', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});
