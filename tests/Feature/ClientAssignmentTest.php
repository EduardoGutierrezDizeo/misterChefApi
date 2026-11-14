<?php

use App\Models\City;
use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function loginClientAssignmentUser(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientAssignmentPayload(array $overrides = []): array
{
    $city = City::where('name_city', 'Ocaña')->firstOrFail();

    return array_merge([
        'client_name1' => 'Cliente',
        'client_last_name1' => 'Prueba',
        'business_name' => 'Negocio Prueba',
        'address' => 'Cll 1 # 2-3, Ocaña',
        'longitude' => -73.2600,
        'latitude' => 8.2400,
        'phone_number' => '3000000000',
        'status' => true,
        'id_departament' => $city->id_departament,
        'id_city' => $city->id_city,
    ], $overrides);
}

beforeEach(function () {
    $this->seed();
});

it('updates the route when an administrator changes the seller', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '1000000003',
        ])
        ->assertOk();

    $routes = DeliveryRoute::where('id_client', 'CLI001')->get();

    expect(Client::find('CLI001')->document_employee)->toBe('1000000003')
        ->and($routes)->toHaveCount(1)
        ->and($routes->first()->document_employee)->toBe('1000000003');
});

it('creates a route when an administrator creates a client with a seller', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $client = $this->withToken($token)
        ->postJson('/api/v1/clients', clientAssignmentPayload([
            'document_employee' => '1000000002',
        ]))
        ->assertCreated()
        ->json('client');

    $routes = DeliveryRoute::where('id_client', $client['id_client'])->get();

    expect($client['document_employee'])->toBe('1000000002')
        ->and($routes)->toHaveCount(1)
        ->and($routes->first()->document_employee)->toBe('1000000002');
});

it('shows the reassigned client to the destination seller', function () {
    $adminToken = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '1000000003',
        ])
        ->assertOk();

    // Cada petición real usa un guard nuevo; en tests hay que limpiar el token y el guard cacheados.
    $this->flushHeaders();
    $this->app['auth']->forgetGuards();

    $sellerToken = loginClientAssignmentUser('vendedor2@misterchef.com', 'vendedor123');

    $routes = $this->withToken($sellerToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    $sellerRoute = collect($routes)->firstWhere('document_employee', '1000000003');

    expect($sellerRoute)->not->toBeNull()
        ->and(collect($sellerRoute['clients'])->pluck('id_client')->all())->toContain('CLI001');
});

it('rejects assigning an inactive seller', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '1000000004',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor seleccionado no existe o no está activo.',
        ]);

    $this->withToken($token)
        ->postJson('/api/v1/clients', clientAssignmentPayload([
            'document_employee' => '1000000004',
        ]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor seleccionado no existe o no está activo.',
        ]);
});

it('rejects assigning an administrator as seller', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '1000000001',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor seleccionado no existe o no está activo.',
        ]);

    $this->withToken($token)
        ->postJson('/api/v1/clients', clientAssignmentPayload([
            'document_employee' => '1000000001',
        ]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor seleccionado no existe o no está activo.',
        ]);
});

it('rejects assigning a document that does not exist', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '9999999999',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor seleccionado no existe o no está activo.',
        ]);

    $this->withToken($token)
        ->postJson('/api/v1/clients', clientAssignmentPayload([
            'document_employee' => '9999999999',
        ]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor seleccionado no existe o no está activo.',
        ]);
});

it('removes the route when the client is left without a seller', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(1);

    $this->withToken($token)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => null,
        ])
        ->assertOk();

    expect(Client::find('CLI001')->document_employee)->toBeNull()
        ->and(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0);
});

it('does not duplicate the route when saving the same seller', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '1000000002',
        ])
        ->assertOk();

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(1);
});

it('does not create a route when the client is created without a seller', function () {
    $token = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $client = $this->withToken($token)
        ->postJson('/api/v1/clients', clientAssignmentPayload([
            'document_employee' => null,
        ]))
        ->assertCreated()
        ->json('client');

    expect($client['document_employee'])->toBeNull()
        ->and(DeliveryRoute::where('id_client', $client['id_client'])->count())->toBe(0);
});

it('shows a client assigned to a new seller in the routes endpoint', function () {
    Employee::create([
        'document_employee' => '1000000009',
        'name_1' => 'Nuevo',
        'last_name_1' => 'Vendedor',
        'email' => 'nuevo.vendedor@misterchef.com',
        'password' => Hash::make('vendedor123'),
        'type' => 'V',
        'status' => 'A',
    ]);

    $adminToken = loginClientAssignmentUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '1000000009',
        ])
        ->assertOk();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();

    $sellerToken = loginClientAssignmentUser('nuevo.vendedor@misterchef.com', 'vendedor123');

    $routes = $this->withToken($sellerToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    $sellerRoute = collect($routes)->firstWhere('document_employee', '1000000009');

    expect($sellerRoute)->not->toBeNull()
        ->and(collect($sellerRoute['clients'])->pluck('id_client')->all())->toContain('CLI001');
});
