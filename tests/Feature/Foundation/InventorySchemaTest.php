<?php

use App\Enums\BorrowStatus;
use App\Models\BorrowItem;
use App\Models\BorrowReturn;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;

test('equipment rejects impossible stock quantities', function (array $attributes) {
    expect(fn () => Equipment::factory()->create($attributes))->toThrow(QueryException::class);
    $this->assertDatabaseCount('equipment', 0);
})->with([
    'negative total' => [['quantity' => -1]],
    'negative availability' => [['available_quantity' => -1]],
    'negative defects' => [['defective_quantity' => -1]],
    'excess availability' => [['quantity' => 1, 'available_quantity' => 2]],
    'overlapping defects' => [['quantity' => 1, 'available_quantity' => 1, 'defective_quantity' => 1]],
    'serial on batch' => [['quantity' => 2, 'serial_number' => 'SERIAL-1']],
]);

test('stock constraints also reject invalid updates', function () {
    $equipment = Equipment::factory()->create();

    expect(fn () => Equipment::whereKey($equipment->id)->update(['available_quantity' => 2]))
        ->toThrow(QueryException::class);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 1]);
});

test('asset tags are unique', function () {
    Equipment::factory()->create(['asset_tag' => 'LAB-001']);

    expect(fn () => Equipment::factory()->create(['asset_tag' => 'LAB-001']))->toThrow(QueryException::class);
});

test('referenced catalog records cannot be deleted', function (string $relationship) {
    $equipment = Equipment::factory()->create();
    $reference = $equipment->{$relationship};

    expect(fn () => $reference->delete())->toThrow(QueryException::class);
    $this->assertModelExists($reference);
    $this->assertModelExists($equipment);
})->with(['category', 'location']);

test('a renamed inactive location remains associated with existing equipment', function () {
    $equipment = Equipment::factory()->create();
    $location = $equipment->location;

    $location->update(['name' => 'Renamed laboratory', 'is_active' => false]);

    expect($equipment->fresh()->location->name)->toBe('Renamed laboratory');
    expect(EquipmentLocation::active()->whereKey($location->id)->exists())->toBeFalse();
});

test('inactive categories are excluded from new assignment choices', function () {
    $category = EquipmentCategory::factory()->inactive()->create();

    expect(EquipmentCategory::active()->whereKey($category->id)->exists())->toBeFalse();
    $this->assertModelExists($category);
});

test('borrow item quantities cannot be zero or over returned', function (array $attributes) {
    expect(fn () => BorrowItem::factory()->create($attributes))->toThrow(QueryException::class);
})->with([
    'zero borrowed' => [['quantity' => 0]],
    'negative returned' => [['returned_quantity' => -1]],
    'excess returned' => [['quantity' => 1, 'returned_quantity' => 2]],
]);

test('return quantities must be positive and defects cannot exceed returned units', function (array $attributes) {
    expect(fn () => BorrowReturn::factory()->create($attributes))->toThrow(QueryException::class);
})->with([
    'zero returned' => [['quantity' => 0]],
    'negative defects' => [['defective_quantity' => -1]],
    'excess defects' => [['quantity' => 1, 'defective_quantity' => 2]],
]);

test('a return deadline cannot precede the borrowing time', function () {
    expect(fn () => BorrowTransaction::factory()->create([
        'borrowed_at' => '2026-10-31 10:00:00',
        'expected_return_at' => '2026-10-31 09:00:00',
    ]))->toThrow(QueryException::class);
});

test('overdue is derived from an exact deadline and outstanding units', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00:00', 'UTC'));
    $transaction = BorrowTransaction::factory()->create([
        'borrowed_at' => '2026-10-30 10:00:00',
        'expected_return_at' => '2026-10-31 10:00:00',
    ]);
    $item = BorrowItem::factory()->for($transaction, 'transaction')->create();

    expect($transaction->isOverdue())->toBeFalse();
    $this->travel(1)->seconds();
    expect($transaction->displayStatus())->toBe('overdue');
    $item->returned_quantity = 1;
    $item->save();
    expect($transaction->isOverdue())->toBeFalse();
    expect($transaction->returnStatus())->toBe(BorrowStatus::Returned);
});

test('partial returns and defective stock retain their separate quantities and relationships', function () {
    $equipment = Equipment::factory()->create(['quantity' => 20, 'available_quantity' => 15, 'defective_quantity' => 1]);
    $item = BorrowItem::factory()->for($equipment)->create(['quantity' => 5, 'returned_quantity' => 1]);
    $return = BorrowReturn::factory()->for($item, 'item')->create(['quantity' => 1, 'defective_quantity' => 1]);

    expect($equipment->outstandingQuantity())->toBe(4);
    expect($item->outstandingQuantity())->toBe(4);
    expect($item->transaction->returnStatus())->toBe(BorrowStatus::PartiallyReturned);
    expect($item->returns()->sole()->is($return))->toBeTrue();
    expect($item->transaction->items()->sole()->equipment->is($equipment))->toBeTrue();
    expect($return->recordedBy->borrowReturns()->sole()->is($return))->toBeTrue();
    expect($item->transaction->recordedBy->borrowTransactions()->sole()->is($item->transaction))->toBeTrue();
});

test('historical borrowing equipment and return links restrict deletion', function () {
    $return = BorrowReturn::factory()->create();

    expect(fn () => $return->item->delete())->toThrow(QueryException::class);
    expect(fn () => $return->item->transaction->delete())->toThrow(QueryException::class);
    expect(fn () => $return->item->equipment->delete())->toThrow(QueryException::class);
});

test('development seeding is repeatable and never creates known login credentials', function () {
    $this->seed(DatabaseSeeder::class);
    $location = EquipmentLocation::where('name', EquipmentLocation::COMPUTER_LAB_TWO)->firstOrFail();

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('equipment_categories', 9);
    $this->assertDatabaseCount('equipment_locations', 1);
    $this->assertDatabaseCount('users', 0);
    expect($location->fresh()->is_active)->toBeTrue();
});
