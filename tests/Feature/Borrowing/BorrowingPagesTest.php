<?php

use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\User;
use App\Services\ManageBorrowing;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function pageLoan(User $actor, Equipment $equipment, array $overrides = []): BorrowTransaction
{
    return app(ManageBorrowing::class)->create($actor, array_replace([
        'borrower_name' => 'Campus Borrower', 'borrower_type' => 'student',
        'borrower_identifier' => '123456789012', 'grade_level' => 'Grade 10', 'section' => 'Rizal',
        'borrowed_at' => now()->subHour()->setTimezone(config('clims.display_timezone'))->format('Y-m-d\TH:i'),
        'expected_return_at' => now()->addDay()->setTimezone(config('clims.display_timezone'))->format('Y-m-d\TH:i'),
        'items' => [['equipment_id' => $equipment->id, 'quantity' => 3]],
    ], $overrides));
}

test('staff records a multi item loan and processes partial defective then final returns through pages', function () {
    $actor = User::factory()->create();
    $first = Equipment::factory()->create(['quantity' => 10, 'available_quantity' => 10, 'defective_quantity' => 0]);
    $second = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 5, 'defective_quantity' => 0]);
    $this->actingAs($actor);
    Livewire::test('pages::borrowing.create')->set('borrower_name', 'Campus Borrower')
        ->set('borrower_identifier', '123456789012')->set('grade_level', 'Grade 10')->set('section', 'Rizal')
        ->set('items', [['equipment_id' => $first->id, 'quantity' => 3], ['equipment_id' => $second->id, 'quantity' => 2]])
        ->call('save')->assertHasNoErrors();
    $loan = BorrowTransaction::sole();
    expect($first->fresh()->available_quantity)->toBe(7);
    $this->get(route('borrowing.show', $loan))->assertOk()->assertSee('Campus Borrower')->assertSee($first->name);
    $this->get(route('borrowing.return', $loan))->assertOk()->assertSee('What came back?');
    $rows = $loan->items()->orderBy('id')->get();
    Livewire::test('pages::borrowing.return', ['transaction' => $loan])
        ->set('items', [['borrow_item_id' => $rows[0]->id, 'quantity' => 1, 'defective_quantity' => 1], ['borrow_item_id' => $rows[1]->id, 'quantity' => 0, 'defective_quantity' => 0]])
        ->call('save')->assertHasNoErrors()->assertRedirect(route('borrowing.show', $loan));
    expect($first->fresh()->defective_quantity)->toBe(1)->and($first->fresh()->available_quantity)->toBe(7);
    $this->get(route('borrowing.show', $loan))->assertSee('Partially returned')->assertSee('Return history');
    Livewire::test('pages::borrowing.return', ['transaction' => $loan])
        ->set('items', [['borrow_item_id' => $rows[0]->id, 'quantity' => 2, 'defective_quantity' => 0], ['borrow_item_id' => $rows[1]->id, 'quantity' => 2, 'defective_quantity' => 0]])
        ->call('save')->assertHasNoErrors();
    expect($loan->fresh()->outstandingQuantity())->toBe(0)->and($first->fresh()->available_quantity)->toBe(9);
    $this->get(route('borrowing.return', $loan))->assertSee('All equipment has been returned.');
});

test('history searches equipment and separates overdue and current loans', function () {
    $actor = User::factory()->create();
    $equipment = Equipment::factory()->create(['quantity' => 12, 'available_quantity' => 12, 'defective_quantity' => 0, 'name' => 'Searchable projector']);
    $current = pageLoan($actor, $equipment);
    $overdue = pageLoan($actor, $equipment, ['borrower_name' => 'Overdue Person', 'borrowed_at' => now()->subDays(3)->format('Y-m-d\TH:i'), 'expected_return_at' => now()->subDay()->format('Y-m-d\TH:i')]);
    $this->actingAs($actor);
    Livewire::test('pages::borrowing.index')->set('search', 'Searchable projector')->assertSee('Campus Borrower')->assertSee('Overdue Person')
        ->set('status', 'overdue')->assertSee('Overdue Person')->assertDontSee('Campus Borrower')
        ->set('status', 'borrowed')->assertSee('Campus Borrower')->assertDontSee('Overdue Person')
        ->set('borrowerType', 'teacher')->assertSee('No borrowing transactions found')
        ->call('clearFilters')->assertSee('Overdue Person');
});

