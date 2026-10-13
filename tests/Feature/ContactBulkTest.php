<?php

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates contacts posted in bulk', function () {
    $response = $this->postJson('/api/contacts/bulk', [
        'contacts' => [
            ['name' => 'Mazen', 'email' => 'mazen@test.com', 'phone' => '01000000001', 'age' => 28],
            ['name' => 'Ahmed', 'email' => 'ahmed@test.com'],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJson(['message' => 'Contacts saved.', 'created' => 2, 'updated' => 0]);

    $this->assertDatabaseCount('contacts', 2);
    $this->assertDatabaseHas('contacts', ['email' => 'mazen@test.com', 'age' => 28]);
    $this->assertDatabaseHas('contacts', ['email' => 'ahmed@test.com', 'phone' => null, 'age' => null]);
});

it('updates existing emails instead of duplicating them', function () {
    Contact::create(['name' => 'Mazen', 'email' => 'mazen@test.com', 'phone' => '01000000001', 'age' => 28]);

    $response = $this->postJson('/api/contacts/bulk', [
        'contacts' => [
            ['name' => 'Mazen Updated', 'email' => 'mazen@test.com', 'age' => 30],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJson(['created' => 0, 'updated' => 1]);

    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseHas('contacts', ['name' => 'Mazen Updated', 'age' => 30]);
});

it('rejects invalid payloads', function () {
    $this->postJson('/api/contacts/bulk', ['contacts' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('contacts');

    $this->postJson('/api/contacts/bulk', [
        'contacts' => [['name' => 'No Mail', 'email' => 'not-an-email']],
    ])->assertStatus(422)->assertJsonValidationErrors('contacts.0.email');

    $this->assertDatabaseCount('contacts', 0);
});

it('lists saved contacts', function () {
    Contact::create(['name' => 'Mazen', 'email' => 'mazen@test.com']);

    $this->getJson('/api/contacts')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.email', 'mazen@test.com');
});
