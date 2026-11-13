<?php

use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use App\Models\RouteSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function loginRouteDistributionUser(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

/**
 * @return array<string, mixed>
 */
function routeDistributionSnapshot(): array
{
    return [
        'clients' => Client::orderBy('id_client')->get()->map(fn (Client $client) => [
            'id_client' => $client->id_client,
            'document_employee' => $client->document_employee,
            'status' => $client->status,
            'latitude' => $client->latitude,
            'longitude' => $client->longitude,
        ])->all(),
        'routes' => DeliveryRoute::orderBy('id_ruta')->get()->map(fn (DeliveryRoute $route) => [
            'id_ruta' => $route->id_ruta,
            'id_client' => $route->id_client,
            'document_employee' => $route->document_employee,
        ])->all(),
        'suggestions' => RouteSuggestion::orderBy('id_suggestion')->get()->map(fn (RouteSuggestion $suggestion) => [
            'id_suggestion' => $suggestion->id_suggestion,
            'id_client' => $suggestion->id_client,
            'document_employee' => $suggestion->document_employee,
            'status' => $suggestion->status,
            'resolved_at' => $suggestion->resolved_at?->toDateTimeString(),
            'resolved_by' => $suggestion->resolved_by,
        ])->all(),
    ];
}

/**
 * @param  array<string, mixed>  $preview
 * @return array<string, string>
 */
function routeDistributionAssignments(array $preview): array
{
    $pairs = [];

    foreach ($preview['sellers'] as $seller) {
        foreach ($seller['clients'] as $client) {
            $pairs[$client['id_client']] = $seller['document_employee'];
        }
    }

    ksort($pairs, SORT_STRING);

    return $pairs;
}

/**
 * @return array<string, mixed>
 */
function routeDistributionState(): array
{
    return [
        'clients' => Client::orderBy('id_client')->pluck('document_employee', 'id_client')->all(),
        'routes' => DeliveryRoute::orderBy('id_client')
            ->get()
            ->mapWithKeys(fn (DeliveryRoute $route) => [$route->id_client => $route->document_employee])
            ->all(),
        'suggestions' => RouteSuggestion::orderBy('id_suggestion')
            ->get()
            ->map(fn (RouteSuggestion $suggestion) => [
                $suggestion->id_suggestion,
                $suggestion->status,
                $suggestion->resolved_by,
            ])
            ->all(),
    ];
}

beforeEach(function () {
    $this->seed();
});

it('does not write anything when previewing the distribution', function () {
    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');
    $before = routeDistributionSnapshot();

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk();

    expect(routeDistributionSnapshot())->toBe($before);
});

it('returns a deterministic preview with the expected structure', function () {
    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');
    $eligibleClients = Client::where('status', true)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->count();

    $first = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    expect($first)->toHaveKeys(['fingerprint', 'summary', 'sellers', 'skipped'])
        ->and($first['summary'])->toHaveKeys(['clients', 'sellers', 'skipped_without_coordinates'])
        ->and($first['summary']['clients'])->toBe($eligibleClients)
        ->and($first['summary']['sellers'])->toBe(2)
        ->and(collect($first['sellers'])->sum('new_clients'))->toBe($eligibleClients);

    foreach ($first['sellers'] as $seller) {
        expect($seller)->toHaveKeys([
            'document_employee',
            'name',
            'current_clients',
            'new_clients',
            'clients',
        ]);

        foreach ($seller['clients'] as $client) {
            expect($client)->toHaveKeys([
                'id_client',
                'business_name',
                'address',
                'latitude',
                'longitude',
                'previous_document_employee',
            ]);
        }
    }

    $sellerClientCounts = collect($first['sellers'])->pluck('new_clients')->all();

    expect(max($sellerClientCounts) - min($sellerClientCounts))->toBeLessThanOrEqual(1);

    $second = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    expect($second['fingerprint'])->toBe($first['fingerprint'])
        ->and(routeDistributionAssignments($second))->toBe(routeDistributionAssignments($first));
});

it('reports clients without coordinates as skipped and ignores inactive clients', function () {
    Client::where('id_client', 'CLI002')->update([
        'latitude' => null,
        'longitude' => null,
    ]);
    Client::where('id_client', 'CLI003')->update(['status' => false]);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $skipped = collect($preview['skipped']);
    $assignedIds = collect($preview['sellers'])
        ->flatMap(fn (array $seller) => collect($seller['clients'])->pluck('id_client'))
        ->all();

    $skippedClient = $skipped->firstWhere('id_client', 'CLI002');

    expect($skipped->pluck('id_client')->all())->toContain('CLI002')
        ->not->toContain('CLI003')
        ->and($skippedClient)->toHaveKeys(['id_client', 'business_name', 'reason'])
        ->and($skippedClient['reason'])->toBe('sin_coordenadas')
        ->and($assignedIds)->not->toContain('CLI002')
        ->not->toContain('CLI003');
});

it('excludes inactive sellers and includes new active sellers', function () {
    Employee::whereKey('1000000003')->update(['status' => 'I']);

    Employee::create([
        'document_employee' => '1000000009',
        'name_1' => 'Nuevo',
        'last_name_1' => 'Vendedor',
        'email' => 'nuevo.vendedor@misterchef.com',
        'password' => Hash::make('vendedor123'),
        'type' => 'V',
        'status' => 'A',
    ]);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $sellerDocuments = collect($preview['sellers'])->pluck('document_employee')->all();
    $newSeller = collect($preview['sellers'])->firstWhere('document_employee', '1000000009');

    expect($sellerDocuments)->not->toContain('1000000003')
        ->toContain('1000000009')
        ->and($newSeller)->not->toBeNull()
        ->and($newSeller['new_clients'])->toBeGreaterThan(0);
});

it('synchronizes clients and routes when applying the distribution', function () {
    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $applied = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => $preview['fingerprint'],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Distribución aplicada correctamente.')
        ->json('applied');

    $eligibleIds = Client::where('status', true)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->pluck('id_client');

    expect($applied['clients'])->toBe($eligibleIds->count())
        ->and($applied['routes'])->toBe($eligibleIds->count());

    $routes = DeliveryRoute::all();

    expect($routes->pluck('id_client')->duplicates())->toBeEmpty();

    foreach ($routes as $route) {
        expect(Client::find($route->id_client)->document_employee)->toBe($route->document_employee);
    }

    foreach ($eligibleIds as $idClient) {
        $client = Client::find($idClient);
        $route = DeliveryRoute::where('id_client', $idClient)->first();

        expect($route)->not->toBeNull()
            ->and($route->document_employee)->toBe($client->document_employee);
    }
});

it('overwrites a previous manual assignment when applying', function () {
    Client::where('id_client', 'CLI005')->update(['document_employee' => '1000000001']);
    DeliveryRoute::where('id_client', 'CLI005')->update(['document_employee' => '1000000001']);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $expectedSeller = collect($preview['sellers'])->first(
        fn (array $seller) => collect($seller['clients'])->pluck('id_client')->contains('CLI005')
    );

    expect($expectedSeller)->not->toBeNull()
        ->and($expectedSeller['document_employee'])->not->toBe('1000000001');

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => $preview['fingerprint'],
        ])
        ->assertOk();

    $client = Client::find('CLI005');
    $routes = DeliveryRoute::where('id_client', 'CLI005')->get();

    expect($client->document_employee)->toBe($expectedSeller['document_employee'])
        ->and($routes)->toHaveCount(1)
        ->and($routes->first()->document_employee)->toBe($expectedSeller['document_employee']);
});

