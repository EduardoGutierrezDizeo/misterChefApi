<?php

use App\Models\City;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function loginClientVisibilityUser(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

function createUnassignedClientForVisibility(string $idClient = 'CLI900'): Client
{
    $city = City::where('name_city', 'Ocaña')->firstOrFail();

    return Client::create([
        'id_client' => $idClient,
        'client_name1' => 'Cliente',
        'client_last_name1' => 'Sin Vendedor',
        'business_name' => 'Negocio Sin Vendedor',
        'address' => 'Cll 1 # 2-3, Ocaña',
        'longitude' => -73.2600,
        'latitude' => 8.2400,
        'phone_number' => '3000000000',
        'status' => true,
        'document_employee' => null,
        'id_departament' => $city->id_departament,
        'id_city' => $city->id_city,
    ]);
}

function forgetClientVisibilityGuards(): void
{
    test()->flushHeaders();
    app('auth')->forgetGuards();
}

beforeEach(function () {
    $this->seed();
});

it('shows each vendor only the clients assigned to them', function () {
    $vendorOneToken = loginClientVisibilityUser('vendedor@misterchef.com', 'vendedor123');

    $vendorOneClients = $this->withToken($vendorOneToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    $expectedVendorOne = Client::where('document_employee', '1000000002')
        ->orderBy('id_client')
        ->pluck('id_client')
        ->all();

    expect(collect($vendorOneClients)->pluck('id_client')->sort()->values()->all())->toBe($expectedVendorOne)
        ->and(collect($vendorOneClients)->pluck('document_employee')->unique()->values()->all())->toBe(['1000000002']);

    forgetClientVisibilityGuards();

    $vendorTwoToken = loginClientVisibilityUser('vendedor2@misterchef.com', 'vendedor123');

    $vendorTwoClients = $this->withToken($vendorTwoToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    $expectedVendorTwo = Client::where('document_employee', '1000000003')
        ->orderBy('id_client')
        ->pluck('id_client')
        ->all();

    expect(collect($vendorTwoClients)->pluck('id_client')->sort()->values()->all())->toBe($expectedVendorTwo)
        ->and(collect($vendorTwoClients)->pluck('document_employee')->unique()->values()->all())->toBe(['1000000003'])
        ->and(collect($vendorOneClients)->pluck('id_client')->intersect(collect($vendorTwoClients)->pluck('id_client'))->all())->toBeEmpty();
});

it('shows every client to the administrator', function () {
    $token = loginClientVisibilityUser('admin@misterchef.com', 'admin123');

    $clients = $this->withToken($token)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    expect(collect($clients)->pluck('id_client')->sort()->values()->all())
        ->toBe(Client::orderBy('id_client')->pluck('id_client')->all());
});

it('returns an empty array for a vendor without clients', function () {
    Employee::create([
        'document_employee' => '1000000009',
        'name_1' => 'Vendedor',
        'last_name_1' => 'Sin Clientes',
        'email' => 'sin.clientes@misterchef.com',
        'password' => Hash::make('vendedor123'),
        'type' => 'V',
        'status' => 'A',
    ]);

    $token = loginClientVisibilityUser('sin.clientes@misterchef.com', 'vendedor123');

    $clients = $this->withToken($token)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    expect($clients)->toBe([]);
});

it('hides clients without a seller from every vendor but not from the administrator', function () {
    createUnassignedClientForVisibility();

    $vendorOneToken = loginClientVisibilityUser('vendedor@misterchef.com', 'vendedor123');

    $vendorOneClients = $this->withToken($vendorOneToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    expect(collect($vendorOneClients)->pluck('id_client')->all())->not->toContain('CLI900');

    forgetClientVisibilityGuards();

    $vendorTwoToken = loginClientVisibilityUser('vendedor2@misterchef.com', 'vendedor123');

    $vendorTwoClients = $this->withToken($vendorTwoToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    expect(collect($vendorTwoClients)->pluck('id_client')->all())->not->toContain('CLI900');

    forgetClientVisibilityGuards();

    $adminToken = loginClientVisibilityUser('admin@misterchef.com', 'admin123');

    $adminClients = $this->withToken($adminToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    $unassignedClient = collect($adminClients)->firstWhere('id_client', 'CLI900');

    expect($unassignedClient)->not->toBeNull()
        ->and($unassignedClient['document_employee'])->toBeNull();
});

it('moves client visibility when an administrator reassigns it', function () {
    $adminToken = loginClientVisibilityUser('admin@misterchef.com', 'admin123');

    $this->withToken($adminToken)
        ->putJson('/api/v1/clients/CLI001', [
            'document_employee' => '1000000003',
        ])
        ->assertOk();

    forgetClientVisibilityGuards();

    $previousVendorToken = loginClientVisibilityUser('vendedor@misterchef.com', 'vendedor123');

    $previousVendorClients = $this->withToken($previousVendorToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    expect(collect($previousVendorClients)->pluck('id_client')->all())->not->toContain('CLI001');

    forgetClientVisibilityGuards();

    $newVendorToken = loginClientVisibilityUser('vendedor2@misterchef.com', 'vendedor123');

    $newVendorClients = $this->withToken($newVendorToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    expect(collect($newVendorClients)->pluck('id_client')->all())->toContain('CLI001');
});

it('forbids a vendor from viewing another vendor client details', function () {
    $token = loginClientVisibilityUser('vendedor@misterchef.com', 'vendedor123');

    $response = $this->withToken($token)
        ->getJson('/api/v1/clients/CLI005')
        ->assertForbidden()
        ->assertJson([
            'message' => 'No tienes permiso para ver este cliente.',
        ]);

    expect($response->json())->toBe([
        'message' => 'No tienes permiso para ver este cliente.',
    ]);
});

it('forbids a vendor from viewing the details of an unassigned client', function () {
    createUnassignedClientForVisibility();

    $token = loginClientVisibilityUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($token)
        ->getJson('/api/v1/clients/CLI900')
        ->assertForbidden()
        ->assertJson([
            'message' => 'No tienes permiso para ver este cliente.',
        ]);
});

it('allows a vendor to view their own client details', function () {
    $token = loginClientVisibilityUser('vendedor@misterchef.com', 'vendedor123');

    $client = $this->withToken($token)
        ->getJson('/api/v1/clients/CLI001')
        ->assertOk()
        ->json();

    expect($client['id_client'])->toBe('CLI001')
        ->and($client['document_employee'])->toBe('1000000002');
});

it('allows the administrator to view any client details', function () {
    $token = loginClientVisibilityUser('admin@misterchef.com', 'admin123');

    $client = $this->withToken($token)
        ->getJson('/api/v1/clients/CLI005')
        ->assertOk()
        ->json();

    expect($client['id_client'])->toBe('CLI005')
        ->and($client['document_employee'])->toBe('1000000003');
});

it('requires authentication for both client endpoints', function () {
    $this->getJson('/api/v1/clients')->assertUnauthorized();
    $this->getJson('/api/v1/clients/CLI001')->assertUnauthorized();
});

it('keeps the same client element format for vendors and administrators', function () {
    $adminToken = loginClientVisibilityUser('admin@misterchef.com', 'admin123');

    $adminClients = $this->withToken($adminToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    $adminClient = collect($adminClients)->firstWhere('id_client', 'CLI001');

    forgetClientVisibilityGuards();

    $vendorToken = loginClientVisibilityUser('vendedor@misterchef.com', 'vendedor123');

    $vendorClients = $this->withToken($vendorToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->json();

    $vendorClient = collect($vendorClients)->firstWhere('id_client', 'CLI001');

    expect($vendorClient)->not->toBeNull()
        ->and(array_keys($vendorClient))->toBe(array_keys($adminClient));
});
