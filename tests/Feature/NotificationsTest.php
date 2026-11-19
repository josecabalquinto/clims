<?php

use App\Models\Equipment;
use App\Models\User;
use Livewire\Livewire;

test('redirect messages are carried by the shared notification payload instead of an inline banner', function () {
    $equipment = Equipment::factory()->create();
    $response = $this->actingAs(User::factory()->create())
        ->withSession(['equipment-status' => 'Equipment registered.'])
        ->get(route('equipment.show', $equipment))->assertOk();
    $response->assertSee('data-clims-notifications', false)->assertDontSee('role="status"', false);
    preg_match('/<template data-clims-notifications>(.*?)<\/template>/s', $response->getContent(), $matches);
    expect(json_decode(html_entity_decode($matches[1]), true))->toBe([
        ['type' => 'success', 'message' => 'Equipment registered.'],
    ]);
});

test('authentication status uses the notification payload with safe text encoding', function () {
    $response = $this->withSession(['status' => '<img src=x onerror=alert(1)>'])
        ->get(route('login'))->assertOk();
    $response->assertDontSee('<img src=x onerror=alert(1)>', false);
    preg_match('/<template data-clims-notifications>(.*?)<\/template>/s', $response->getContent(), $matches);
    expect(json_decode(html_entity_decode($matches[1]), true))->toBe([
        ['type' => 'success', 'message' => '<img src=x onerror=alert(1)>'],
    ]);
});

test('failed authentication is included in the shared error notification payload', function () {
    $response = $this->followingRedirects()->from(route('login'))
        ->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'invalid-password'])->assertOk();
    preg_match('/<template data-clims-notifications>(.*?)<\/template>/s', $response->getContent(), $matches);
    expect(json_decode(html_entity_decode($matches[1]), true))->toBe([
        ['type' => 'error', 'message' => __('auth.failed')],
    ]);
});

test('settings updates dispatch a shared success notification', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Livewire::test('pages::settings.profile')->set('name', 'Updated Staff')->call('updateProfileInformation')
        ->assertHasNoErrors()->assertDispatched('notify', type: 'success', message: 'Profile updated.');
});
