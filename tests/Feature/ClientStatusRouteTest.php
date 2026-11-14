<?php

use App\Models\City;
use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginClientStatusRouteUser(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

function forgetClientStatusRouteGuards(): void
{
    test()->flushHeaders();
    app('auth')->forgetGuards();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientStatusRoutePayload(array $overrides = []): array
{
    $city = City::where('name_city', 'Ocaña')->firstOrFail();

    return array_merge([
        'client_name1' => 'Cliente',
        'client_last_name1' => 'Activo',
        'business_name' => 'Negocio Activo',
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

it('removes only the deactivated client route and keeps its seller', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $otherRoutesBefore = DeliveryRoute::where('id_client', '!=', 'CLI001')
        ->orderBy('id_ruta')
        ->get()
        ->map(fn (DeliveryRoute $route) => [$route->id_ruta, $route->id_client, $route->document_employee])
        ->all();

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk()
        ->assertJsonPath('message', 'Estado del cliente actualizado correctamente.')
        ->assertJsonPath('client.id_client', 'CLI001')
        ->assertJsonPath('client.status', false)
        ->assertJsonPath('client.document_employee', '1000000002');

    $otherRoutesAfter = DeliveryRoute::where('id_client', '!=', 'CLI001')
        ->orderBy('id_ruta')
        ->get()
        ->map(fn (DeliveryRoute $route) => [$route->id_ruta, $route->id_client, $route->document_employee])
        ->all();

    expect(Client::find('CLI001')->document_employee)->toBe('1000000002')
        ->and(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0)
        ->and($otherRoutesAfter)->toBe($otherRoutesBefore);
});

it('hides the deactivated client from the vendor routes and navigate', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');
    $vendorToken = loginClientStatusRouteUser('vendedor@misterchef.com', 'vendedor123');

    $before = $this->withToken($vendorToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    $beforeGroup = collect($before)->firstWhere('document_employee', '1000000002');

    expect($beforeGroup['total_clients'])->toBe(3);

    forgetClientStatusRouteGuards();

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk();

    forgetClientStatusRouteGuards();

    $after = $this->withToken($vendorToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    $afterGroup = collect($after)->firstWhere('document_employee', '1000000002');

    expect($afterGroup['total_clients'])->toBe(2)
        ->and(collect($afterGroup['clients'])->pluck('id_client')->all())->not->toContain('CLI001');

    $this->withToken($vendorToken)
        ->getJson('/api/v1/routes/navigate/CLI001')
        ->assertStatus(422)
        ->assertJson(['message' => 'Este cliente está desactivado.']);
});

it('restores exactly one route when reactivating the client', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk();

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => true])
        ->assertOk()
        ->assertJsonPath('client.status', true)
        ->assertJsonPath('client.document_employee', '1000000002');

    $routes = DeliveryRoute::where('id_client', 'CLI001')->get();

    expect($routes)->toHaveCount(1)
        ->and($routes->first()->document_employee)->toBe('1000000002');

    forgetClientStatusRouteGuards();

    $vendorToken = loginClientStatusRouteUser('vendedor@misterchef.com', 'vendedor123');

    $vendorRoutes = $this->withToken($vendorToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    expect(collect($vendorRoutes)->pluck('clients')->flatten(1)->pluck('id_client')->all())->toContain('CLI001');
});

it('does not create a route when reactivating with an inactive seller', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk();

    Employee::whereKey('1000000002')->update(['status' => 'I']);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => true])
        ->assertOk()
        ->assertJsonPath('client.status', true);

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0);
});

it('does not create a route when reactivating a client without seller', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk();

    Client::whereKey('CLI001')->update(['document_employee' => null]);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => true])
        ->assertOk()
        ->assertJsonPath('client.status', true)
        ->assertJsonPath('client.document_employee', null);

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0);
});

it('is idempotent when deactivating and reactivating twice', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk();

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk();

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => true])
        ->assertOk();

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(1);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => true])
        ->assertOk();

    expect(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(1)
        ->and(DeliveryRoute::where('id_client', 'CLI001')->first()->document_employee)->toBe('1000000002');
});

it('hides a stale route for an inactive client and sanitizes it on reactivation', function () {
    Client::whereKey('CLI001')->update(['status' => false]);

    DeliveryRoute::where('id_client', 'CLI001')->delete();
    DeliveryRoute::create([
        'id_ruta' => 'RTOLD001',
        'id_client' => 'CLI001',
        'document_employee' => '1000000002',
    ]);

    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');
    $vendorToken = loginClientStatusRouteUser('vendedor@misterchef.com', 'vendedor123');

    $routes = $this->withToken($vendorToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    $group = collect($routes)->firstWhere('document_employee', '1000000002');

    expect(collect($group['clients'])->pluck('id_client')->all())->not->toContain('CLI001')
        ->and($group['total_clients'])->toBe(2);

    $this->withToken($vendorToken)
        ->getJson('/api/v1/routes/navigate/CLI001')
        ->assertStatus(422)
        ->assertJson(['message' => 'Este cliente está desactivado.']);

    forgetClientStatusRouteGuards();

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => true])
        ->assertOk();

    $routesAfter = DeliveryRoute::where('id_client', 'CLI001')->get();

    expect($routesAfter)->toHaveCount(1)
        ->and($routesAfter->first()->document_employee)->toBe('1000000002')
        ->and($routesAfter->first()->id_ruta)->not->toBe('RTOLD001');

    forgetClientStatusRouteGuards();

    $vendorRoutes = $this->withToken($vendorToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    expect(collect($vendorRoutes)->pluck('clients')->flatten(1)->pluck('id_client')->all())->toContain('CLI001');
});

