<?php

use App\Enums\BorrowStatus;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ManageBorrowing;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00:00', 'UTC'));
});

function borrowingInput(array $items, array $overrides = []): array
{
    return array_replace([
        'borrower_name' => 'Sample Borrower',
        'borrower_type' => 'student',
        'borrower_identifier' => '123456789012',
        'grade_level' => 'Grade 10',
        'section' => 'Rizal',
        'borrowed_at' => '2026-10-31T17:00',
        'expected_return_at' => '2026-11-01T18:00',
        'remarks' => 'Laboratory activity',
        'items' => $items,
    ], $overrides);
}

function returningInput(array $items, array $overrides = []): array
{
    return array_replace(['returned_at' => '2026-10-31T18:00', 'remarks' => 'Checked at the laboratory.', 'items' => $items], $overrides);
}

test('both roles can record multi item loans with UTC dates and server controlled attribution', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $first = Equipment::factory()->create(['quantity' => 10, 'available_quantity' => 9, 'defective_quantity' => 1]);
    $second = Equipment::factory()->create(['quantity' => 2, 'available_quantity' => 2]);

    $loan = app(ManageBorrowing::class)->create($actor, borrowingInput([
        ['equipment_id' => $first->id, 'quantity' => 3],
        ['equipment_id' => $second->id, 'quantity' => 1],
    ], ['recorded_by' => 999, 'status' => 'returned']));

    expect($loan->status)->toBe(BorrowStatus::Borrowed);
    expect($loan->recorded_by)->toBe($actor->id);
    expect($loan->borrowed_at->format('Y-m-d H:i:s'))->toBe('2026-10-31 09:00:00');
    expect($loan->expected_return_at->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00');
    expect($loan->items)->toHaveCount(2);
    $this->assertDatabaseHas('equipment', ['id' => $first->id, 'available_quantity' => 6, 'defective_quantity' => 1]);
    $this->assertDatabaseHas('equipment', ['id' => $second->id, 'available_quantity' => 1]);
    $this->assertDatabaseCount('activity_logs', 5);
    $this->assertDatabaseCount('users', 1);
})->with(UserRole::cases());

test('partial and defective returns retain history and restore only serviceable units', function () {
    $actor = User::factory()->create();
    $processor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 10, 'available_quantity' => 10]);
    $service = app(ManageBorrowing::class);
    $loan = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 7]]));
    $item = $loan->items->sole();

    $loan = $service->returnEquipment($processor, $loan, returningInput([['borrow_item_id' => $item->id, 'quantity' => 3, 'defective_quantity' => 1]]));

    expect($loan->status)->toBe(BorrowStatus::PartiallyReturned);
    expect($loan->returned_at)->toBeNull();
    expect($loan->outstandingQuantity())->toBe(4);
    expect($loan->recorded_by)->toBe($actor->id);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 5, 'defective_quantity' => 1]);
    $this->assertDatabaseHas('borrow_returns', ['borrow_item_id' => $item->id, 'quantity' => 3, 'defective_quantity' => 1, 'recorded_by' => $processor->id, 'remarks' => 'Checked at the laboratory.']);

    $loan = $service->returnEquipment($processor, $loan, returningInput([['borrow_item_id' => $item->id, 'quantity' => 4, 'defective_quantity' => 0]]));

    expect($loan->status)->toBe(BorrowStatus::Returned);
    expect($loan->outstandingQuantity())->toBe(0);
    expect($loan->returned_at->format('Y-m-d H:i:s'))->toBe('2026-10-31 10:00:00');
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 9, 'defective_quantity' => 1]);
    $this->assertDatabaseCount('borrow_returns', 2);
    $this->assertDatabaseCount('activity_logs', 11);
});