it('closes pending suggestions when applying the distribution', function () {
    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $applied = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => $preview['fingerprint'],
        ])
        ->assertOk()
        ->json('applied');

    $suggestion = RouteSuggestion::find('SG00000001');

    expect($applied['suggestions_closed'])->toBe(1)
        ->and($suggestion->status)->toBe('R')
        ->and($suggestion->resolved_at)->not->toBeNull()
        ->and($suggestion->resolved_by)->toBe('1000000001')
        ->and(RouteSuggestion::where('status', 'P')->count())->toBe(0);
});

it('rejects an altered or stale fingerprint without writing', function () {
    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $before = routeDistributionSnapshot();

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => str_repeat('a', 40),
        ])
        ->assertStatus(409)
        ->assertJson([
            'message' => 'El reparto cambió desde la vista previa. Vuelve a calcularlo.',
        ]);

    expect(routeDistributionSnapshot())->toBe($before);

    Client::where('id_client', 'CLI001')->update(['status' => false]);
    $staleBefore = routeDistributionSnapshot();

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => $preview['fingerprint'],
        ])
        ->assertStatus(409)
        ->assertJson([
            'message' => 'El reparto cambió desde la vista previa. Vuelve a calcularlo.',
        ]);

    expect(routeDistributionSnapshot())->toBe($staleBefore);
});

it('does not touch clients without coordinates or inactive clients when applying', function () {
    Client::where('id_client', 'CLI002')->update([
        'latitude' => null,
        'longitude' => null,
    ]);
    Client::where('id_client', 'CLI003')->update(['status' => false]);

    $skippedClientBefore = Client::find('CLI002');
    $disabledClientBefore = Client::find('CLI003');
    $skippedRouteBefore = DeliveryRoute::where('id_client', 'CLI002')->first();
    $disabledRouteBefore = DeliveryRoute::where('id_client', 'CLI003')->first();

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => $preview['fingerprint'],
        ])
        ->assertOk();

    expect(Client::find('CLI002')->document_employee)->toBe($skippedClientBefore->document_employee)
        ->and(DeliveryRoute::where('id_client', 'CLI002')->count())->toBe(1)
        ->and(DeliveryRoute::where('id_client', 'CLI002')->first()->id_ruta)->toBe($skippedRouteBefore->id_ruta)
        ->and(Client::find('CLI003')->document_employee)->toBe($disabledClientBefore->document_employee)
        ->and(DeliveryRoute::where('id_client', 'CLI003')->count())->toBe(1)
        ->and(DeliveryRoute::where('id_client', 'CLI003')->first()->id_ruta)->toBe($disabledRouteBefore->id_ruta);
});

