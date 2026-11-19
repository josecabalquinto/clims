<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the first admin is provisioned with a hashed password and a safe audit record', function () {
    $this->artisan('clims:provision-admin')
        ->expectsQuestion('Name', 'Laboratory Administrator')
        ->expectsQuestion('Email', 'administrator@example.test')
        ->expectsQuestion('Password (at least 12 characters)', 'StrongTestPassword!234')
        ->expectsQuestion('Confirm password', 'StrongTestPassword!234')
        ->expectsOutput('Admin created. Sign in using the credentials you entered.')
        ->assertSuccessful();

    $user = User::where('email', 'administrator@example.test')->sole();
    expect($user->isAdmin())->toBeTrue();
    expect(Hash::check('StrongTestPassword!234', $user->password))->toBeTrue();
    $this->assertDatabaseHas('activity_logs', ['action' => 'user.provisioned', 'subject_id' => $user->id, 'user_id' => null]);
    expect(ActivityLog::sole()->new_values)->not->toHaveKey('password');
});

test('initial provisioning refuses to add another administrator', function () {
    User::factory()->superAdmin()->create();

    $this->artisan('clims:provision-admin')
        ->expectsOutput('An active Admin already exists.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('provisioning rejects mismatched passwords without creating an account', function () {
    $this->artisan('clims:provision-admin')
        ->expectsQuestion('Name', 'Laboratory Administrator')
        ->expectsQuestion('Email', 'administrator@example.test')
        ->expectsQuestion('Password (at least 12 characters)', 'StrongTestPassword!234')
        ->expectsQuestion('Confirm password', 'DifferentPassword!234')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});
