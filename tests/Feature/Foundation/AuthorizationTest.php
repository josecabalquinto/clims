<?php

use App\Models\ActivityLog;
use App\Models\BorrowItem;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('the active admin can access operational models', function (string $model) {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', $model))->toBeTrue();
})->with([Equipment::class, EquipmentCategory::class, EquipmentLocation::class, BorrowTransaction::class, ActivityLog::class]);

test('the admin can view activity logs', function () {
    $user = User::factory()->create();
    $log = ActivityLog::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', ActivityLog::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $log))->toBeTrue();
});

test('inactive accounts are denied every model listing', function (string $model) {
    $user = User::factory()->superAdmin()->inactive()->create();

    expect(Gate::forUser($user)->allows('viewAny', $model))->toBeFalse();
})->with([Equipment::class, EquipmentCategory::class, EquipmentLocation::class, BorrowTransaction::class, ActivityLog::class, User::class]);

test('personnel management is disabled', function () {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    expect(Gate::forUser($actor)->allows('viewAny', User::class))->toBeFalse();
    expect(Gate::forUser($actor)->allows('create', User::class))->toBeFalse();
    expect(Gate::forUser($actor)->allows('update', $subject))->toBeFalse();
    expect(Gate::forUser($actor)->allows('delete', $subject))->toBeFalse();
});

test('referenced equipment and catalog entries cannot be deleted', function () {
    $actor = User::factory()->create();
    $item = BorrowItem::factory()->create();
    $equipment = $item->equipment;

    expect(Gate::forUser($actor)->allows('delete', $equipment))->toBeFalse();
    expect(Gate::forUser($actor)->allows('delete', $equipment->category))->toBeFalse();
    expect(Gate::forUser($actor)->allows('delete', $equipment->location))->toBeFalse();
});

test('activity logs are read only even for the admin', function () {
    $actor = User::factory()->create();
    $log = ActivityLog::factory()->create();

    expect(Gate::forUser($actor)->allows('view', $log))->toBeTrue();
    expect(Gate::forUser($actor)->allows('create', ActivityLog::class))->toBeFalse();
    expect(Gate::forUser($actor)->allows('update', $log))->toBeFalse();
    expect(Gate::forUser($actor)->allows('delete', $log))->toBeFalse();
});
