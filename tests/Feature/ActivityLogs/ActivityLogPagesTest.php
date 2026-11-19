<?php

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('super admins can read the log list and details', function () {
    $user = User::factory()->superAdmin()->create();
    $log = ActivityLog::factory()->create(['user_id' => $user->id, 'subject_type' => 'equipment', 'subject_id' => 100, 'action' => 'equipment.created', 'new_values' => ['asset_tag' => 'LAB-100', 'name' => 'Test projector']]);
    $this->actingAs($user)->get(route('activity-logs.index'))->assertOk()->assertSee('Test projector');
    $this->get(route('activity-logs.show', $log))->assertOk()->assertSee('Equipment registered')->assertSee('Test projector')->assertSee('LAB-100');
});

test('logs can be searched by recorded names asset tags users actions and affected record IDs', function () {
    $actor = User::factory()->superAdmin()->create(['name' => 'Activity Operator']);
    $equipment = ActivityLog::factory()->create(['user_id' => $actor->id, 'subject_type' => 'equipment', 'subject_id' => 101, 'action' => 'equipment.updated', 'old_values' => ['name' => 'Previous device'], 'new_values' => ['name' => 'Current projector', 'asset_tag' => 'SEARCH-001']]);
    ActivityLog::factory()->create(['user_id' => null, 'subject_type' => 'equipment', 'subject_id' => 102, 'action' => 'equipment.created', 'new_values' => ['name' => 'Unrelated display']]);
    $this->actingAs($actor);
    foreach (['Current projector', 'Previous device', 'SEARCH-001', 'Activity Operator', 'equipment.updated', 'Equipment updated', '101'] as $search) {
        $page = Livewire::test('pages::activity-logs.index')->set('search', $search);
        expect($page->get('logs')->pluck('id')->all())->toBe([$equipment->id]);
    }
});

test('module actor and action filters combine and can be cleared', function () {
    $actor = User::factory()->superAdmin()->create();
    $inactive = User::factory()->inactive()->create(['name' => 'Former Operator']);
    $wanted = ActivityLog::factory()->create(['user_id' => $inactive->id, 'subject_type' => 'equipment_location', 'subject_id' => 200, 'action' => 'equipment_location.deleted', 'old_values' => ['name' => 'Old storage room']]);
    $loan = ActivityLog::factory()->create(['user_id' => $actor->id, 'subject_type' => 'borrow_transaction', 'subject_id' => 201, 'action' => 'borrow_transaction.created']);
    $system = ActivityLog::factory()->create(['user_id' => null, 'subject_type' => 'user', 'subject_id' => 202, 'action' => 'user.updated']);
    $this->actingAs($actor);
    $page = Livewire::test('pages::activity-logs.index')->set('module', 'equipment')->set('actor', (string) $inactive->id)->set('action', 'equipment_location.deleted');
    expect($page->get('logs')->pluck('id')->all())->toBe([$wanted->id]);
    $page->assertSee('Former Operator (inactive)')->call('clearFilters')->set('module', 'borrowing');
    expect($page->get('logs')->pluck('id')->all())->toBe([$loan->id]);
    $page->call('clearFilters')->set('actor', 'system');
    expect($page->get('logs')->pluck('id')->all())->toBe([$system->id]);
    $page->call('clearFilters');
    expect($page->get('logs')->total())->toBe(3);
    $page->set('module', 'unknown');
    expect($page->get('logs')->total())->toBe(0);
});

test('date filters include both full local calendar days while querying UTC audit times', function () {
    $actor = User::factory()->superAdmin()->create();
    $ids = [];
    foreach (['2026-10-31 15:59:59', '2026-10-31 16:00:00', '2026-11-01 15:59:59', '2026-11-01 16:00:00'] as $time) {
        $ids[] = ActivityLog::factory()->create(['user_id' => $actor->id, 'subject_type' => 'equipment', 'subject_id' => 1, 'created_at' => CarbonImmutable::parse($time, 'UTC')])->id;
    }
    $this->actingAs($actor);
    $page = Livewire::test('pages::activity-logs.index')->set('fromDate', '2026-11-01')->set('toDate', '2026-11-01')->assertHasNoErrors();
    expect($page->get('logs')->pluck('id')->all())->toBe([$ids[2], $ids[1]]);
    $page->set('toDate', '2026-10-31')->assertHasErrors('toDate')->call('clearFilters')->assertHasNoErrors();
    $page->set('fromDate', 'not-a-date')->assertHasErrors('fromDate');
    expect($page->get('logs')->total())->toBe(4);
});

