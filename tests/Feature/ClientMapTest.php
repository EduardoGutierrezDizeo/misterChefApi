<?php

use App\Models\City;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginClientMapUser(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createClientForMap(array $overrides = []): Client
{
    $city = City::where('name_city', 'Ocaña')->firstOrFail();

    return Client::create(array_merge([
        'id_client' => 'CLI900',
        'client_name1' => 'Cliente',
        'client_last_name1' => 'Prueba',
        'business_name' => 'Negocio Prueba',
        'address' => 'Cll 1 # 2-3, Ocaña',
        'latitude' => 8.2400,
        'longitude' => -73.2600,
        'phone_number' => '3000000000',
        'status' => true,
        'document_employee' => null,
        'id_departament' => $city->id_departament,
        'id_city' => $city->id_city,
    ], $overrides));
}

beforeEach(function () {
    $this->seed();
});

it('returns the active clients with coordinates for the admin map', function () {
    $token = loginClientMapUser('admin@misterchef.com', 'admin123');

    $clients = $this->withToken($token)->getJson('/api/v1/clients/map')
        ->assertOk()
        ->json();

    expect($clients)->toHaveCount(8);

    foreach ($clients as $client) {
        expect($client)->toHaveCount(8)
            ->toHaveKeys([
                'id_client',
                'business_name',
                'address',
                'phone_number',
                'latitude',
                'longitude',
                'document_employee',
                'seller_name',
            ]);
    }
});

it('excludes deactivated clients from the map', function () {
    Client::where('id_client', 'CLI001')->update(['status' => false]);

    $token = loginClientMapUser('admin@misterchef.com', 'admin123');

    $clients = $this->withToken($token)->getJson('/api/v1/clients/map')
        ->assertOk()
        ->json();

    expect($clients)->toHaveCount(7)
        ->and(collect($clients)->pluck('id_client')->all())->not->toContain('CLI001');
});

it('excludes clients without coordinates from the map', function () {
    Client::where('id_client', 'CLI002')->update([
        'latitude' => null,
        'longitude' => null,
    ]);

    $token = loginClientMapUser('admin@misterchef.com', 'admin123');

    $clients = $this->withToken($token)->getJson('/api/v1/clients/map')
        ->assertOk()
        ->json();

    expect($clients)->toHaveCount(7)
        ->and(collect($clients)->pluck('id_client')->all())->not->toContain('CLI002');
});

it('includes clients without a seller with null seller data', function () {
    createClientForMap(['id_client' => 'CLI901']);

    $token = loginClientMapUser('admin@misterchef.com', 'admin123');

    $clients = $this->withToken($token)->getJson('/api/v1/clients/map')
        ->assertOk()
        ->json();

    $row = collect($clients)->firstWhere('id_client', 'CLI901');

    expect($row)->not->toBeNull()
        ->and($row['document_employee'])->toBeNull()
        ->and($row['seller_name'])->toBeNull();
});

it('returns the full seller name for clients with a seller', function () {
    $token = loginClientMapUser('admin@misterchef.com', 'admin123');

    $clients = $this->withToken($token)->getJson('/api/v1/clients/map')
        ->assertOk()
        ->json();

    $row = collect($clients)->firstWhere('id_client', 'CLI001');

    expect($row)->not->toBeNull()
        ->and($row['document_employee'])->toBe('1000000002')
        ->and($row['seller_name'])->toBe('Jorge Luis Martínez Cruz');
});

it('forbids vendors from viewing the client map', function () {
    $token = loginClientMapUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($token)->getJson('/api/v1/clients/map')
        ->assertForbidden()
        ->assertJson([
            'message' => 'Solo los administradores pueden ver el mapa de clientes.',
        ]);
});

it('requires authentication to view the client map', function () {
    $this->getJson('/api/v1/clients/map')->assertUnauthorized();
});