it('forbids vendors and guests from previewing or applying', function () {
    $vendorToken = loginRouteDistributionUser('vendedor@misterchef.com', 'vendedor123');

    foreach (['preview', 'apply'] as $endpoint) {
        $this->withToken($vendorToken)
            ->postJson("/api/v1/routes/distribution/{$endpoint}")
            ->assertForbidden();

        // Cada petición real usa un guard nuevo; en tests hay que limpiar el token y el guard cacheados.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/v1/routes/distribution/{$endpoint}")
            ->assertUnauthorized();
    }
});

it('returns 422 when there are no active sellers', function () {
    Employee::where('type', 'V')->update(['status' => 'I']);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertStatus(422)
        ->assertJson([
            'message' => 'No hay vendedores activos para distribuir.',
        ]);

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => 'cualquier-fingerprint',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'No hay vendedores activos para distribuir.',
        ]);
});

it('returns 422 when there are no eligible clients', function () {
    Client::query()->update(['status' => false]);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertStatus(422)
        ->assertJson([
            'message' => 'No hay clientes elegibles para distribuir.',
        ]);
});

it('leaves the same assignment state when applied twice with fresh fingerprints', function () {
    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $firstPreview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => $firstPreview['fingerprint'],
        ])
        ->assertOk();

    $firstState = routeDistributionState();

    $secondPreview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/apply', [
            'fingerprint' => $secondPreview['fingerprint'],
        ])
        ->assertOk();

    expect($secondPreview['fingerprint'])->toBe($firstPreview['fingerprint'])
        ->and(routeDistributionState())->toBe($firstState);
});

it('forbids vendors from approving or rejecting suggestions', function () {
    $vendorToken = loginRouteDistributionUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($vendorToken)
        ->patchJson('/api/v1/route-suggestions/SG00000001/approve')
        ->assertForbidden();

    $this->withToken($vendorToken)
        ->patchJson('/api/v1/route-suggestions/SG00000001/reject', [
            'document_employee' => '1000000003',
        ])
        ->assertForbidden();

    expect(RouteSuggestion::find('SG00000001')->status)->toBe('P');
});

it('rejects suggestions when the destination seller is inactive', function () {
    Employee::whereKey('1000000002')->update(['status' => 'I']);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->patchJson('/api/v1/route-suggestions/SG00000001/approve')
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor destino no existe o no está activo.',
        ]);

    $this->withToken($token)
        ->patchJson('/api/v1/route-suggestions/SG00000001/reject', [
            'document_employee' => '1000000004',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'El vendedor destino no existe o no está activo.',
        ]);

    expect(RouteSuggestion::find('SG00000001')->status)->toBe('P');
});

it('syncs the client and replaces its route when approving a suggestion', function () {
    DeliveryRoute::create([
        'id_ruta' => 'RTMANUAL1',
        'id_client' => 'CLI004',
        'document_employee' => '1000000003',
    ]);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->patchJson('/api/v1/route-suggestions/SG00000001/approve')
        ->assertOk();

    $routes = DeliveryRoute::where('id_client', 'CLI004')->get();

    expect(Client::find('CLI004')->document_employee)->toBe('1000000002')
        ->and($routes)->toHaveCount(1)
        ->and($routes->first()->document_employee)->toBe('1000000002')
        ->and(DeliveryRoute::where('id_ruta', 'RTMANUAL1')->exists())->toBeFalse()
        ->and(RouteSuggestion::find('SG00000001')->status)->toBe('A');
});

it('syncs the client and replaces its route when rejecting a suggestion', function () {
    DeliveryRoute::create([
        'id_ruta' => 'RTMANUAL2',
        'id_client' => 'CLI004',
        'document_employee' => '1000000002',
    ]);

    $token = loginRouteDistributionUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->patchJson('/api/v1/route-suggestions/SG00000001/reject', [
            'document_employee' => '1000000003',
        ])
        ->assertOk();

    $routes = DeliveryRoute::where('id_client', 'CLI004')->get();

    expect(Client::find('CLI004')->document_employee)->toBe('1000000003')
        ->and($routes)->toHaveCount(1)
        ->and($routes->first()->document_employee)->toBe('1000000003')
        ->and(DeliveryRoute::where('id_ruta', 'RTMANUAL2')->exists())->toBeFalse()
        ->and(RouteSuggestion::find('SG00000001')->status)->toBe('R')
        ->and(RouteSuggestion::find('SG00000001')->resolved_by)->toBe('1000000001');
});