test('completion waits for all equipment and uses the latest actual return time', function () {
    $actor = User::factory()->create();
    $first = Equipment::factory()->create();
    $second = Equipment::factory()->create();
    $service = app(ManageBorrowing::class);
    $loan = $service->create($actor, borrowingInput([['equipment_id' => $first->id, 'quantity' => 1], ['equipment_id' => $second->id, 'quantity' => 1]]));
    $items = $loan->items;

    $loan = $service->returnEquipment($actor, $loan, returningInput([['borrow_item_id' => $items[0]->id, 'quantity' => 1, 'defective_quantity' => 0]], ['returned_at' => '2026-10-31T17:45']));
    expect($loan->status)->toBe(BorrowStatus::PartiallyReturned);

    $loan = $service->returnEquipment($actor, $loan, returningInput([['borrow_item_id' => $items[1]->id, 'quantity' => 1, 'defective_quantity' => 0]], ['returned_at' => '2026-10-31T17:30']));
    expect($loan->status)->toBe(BorrowStatus::Returned);
    expect($loan->returned_at->format('Y-m-d H:i:s'))->toBe('2026-10-31 09:45:00');
});

test('insufficient stock in any loan item rolls back the whole transaction', function () {
    $actor = User::factory()->create();
    $first = Equipment::factory()->create(['quantity' => 3, 'available_quantity' => 3]);
    $second = Equipment::factory()->create();

    expect(fn () => app(ManageBorrowing::class)->create($actor, borrowingInput([
        ['equipment_id' => $first->id, 'quantity' => 2], ['equipment_id' => $second->id, 'quantity' => 2],
    ])))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('borrow_transactions', 0);
    $this->assertDatabaseCount('borrow_items', 0);
    $this->assertDatabaseCount('activity_logs', 0);
    $this->assertDatabaseHas('equipment', ['id' => $first->id, 'available_quantity' => 3]);
    $this->assertDatabaseHas('equipment', ['id' => $second->id, 'available_quantity' => 1]);
});

test('a second loan cannot consume units taken by the first loan', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $data = borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]);
    app(ManageBorrowing::class)->create($actor, $data);

    expect(fn () => app(ManageBorrowing::class)->create($actor, $data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('borrow_transactions', 1);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 0]);
});

test('duplicate equipment including mixed numeric representations cannot bypass availability', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();

    expect(fn () => app(ManageBorrowing::class)->create($actor, borrowingInput([
        ['equipment_id' => $equipment->id, 'quantity' => 1], ['equipment_id' => (string) $equipment->id, 'quantity' => 1],
    ])))->toThrow(ValidationException::class, 'Select each equipment record only once.');
    $this->assertDatabaseCount('borrow_transactions', 0);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 1]);
});

test('invalid borrower and date fields never change stock', function (array $overrides) {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();

    expect(fn () => app(ManageBorrowing::class)->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]], $overrides)))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('borrow_transactions', 0);
    $this->assertDatabaseCount('activity_logs', 0);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 1]);
})->with([
    'blank borrower' => [['borrower_name' => ' ']],
    'unknown borrower type' => [['borrower_type' => 'administrator']],
    'invalid time format' => [['borrowed_at' => '2026-10-31']],
    'invalid calendar date' => [['borrowed_at' => '2026-02-30T12:00']],
    'future borrowing' => [['borrowed_at' => '2026-10-31T19:00']],
    'earlier deadline' => [['expected_return_at' => '2026-10-31T16:00']],
    'equal deadline' => [['expected_return_at' => '2026-10-31T17:00']],
    'invalid LRN' => [['borrower_identifier' => '12345']],
    'long remarks' => [['remarks' => str_repeat('x', 5001)]],
    'no items' => [['items' => []]],
    'unknown equipment' => [['items' => [['equipment_id' => 999999, 'quantity' => 1]]]],
    'zero quantity' => [['items' => [['equipment_id' => 1, 'quantity' => 0]]]],
    'fractional quantity' => [['items' => [['equipment_id' => 1, 'quantity' => 1.5]]]],
    'negative quantity' => [['items' => [['equipment_id' => 1, 'quantity' => -1]]]],
    'unexpected item key' => [['items' => [['equipment_id' => 1, 'quantity' => 1, 'available_quantity' => 999]]]],
]);

test('a stale or repeated return cannot return the same units twice', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $service = app(ManageBorrowing::class);
    $loan = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]));
    $data = returningInput([['borrow_item_id' => $loan->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 0]]);
    $service->returnEquipment($actor, $loan, $data);

    expect(fn () => $service->returnEquipment($actor, $loan, $data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('borrow_returns', 1);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 1]);
});