test('borrowing form displays stock errors and supports adding and removing rows', function () {
    $this->actingAs(User::factory()->create());
    $stock = Equipment::factory()->create(['quantity' => 1, 'available_quantity' => 1, 'defective_quantity' => 0]);
    Livewire::test('pages::borrowing.create')->call('addItem')->assertCount('items', 2)
        ->call('removeItem', 0)->assertCount('items', 1)
        ->set('items.0.equipment_id', (string) $stock->id)->set('items.0.quantity', '1')
        ->call('removeItem', 0)->assertSet('items', [['equipment_id' => '', 'quantity' => '1']])
        ->set('borrower_name', 'Borrower')->set('borrower_identifier', '123456789012')
        ->set('grade_level', 'Grade 10')->set('section', 'Rizal')
        ->set('items', [['equipment_id' => $stock->id, 'quantity' => 2]])
        ->call('save')->assertHasErrors(['items.0.quantity']);
    $this->assertDatabaseCount('borrow_transactions', 0);
});

test('return form rejects a stale return without adding history', function () {
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 5, 'defective_quantity' => 0]);
    $loan = pageLoan($actor, $stock);
    $this->actingAs($actor);
    $page = Livewire::test('pages::borrowing.return', ['transaction' => $loan]);
    $page->set('items.0.quantity', 4)->call('save')->assertHasErrors(['items.0.quantity']);
    $this->assertDatabaseCount('borrow_returns', 0);
});

test('borrowing routes require active authentication', function () {
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 5, 'defective_quantity' => 0]);
    $loan = pageLoan($actor, $stock);
    foreach (['borrowing.index', 'borrowing.create', 'borrowing.show', 'borrowing.return'] as $route) {
        $this->get(route($route, in_array($route, ['borrowing.show', 'borrowing.return']) ? $loan : []))->assertRedirect(route('login'));
    }
    $actor->forceFill(['is_active' => false])->save();
    $this->actingAs($actor)->get(route('borrowing.create'))->assertForbidden();
});

test('loan notes are escaped and transaction identity cannot be changed', function () {
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 5, 'defective_quantity' => 0]);
    $loan = pageLoan($actor, $stock, ['remarks' => '<script>alert(1)</script>']);
    $this->actingAs($actor)->get(route('borrowing.show', $loan))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    Livewire::test('pages::borrowing.return', ['transaction' => $loan])->set('transactionId', $loan->id + 1);
})->throws(CannotUpdateLockedPropertyException::class);

test('history distinguishes partial returns and completed loans from actual item quantities', function () {
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create(['quantity' => 9, 'available_quantity' => 9, 'defective_quantity' => 0]);
    $partial = pageLoan($actor, $stock, ['borrower_name' => 'Partial Borrower']);
    $complete = pageLoan($actor, $stock, ['borrower_name' => 'Completed Borrower']);
    foreach ([[$partial, 1], [$complete, 3]] as [$loan, $quantity]) {
        app(ManageBorrowing::class)->returnEquipment($actor, $loan, [
            'returned_at' => now()->setTimezone(config('clims.display_timezone'))->format('Y-m-d\\TH:i'),
            'items' => [['borrow_item_id' => $loan->items->sole()->id, 'quantity' => $quantity, 'defective_quantity' => 0]],
        ]);
    }
    $this->actingAs($actor);
    Livewire::test('pages::borrowing.index')->set('status', 'partially_returned')
        ->assertSee('Partial Borrower')->assertDontSee('Completed Borrower')
        ->set('status', 'returned')->assertSee('Completed Borrower')->assertDontSee('Partial Borrower');
});

test('a deactivated user cannot submit an already opened borrowing form', function () {
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create();
    $this->actingAs($actor);
    $page = Livewire::test('pages::borrowing.create')->set('borrower_name', 'Borrower')
        ->set('items', [['equipment_id' => $stock->id, 'quantity' => 1]]);
    User::whereKey($actor->id)->update(['is_active' => false]);
    $page->call('save')->assertForbidden();
    $this->assertDatabaseCount('borrow_transactions', 0);
});
