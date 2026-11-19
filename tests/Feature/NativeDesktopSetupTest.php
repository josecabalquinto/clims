<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\User;
use Database\Seeders\InventoryReferenceSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    config(['nativephp-internal.running' => true]);
});

test('a fresh native desktop database redirects to one-time setup', function () {
    $this->get(route('home'))->assertRedirect(route('setup'));
    $this->get(route('setup'))->assertOk()->assertSee('Create the administrator');
});

test('inventory references can be seeded in the native production environment', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    app(InventoryReferenceSeeder::class)->run();

    $this->assertDatabaseCount('equipment_categories', 9);
    $this->assertDatabaseCount('equipment_locations', 1);
});

test('the desktop setup creates and signs in the first administrator', function () {
    $page = Livewire::test('pages::setup')
        ->set('name', 'Desktop Administrator')
        ->set('email', ' ADMIN@EXAMPLE.TEST ')
        ->call('save', 'StrongDesktopPassword!234', 'StrongDesktopPassword!234')
        ->assertHasNoErrors();

    $user = User::sole();
    $page->assertRedirect(route('dashboard'));
    expect($user->name)->toBe('Desktop Administrator')
        ->and($user->email)->toBe('admin@example.test')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('StrongDesktopPassword!234', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'user.provisioned',
        'subject_id' => $user->id,
        'user_id' => null,
    ]);
    expect(ActivityLog::sole()->new_values)->not->toHaveKey('password');
    expect(EquipmentCategory::query()->orderBy('name')->pluck('name')->all())->toBe([
        'Keyboard',
        'Monitor',
        'Mouse',
        'Network Equipment',
        'Others',
        'Printer',
        'Projector',
        'System Unit',
        'UPS',
    ])->and(EquipmentLocation::query()->pluck('name')->all())->toBe(['Computer Lab 2']);
});

test('desktop setup validates password strength without exposing it as component state', function () {
    $page = Livewire::test('pages::setup')
        ->set('name', 'Desktop Administrator')
        ->set('email', 'admin@example.test')
        ->call('save', 'weak', 'weak')
        ->assertHasErrors('password');

    expect($page->get('name'))->toBe('Desktop Administrator')
        ->and(property_exists($page->instance(), 'password'))->toBeFalse()
        ->and(property_exists($page->instance(), 'password_confirmation'))->toBeFalse();
    $this->assertDatabaseCount('users', 0);
});

test('setup is unavailable outside NativePHP or after an account exists', function () {
    config(['nativephp-internal.running' => false]);
    $this->get(route('setup'))->assertNotFound();

    config(['nativephp-internal.running' => true]);
    User::factory()->create();
    $this->get(route('setup'))->assertNotFound();
    $this->get(route('home'))->assertRedirect(route('login'));
});
