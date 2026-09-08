<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

it('logs in with valid credentials', function () {
    registerTenantOwner('sam@example.com');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sam@example.com',
        'password' => 'password',
    ])->assertOk()->assertJsonPath('data.email', 'sam@example.com');
});

it('rejects invalid credentials', function () {
    registerTenantOwner('sam@example.com');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sam@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('returns the authenticated user from /me', function () {
    [$user] = registerTenantOwner();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('requires authentication for /me', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