it('lets a vendor deactivate their own client but not another vendor client', function () {
    $vendorToken = loginClientStatusRouteUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($vendorToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertOk()
        ->assertJsonPath('client.status', false);

    expect(Client::find('CLI001')->document_employee)->toBe('1000000002')
        ->and(DeliveryRoute::where('id_client', 'CLI001')->count())->toBe(0);

    $otherRouteBefore = DeliveryRoute::where('id_client', 'CLI005')->first();

    $this->withToken($vendorToken)
        ->patchJson('/api/v1/clients/CLI005/status', ['status' => false])
        ->assertForbidden()
        ->assertJson(['message' => 'No tienes permiso para actualizar este cliente.']);

    $otherRouteAfter = DeliveryRoute::where('id_client', 'CLI005')->first();

    expect(Client::find('CLI005')->status)->toBeTrue()
        ->and($otherRouteAfter)->not->toBeNull()
        ->and($otherRouteAfter->id_ruta)->toBe($otherRouteBefore->id_ruta)
        ->and($otherRouteAfter->document_employee)->toBe($otherRouteBefore->document_employee);

    forgetClientStatusRouteGuards();

    $this->patchJson('/api/v1/clients/CLI001/status', ['status' => false])
        ->assertUnauthorized();
});

it('does not create a route when assigning a seller to an inactive client until reactivation', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI004/status', ['status' => false])
        ->assertOk();

    $this->withToken($adminToken)
        ->putJson('/api/v1/clients/CLI004', ['document_employee' => '1000000003'])
        ->assertOk()
        ->assertJsonPath('client.status', false)
        ->assertJsonPath('client.document_employee', '1000000003');

    expect(DeliveryRoute::where('id_client', 'CLI004')->count())->toBe(0);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI004/status', ['status' => true])
        ->assertOk()
        ->assertJsonPath('client.status', true);

    $routes = DeliveryRoute::where('id_client', 'CLI004')->get();

    expect($routes)->toHaveCount(1)
        ->and($routes->first()->document_employee)->toBe('1000000003');
});

it('returns an empty array when all of a vendor clients are deactivated', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    foreach (['CLI001', 'CLI002', 'CLI003'] as $clientId) {
        $this->withToken($adminToken)
            ->patchJson("/api/v1/clients/{$clientId}/status", ['status' => false])
            ->assertOk();
    }

    forgetClientStatusRouteGuards();

    $vendorToken = loginClientStatusRouteUser('vendedor@misterchef.com', 'vendedor123');

    $routes = $this->withToken($vendorToken)
        ->getJson('/api/v1/routes')
        ->assertOk()
        ->json();

    expect($routes)->toBe([]);
});

it('keeps 404 and 422 responses for the status endpoint', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI999/status', ['status' => false])
        ->assertNotFound()
        ->assertJson(['message' => 'Cliente no encontrado.']);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', ['status' => 'no-es-booleano'])
        ->assertStatus(422);

    $this->withToken($adminToken)
        ->patchJson('/api/v1/clients/CLI001/status', [])
        ->assertStatus(422);
});

it('keeps the new 403 message for show', function () {
    $vendorToken = loginClientStatusRouteUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($vendorToken)
        ->getJson('/api/v1/clients/CLI005')
        ->assertForbidden()
        ->assertJson(['message' => 'No tienes permiso para ver este cliente.']);

    $this->withToken($vendorToken)
        ->getJson('/api/v1/clients/CLI001')
        ->assertOk()
        ->assertJsonPath('id_client', 'CLI001');
});

it('creates an active client with a synchronized route', function () {
    $adminToken = loginClientStatusRouteUser('admin@misterchef.com', 'admin123');

    $client = $this->withToken($adminToken)
        ->postJson('/api/v1/clients', clientStatusRoutePayload([
            'document_employee' => '1000000002',
        ]))
        ->assertCreated()
        ->json('client');

    expect($client['status'])->toBeTrue()
        ->and(DeliveryRoute::where('id_client', $client['id_client'])->count())->toBe(1)
        ->and(DeliveryRoute::where('id_client', $client['id_client'])->first()->document_employee)->toBe('1000000002');
});
