<?php

use App\Models\ActivityLog;
use App\Models\BorrowItem;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('an empty dashboard shows zero totals and helpful empty tables', function () {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test('pages::dashboard')
        ->assertSee('No borrowing recorded yet')->assertSee('No borrowing in the last 30 days')->assertSee('No equipment registered yet.');
    expect($page->get('totals'))->toBe(['total' => 0, 'records' => 0, 'available' => 0, 'borrowed' => 0, 'defective' => 0, 'active' => 0, 'overdue' => 0]);
});

test('dashboard totals count units and outstanding loans rather than inventory records or stored statuses', function () {
    $this->travelTo(now()->startOfSecond());
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create(['quantity' => 20, 'available_quantity' => 11, 'defective_quantity' => 3]);
    $other = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 5, 'defective_quantity' => 0]);
    $currentStock = Equipment::factory()->create(['quantity' => 10, 'available_quantity' => 8, 'defective_quantity' => 0]);
    $partial = BorrowTransaction::factory()->create(['borrowed_at' => now()->subDays(3), 'expected_return_at' => now()->subDay(), 'recorded_by' => $actor->id]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $partial->id, 'equipment_id' => $stock->id, 'quantity' => 8, 'returned_quantity' => 2]);
    $complete = BorrowTransaction::factory()->create(['borrowed_at' => now()->subDays(3), 'expected_return_at' => now()->subDay(), 'recorded_by' => $actor->id]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $complete->id, 'equipment_id' => $other->id, 'quantity' => 4, 'returned_quantity' => 4]);
    $current = BorrowTransaction::factory()->create(['recorded_by' => $actor->id]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $current->id, 'equipment_id' => $currentStock->id, 'quantity' => 2]);
    $this->actingAs($actor);
    $page = Livewire::test('pages::dashboard')->assertSee('Overdue')->assertSee('Returned');
    expect($page->get('totals'))->toBe(['total' => 35, 'records' => 3, 'available' => 24, 'borrowed' => 8, 'defective' => 3, 'active' => 2, 'overdue' => 1]);
});

test('overdue totals count a multi item transaction once and exclude the exact deadline and completed loans', function () {
    $this->travelTo(now()->startOfSecond());
    $actor = User::factory()->create();
    $late = BorrowTransaction::factory()->create(['borrowed_at' => now()->subDay(), 'expected_return_at' => now()->subSecond(), 'recorded_by' => $actor->id]);
    BorrowItem::factory()->count(2)->create(['borrow_transaction_id' => $late->id]);
    $due = BorrowTransaction::factory()->create(['borrowed_at' => now()->subDay(), 'expected_return_at' => now(), 'recorded_by' => $actor->id]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $due->id]);
    $done = BorrowTransaction::factory()->create(['borrowed_at' => now()->subDay(), 'expected_return_at' => now()->subSecond(), 'recorded_by' => $actor->id]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $done->id, 'returned_quantity' => 1]);
    $this->actingAs($actor);
    $totals = Livewire::test('pages::dashboard')->get('totals');
    expect($totals['active'])->toBe(2)->and($totals['overdue'])->toBe(1)->and($totals['borrowed'])->toBe(3);
});

test('recent borrowing is limited to five records ordered by event time then id', function () {
    $this->travelTo(now()->startOfSecond());
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create();
    for ($index = 0; $index < 7; $index++) {
        $loan = BorrowTransaction::factory()->create(['borrower_name' => 'Dashboard borrower '.$index, 'borrowed_at' => now()->subHours(7 - $index), 'recorded_by' => $actor->id]);
        BorrowItem::factory()->create(['borrow_transaction_id' => $loan->id, 'equipment_id' => $stock->id, 'returned_quantity' => 1]);
    }
    $this->actingAs($actor);
    $page = Livewire::test('pages::dashboard')->assertDontSee('Dashboard borrower 0')->assertDontSee('Dashboard borrower 1')
        ->assertSeeInOrder(['Dashboard borrower 6', 'Dashboard borrower 5', 'Dashboard borrower 4', 'Dashboard borrower 3', 'Dashboard borrower 2']);
    expect($page->get('recentLoans'))->toHaveCount(5);
    $latest = BorrowTransaction::factory()->create(['borrower_name' => 'Latest tied borrower', 'borrowed_at' => now()->subHour(), 'recorded_by' => $actor->id]);
    $page->call('refreshDashboard')->assertSeeInOrder(['Latest tied borrower', 'Dashboard borrower 6']);
});

test('dashboard escapes chart and borrower labels and excludes activity without deleting audit history', function () {
    $this->travelTo(now()->startOfSecond());
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create(['name' => '<script>inventory()</script>']);
    ActivityLog::factory()->create(['user_id' => $actor->id, 'action' => 'user.updated', 'subject_type' => 'user', 'subject_id' => $actor->id, 'new_values' => ['name' => 'Private administrative detail']]);
    ActivityLog::factory()->create(['user_id' => $actor->id, 'action' => 'equipment.updated', 'subject_type' => 'equipment', 'subject_id' => $stock->id]);
    ActivityLog::factory()->create(['user_id' => null, 'action' => 'equipment_category.deleted', 'subject_type' => 'equipment_category', 'subject_id' => 999999, 'old_values' => ['name' => 'Removed category']]);
    $loan = BorrowTransaction::factory()->create(['borrower_name' => '<script>borrower()</script>', 'recorded_by' => $actor->id]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $loan->id, 'equipment_id' => $stock->id]);
    $this->actingAs($actor);
    Livewire::test('pages::dashboard')->assertDontSee('Removed category')->assertDontSee('Recent activity')
        ->assertSee('&lt;script&gt;inventory()&lt;/script&gt;', false)->assertDontSee('<script>inventory()</script>', false)
        ->assertSee('&lt;script&gt;borrower()&lt;/script&gt;', false)->assertDontSee('<script>borrower()</script>', false)
        ->assertDontSee('Private administrative detail');
    $this->assertDatabaseCount('activity_logs', 3);
});

