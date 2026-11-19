<?php

use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ManageEquipmentReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

test('catalog changes are validated and attributed to their actor', function (string $model, string $type) {
    $actor = User::factory()->create();
    $service = app(ManageEquipmentReference::class);

    $record = $service->save($actor, new $model, ['name' => '  Laboratory entry  ', 'description' => '  Details  ', 'role' => 'super_admin']);
    $service->save($actor, $record, ['name' => 'Renamed entry', 'is_active' => false]);

    $this->assertDatabaseHas($record->getTable(), ['id' => $record->id, 'name' => 'Renamed entry', 'description' => 'Details', 'is_active' => false]);
    $this->assertDatabaseCount('activity_logs', 2);
    $log = ActivityLog::where('action', $type.'.updated')->sole();
    expect($log->user_id)->toBe($actor->id);
    expect($log->old_values)->toMatchArray(['name' => 'Laboratory entry', 'is_active' => true]);
    expect($log->new_values)->toMatchArray(['name' => 'Renamed entry', 'is_active' => false]);
})->with([
    'category' => [EquipmentCategory::class, 'equipment_category'],
]);

test('catalog names must remain unique on create and edit', function (string $model) {
    $actor = User::factory()->create();
    $existing = $model::factory()->create(['name' => 'Existing entry']);
    $other = $model::factory()->create(['name' => 'Other entry']);
    $service = app(ManageEquipmentReference::class);

    expect(fn () => $service->save($actor, new $model, ['name' => ' Existing entry ']))->toThrow(ValidationException::class);
    expect(fn () => $service->save($actor, $other, ['name' => $existing->name]))->toThrow(ValidationException::class);
    $this->assertDatabaseHas($other->getTable(), ['id' => $other->id, 'name' => 'Other entry']);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([EquipmentCategory::class]);

test('invalid catalog values produce no write or audit', function (array $data) {
    $actor = User::factory()->create();

    expect(fn () => app(ManageEquipmentReference::class)->save($actor, new EquipmentCategory, $data))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('equipment_categories', 0);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([
    'blank name' => [['name' => ' ']],
    'long name' => [['name' => str_repeat('x', 256)]],
    'long description' => [['name' => 'Name', 'description' => str_repeat('x', 2001)]],
    'invalid activation' => [['name' => 'Name', 'is_active' => 'invalid']],
]);

test('inactive personnel cannot mutate catalog entries through the service', function (string $model) {
    $actor = User::factory()->inactive()->create();

    expect(fn () => app(ManageEquipmentReference::class)->save($actor, new $model, ['name' => 'Blocked']))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount((new $model)->getTable(), 0);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([EquipmentCategory::class]);

test('both active roles can delete unused catalog entries with a retained audit snapshot', function (string $model, bool $admin) {
    $factory = User::factory();
    $actor = ($admin ? $factory->superAdmin() : $factory)->create();
    $record = $model::factory()->create(['name' => 'Unused entry', 'description' => 'Keep this in the audit.']);
    $type = $record->getMorphClass();

    app(ManageEquipmentReference::class)->delete($actor, $record);

    $this->assertModelMissing($record);
    $log = ActivityLog::sole();
    expect($log->action)->toBe($type.'.deleted');
    expect($log->user_id)->toBe($actor->id);
    expect($log->subject_id)->toBe($record->id);
    expect($log->old_values)->toBe(['name' => 'Unused entry', 'description' => 'Keep this in the audit.', 'is_active' => true]);
    expect($log->new_values)->toBe([]);
    expect($log->subject)->toBeNull();
})->with([EquipmentCategory::class])->with([false, true]);

test('unused inactive catalog entries can also be deleted', function (string $model) {
    $actor = User::factory()->create();
    $record = $model::factory()->inactive()->create();

    app(ManageEquipmentReference::class)->delete($actor, $record);

    $this->assertModelMissing($record);
    expect(ActivityLog::sole()->old_values['is_active'])->toBeFalse();
})->with([EquipmentCategory::class]);

test('assigned catalog entries cannot be deleted even when inactive', function (string $model, string $relationship) {
    $actor = User::factory()->create();
    $record = $model::factory()->inactive()->create();
    $equipment = Equipment::factory()->for($record, $relationship)->create();

    expect(fn () => app(ManageEquipmentReference::class)->delete($actor, $record))
        ->toThrow(ValidationException::class, 'This entry is assigned to equipment and cannot be deleted. Deactivate it instead.');

    $this->assertModelExists($record);
    $this->assertModelExists($equipment);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([
    'category' => [EquipmentCategory::class, 'category'],
]);

test('deactivated personnel cannot delete catalog entries through the service', function (string $model) {
    $actor = User::factory()->create();
    $record = $model::factory()->create();
    User::whereKey($actor->id)->update(['is_active' => false]);

    expect(fn () => app(ManageEquipmentReference::class)->delete($actor, $record))
        ->toThrow(AuthorizationException::class);

    $this->assertModelExists($record);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([EquipmentCategory::class]);

test('catalog deletion rolls back when its audit record cannot be saved', function () {
    $actor = User::factory()->create();
    $record = EquipmentCategory::factory()->create();
    $this->mock(ActivityLogger::class)->shouldReceive('record')->once()
        ->andThrow(new RuntimeException('Audit storage unavailable'));

    expect(fn () => app(ManageEquipmentReference::class)->delete($actor, $record))
        ->toThrow(RuntimeException::class, 'Audit storage unavailable');

    $this->assertModelExists($record);
    $this->assertDatabaseCount('activity_logs', 0);
});
