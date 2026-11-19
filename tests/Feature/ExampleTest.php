<?php

use App\Models\User;

test('the root URL redirects guests to login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the root URL redirects authenticated users to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))->assertRedirect(route('dashboard'));
});
