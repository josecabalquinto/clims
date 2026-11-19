<?php

use App\Models\User;
use Livewire\Livewire;

test('module pages redirect guests to login', function (string $name) {
    $this->get(route($name))->assertRedirect(route('login'));
})->with(['equipment.index', 'equipment.categories', 'borrowing.index', 'activity-logs.index', 'profile.edit']);

test('the active admin can open every application module', function (string $name) {
    $this->actingAs(User::factory()->create())->get(route($name))->assertOk();
})->with(['dashboard', 'equipment.index', 'equipment.categories', 'borrowing.index', 'activity-logs.index', 'profile.edit', 'appearance.edit']);

test('retired location and user management routes are unavailable', function (string $uri) {
    $this->actingAs(User::factory()->create())->get($uri)->assertNotFound();
})->with(['/equipment/locations', '/users', '/users/create']);

test('sidebar contains only the required modules', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Equipment')
        ->assertSee('Borrow Log')
        ->assertSee('View Log Details')
        ->assertSee('Settings')
        ->assertDontSee('User Management')
        ->assertDontSee('Equipment Locations');
});

test('inactive accounts cannot open protected pages', function (string $name) {
    $this->actingAs(User::factory()->inactive()->create())->get(route($name))->assertForbidden();

    $this->assertGuest();
})->with(['dashboard', 'equipment.index', 'equipment.categories', 'borrowing.index', 'activity-logs.index', 'profile.edit', 'security.edit', 'appearance.edit']);

test('inactive accounts cannot sign in even with a valid password', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('public registration remains unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('deactivation blocks an update from a page opened while active', function () {
    $user = User::factory()->create();
    $page = $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    User::whereKey($user->id)->update(['is_active' => false]);

    $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => ['name' => 'Unauthorized change'],
            'calls' => [['path' => '', 'method' => 'updateProfileInformation', 'params' => []]],
        ]],
    ])->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'is_active' => false]);
    $this->assertGuest();
});