test('dashboard refresh shows current stock and refuses deactivated accounts', function () {
    $actor = User::factory()->create();
    $stock = Equipment::factory()->create(['quantity' => 5, 'available_quantity' => 5, 'defective_quantity' => 0]);
    $this->actingAs($actor);
    $page = Livewire::test('pages::dashboard');
    expect($page->get('totals')['available'])->toBe(5);
    Equipment::whereKey($stock->id)->update(['available_quantity' => 3, 'defective_quantity' => 2]);
    $page->call('refreshDashboard');
    expect($page->get('totals')['available'])->toBe(3)->and($page->get('totals')['defective'])->toBe(2);
    User::whereKey($actor->id)->update(['is_active' => false]);
    $page->call('refreshDashboard')->assertForbidden();
});

test('dashboard links open the corresponding filtered inventory and overdue views for both roles', function (bool $admin) {
    $actor = $admin ? User::factory()->superAdmin()->create() : User::factory()->create();
    $this->actingAs($actor)->get(route('dashboard'))->assertOk()
        ->assertSee(route('equipment.index', ['availability' => 'available']), false)
        ->assertSee(route('equipment.index', ['availability' => 'borrowed']), false)
        ->assertSee(route('equipment.index', ['availability' => 'defective']), false)
        ->assertSee(route('borrowing.index', ['status' => 'overdue']), false);
})->with([false, true]);

test('most borrowed chart ranks total units in the last thirty days including returned units', function () {
    $this->travelTo(now()->startOfSecond());
    $actor = User::factory()->create();
    $first = Equipment::factory()->create(['name' => 'Shared name', 'quantity' => 20, 'available_quantity' => 20]);
    $second = Equipment::factory()->create(['name' => 'Shared name', 'quantity' => 20, 'available_quantity' => 20]);
    $recent = BorrowTransaction::factory()->create(['recorded_by' => $actor->id, 'borrowed_at' => now()->subDays(2)]);
    $boundary = BorrowTransaction::factory()->create(['recorded_by' => $actor->id, 'borrowed_at' => now()->subDays(30), 'expected_return_at' => now()->subDays(29)]);
    $old = BorrowTransaction::factory()->create(['recorded_by' => $actor->id, 'borrowed_at' => now()->subDays(30)->subSecond(), 'expected_return_at' => now()->subDays(29)]);
    $future = BorrowTransaction::factory()->create(['recorded_by' => $actor->id, 'borrowed_at' => now()->addHour()]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $recent->id, 'equipment_id' => $first->id, 'quantity' => 3, 'returned_quantity' => 3]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $boundary->id, 'equipment_id' => $first->id, 'quantity' => 4, 'returned_quantity' => 4]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $recent->id, 'equipment_id' => $second->id, 'quantity' => 5]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $old->id, 'equipment_id' => $second->id, 'quantity' => 9]);
    BorrowItem::factory()->create(['borrow_transaction_id' => $future->id, 'equipment_id' => $second->id, 'quantity' => 10]);
    $this->actingAs($actor);
    $page = Livewire::test('pages::dashboard')->assertSee('Last 30 days');
    expect($page->get('mostBorrowed'))->toBe([
        ['id' => $first->id, 'name' => 'Shared name', 'asset_tag' => $first->asset_tag, 'units' => 7],
        ['id' => $second->id, 'name' => 'Shared name', 'asset_tag' => $second->asset_tag, 'units' => 5],
    ]);
    BorrowItem::factory()->create(['borrow_transaction_id' => BorrowTransaction::factory()->create(['recorded_by' => $actor->id])->id, 'equipment_id' => $second->id, 'quantity' => 4]);
    $page->call('refreshDashboard');
    expect($page->get('mostBorrowed')[0]['id'])->toBe($second->id)
        ->and($page->get('mostBorrowed')[0]['units'])->toBe(9);
});

test('most borrowed chart shows at most five equipment records with deterministic ties', function () {
    $actor = User::factory()->create();
    $loan = BorrowTransaction::factory()->create(['recorded_by' => $actor->id]);
    $ids = [];
    foreach (range(1, 7) as $index) {
        $equipment = Equipment::factory()->create();
        $ids[] = $equipment->id;
        BorrowItem::factory()->create(['borrow_transaction_id' => $loan->id, 'equipment_id' => $equipment->id]);
    }
    $this->actingAs($actor);
    $ranking = Livewire::test('pages::dashboard')->get('mostBorrowed');
    expect(array_column($ranking, 'id'))->toBe(array_slice($ids, 0, 5));
});