test('invalid returns do not change any stock or return history', function (array $overrides) {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 3, 'available_quantity' => 3]);
    $service = app(ManageBorrowing::class);
    $loan = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 2]]));
    $line = ['borrow_item_id' => $loan->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 0];
    $data = returningInput([array_replace($line, $overrides)]);

    expect(fn () => $service->returnEquipment($actor, $loan, $data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('borrow_returns', 0);
    $this->assertDatabaseCount('activity_logs', 3);
    $this->assertDatabaseHas('borrow_items', ['id' => $line['borrow_item_id'], 'returned_quantity' => 0]);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 1, 'defective_quantity' => 0]);
})->with([
    'over return' => [['quantity' => 3]],
    'negative return' => [['quantity' => -1]],
    'fractional return' => [['quantity' => 0.5]],
    'negative defects' => [['defective_quantity' => -1]],
    'excess defects' => [['quantity' => 1, 'defective_quantity' => 2]],
    'no units' => [['quantity' => 0]],
    'unknown item' => [['borrow_item_id' => 999999]],
]);

test('return times must be between borrowing and the present', function (string $time) {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $service = app(ManageBorrowing::class);
    $loan = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]));

    expect(fn () => $service->returnEquipment($actor, $loan, returningInput([['borrow_item_id' => $loan->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 0]], ['returned_at' => $time])))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('borrow_returns', 0);
})->with(['2026-10-31T16:59', '2026-10-31T18:01']);

test('an item from another transaction cannot be returned against this loan', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 2, 'available_quantity' => 2]);
    $service = app(ManageBorrowing::class);
    $first = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]));
    $second = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]));

    expect(fn () => $service->returnEquipment($actor, $first, returningInput([['borrow_item_id' => $second->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 0]])))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('borrow_returns', 0);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 0]);
});

test('duplicate return lines are rejected before they can over return stock', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $service = app(ManageBorrowing::class);
    $loan = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]));
    $line = ['borrow_item_id' => $loan->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 0];

    expect(fn () => $service->returnEquipment($actor, $loan, returningInput([$line, $line])))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('borrow_returns', 0);
});

test('audit failure rolls back returns', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $data = borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]);
    $loan = app(ManageBorrowing::class)->create($actor, $data);
    $this->mock(ActivityLogger::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit failed'));

    expect(fn () => app(ManageBorrowing::class)->returnEquipment($actor, $loan, returningInput([['borrow_item_id' => $loan->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 1]])))
        ->toThrow(RuntimeException::class, 'Audit failed');
    $this->assertDatabaseCount('borrow_returns', 0);
    $this->assertDatabaseHas('borrow_items', ['id' => $loan->items->sole()->id, 'returned_quantity' => 0]);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 0, 'defective_quantity' => 0]);
    $this->assertDatabaseHas('borrow_transactions', ['id' => $loan->id, 'status' => 'borrowed', 'returned_at' => null]);
});

test('audit failure rolls back a new borrowing transaction and stock', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $this->mock(ActivityLogger::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit failed'));

    expect(fn () => app(ManageBorrowing::class)->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]])))
        ->toThrow(RuntimeException::class, 'Audit failed');
    $this->assertDatabaseCount('borrow_transactions', 0);
    $this->assertDatabaseCount('borrow_items', 0);
    expect($equipment->fresh()->available_quantity)->toBe(1);
});

test('inactive users cannot create loans or process returns even through services', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 2, 'available_quantity' => 2]);
    $service = app(ManageBorrowing::class);
    $loan = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]));
    User::whereKey($actor->id)->update(['is_active' => false]);

    expect(fn () => $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]])))->toThrow(AuthorizationException::class);
    expect(fn () => $service->returnEquipment($actor, $loan, returningInput([['borrow_item_id' => $loan->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 0]])))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('borrow_transactions', 1);
    $this->assertDatabaseCount('borrow_returns', 0);
});

test('equipment under inactive catalog entries can still be borrowed and returned', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create();
    $equipment->category->update(['is_active' => false]);
    $equipment->location->update(['is_active' => false]);
    $service = app(ManageBorrowing::class);

    $loan = $service->create($actor, borrowingInput([['equipment_id' => $equipment->id, 'quantity' => 1]]));
    $service->returnEquipment($actor, $loan, returningInput([['borrow_item_id' => $loan->items->sole()->id, 'quantity' => 1, 'defective_quantity' => 0]]));

    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 1]);
});
