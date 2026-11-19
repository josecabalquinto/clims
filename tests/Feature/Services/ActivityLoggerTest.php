<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\MassAssignmentException;

test('activity logging strips credentials and unapproved fields', function () {
    $actor = User::factory()->create();
    $values = ['name' => 'New name', 'password' => 'secret', 'remember_token' => 'token', 'two_factor_secret' => 'secret', 'other_token' => 'secret'];

    $log = app(ActivityLogger::class)->record($actor, 'user.updated', $actor, $values, $values);

    expect($log->fresh()->old_values)->toBe(['name' => 'New name']);
    expect($log->fresh()->new_values)->toBe(['name' => 'New name']);
    expect($actor->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

test('activity records cannot be edited through Eloquent', function () {
    $log = ActivityLog::factory()->create();

    expect(fn () => $log->update(['action' => 'changed']))->toThrow(MassAssignmentException::class);
    $log->action = 'changed';
    expect(fn () => $log->save())->toThrow(LogicException::class);
    $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'action' => 'user.created']);
});

test('activity records cannot be deleted through Eloquent', function () {
    $log = ActivityLog::factory()->create();

    expect(fn () => $log->delete())->toThrow(LogicException::class);
    $this->assertModelExists($log);
});