test('pagination is stable for tied timestamps and resets after changing a filter', function () {
    $actor = User::factory()->superAdmin()->create();
    $this->travelTo(now()->startOfSecond());
    $logs = ActivityLog::factory()->count(16)->create(['user_id' => $actor->id, 'subject_type' => 'equipment', 'subject_id' => 1]);
    $this->actingAs($actor);
    $page = Livewire::test('pages::activity-logs.index');
    expect($page->get('logs')->count())->toBe(15)->and($page->get('logs')->first()->id)->toBe($logs->last()->id);
    $page->call('gotoPage', 2);
    expect($page->get('logs')->pluck('id')->all())->toBe([$logs->first()->id]);
    $page->set('search', 'no-match')->assertSet('paginators.page', 1)->assertSee('No activity records found');
});

test('details show safe historical values and never render legacy secrets or structured payloads', function () {
    $actor = User::factory()->superAdmin()->create();
    $log = ActivityLog::factory()->create([
        'user_id' => null, 'subject_type' => 'user', 'subject_id' => 99999, 'action' => 'user.updated',
        'old_values' => ['name' => 'Former name', 'role' => 'lab_staff', 'is_active' => true, 'password' => 'old-password-secret'],
        'new_values' => ['name' => '<script>unsafe()</script>', 'role' => 'super_admin', 'is_active' => false, 'password' => 'new-password-secret', 'remember_token' => 'token-secret', 'email' => null],
    ]);
    $this->actingAs($actor);
    $this->get(route('activity-logs.show', $log))->assertOk()->assertSee('Former name')->assertSee('Former staff account')->assertSee('Admin')->assertSee('Inactive')->assertSee('Not set')->assertSee('System')
        ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false)
        ->assertDontSee('old-password-secret')->assertDontSee('new-password-secret')->assertDontSee('token-secret');
    $legacy = ActivityLog::factory()->create(['user_id' => $actor->id, 'subject_type' => 'equipment', 'subject_id' => 200, 'action' => 'equipment.updated', 'new_values' => ['remarks' => ['secret' => 'nested-secret'], 'date_acquired' => 'bad-date']]);
    $this->get(route('activity-logs.show', $legacy))->assertOk()->assertSee('Structured value omitted')->assertDontSee('nested-secret')->assertSee('bad-date');
});

test('deleted categories and unknown legacy subject types remain readable without live relationships', function () {
    $actor = User::factory()->superAdmin()->create();
    $deleted = ActivityLog::factory()->create(['user_id' => $actor->id, 'subject_type' => 'equipment_category', 'subject_id' => 99999, 'action' => 'equipment_category.deleted', 'old_values' => ['name' => 'Removed category']]);
    $unknown = ActivityLog::factory()->create(['user_id' => null, 'subject_type' => 'legacy_record', 'subject_id' => 99998, 'action' => 'legacy_record.archived', 'new_values' => ['name' => 'private legacy payload']]);
    $this->actingAs($actor)->get(route('activity-logs.show', $deleted))->assertOk()->assertSee('Removed category');
    $this->get(route('activity-logs.show', $unknown))->assertOk()->assertSee('No displayable changes recorded')->assertDontSee('private legacy payload');
    $this->get(route('activity-logs.index'))->assertOk()->assertSee('Record #99998');
});

test('borrow timestamps in changes use the configured display timezone', function () {
    $log = ActivityLog::factory()->create(['subject_type' => 'borrow_transaction', 'subject_id' => 55, 'new_values' => ['borrowed_at' => '2026-11-01T01:30:00+00:00', 'status' => 'partially_returned']]);
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('activity-logs.show', $log))->assertOk()->assertSee('Nov 1, 2026')->assertSee('9:30 AM')->assertSee('Partially returned');
});

test('log detail access requires authentication and an active account and rejects missing records', function () {
    $log = ActivityLog::factory()->create();
    $this->get(route('activity-logs.show', $log))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->superAdmin()->inactive()->create())->get(route('activity-logs.show', $log))->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('activity-logs.show', 999999))->assertNotFound();
});

test('deactivation blocks an already opened log page', function () {
    $actor = User::factory()->superAdmin()->create();
    $this->actingAs($actor);
    $page = Livewire::test('pages::activity-logs.index');
    User::whereKey($actor->id)->update(['is_active' => false]);
    $page->set('search', 'equipment')->assertForbidden();
});

test('activity detail identity is locked against client updates', function () {
    $log = ActivityLog::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test('pages::activity-logs.show', ['log' => $log])->set('logId', $log->id + 1);
})->throws(CannotUpdateLockedPropertyException::class);

test('log endpoints offer no update or delete operations', function () {
    $log = ActivityLog::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->patch(route('activity-logs.show', $log), ['action' => 'tampered'])->assertStatus(405);
    $this->delete(route('activity-logs.show', $log))->assertStatus(405);
    $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'action' => $log->action]);
});
