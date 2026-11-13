<?php

use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use App\Models\EmployeeLocation;
use App\Models\Invoice;
use App\Models\RouteSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginEmployeeDeactivationUser(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

function deactivateEmployeeWithToken(string $token, string $document): void
{
    test()->withToken($token)
        ->patchJson("/api/v1/employees/{$document}/status", ['status' => 'I'])
        ->assertOk();
}

beforeEach(function () {
    $this->seed();
});

it('releases the vendor clients, routes and pending suggestions with real counts', function () {
    $vendor = '1000000002';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    $expected = [
        'clients' => Client::where('document_employee', $vendor)->count(),
        'routes' => DeliveryRoute::where('document_employee', $vendor)->count(),
        'suggestions' => RouteSuggestion::where('document_employee', $vendor)
            ->where('status', 'P')
            ->count(),
    ];

    expect($expected)->toBe([
        'clients' => 4,
        'routes' => 3,
        'suggestions' => 1,
    ]);

    $this->withToken($adminToken)
        ->patchJson("/api/v1/employees/{$vendor}/status", ['status' => 'I'])
        ->assertOk()
        ->assertJsonPath('employee.document_employee', $vendor)
        ->assertJsonPath('employee.status', 'I')
        ->assertJsonPath('released.clients', $expected['clients'])
        ->assertJsonPath('released.routes', $expected['routes'])
        ->assertJsonPath('released.suggestions', $expected['suggestions']);
});

it('leaves the released clients without a seller and still active', function () {
    $vendor = '1000000002';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');
    $clientIds = Client::where('document_employee', $vendor)->pluck('id_client')->all();

    expect($clientIds)->toHaveCount(4);

    deactivateEmployeeWithToken($adminToken, $vendor);

    $releasedClients = Client::whereIn('id_client', $clientIds)->get();

    expect($releasedClients)->toHaveCount(4);

    foreach ($releasedClients as $client) {
        expect($client->document_employee)->toBeNull()
            ->and($client->status)->toBeTrue();
    }

    expect(Client::where('document_employee', $vendor)->count())->toBe(0);
});

it('removes only the deactivated vendor routes and keeps the other vendor intact', function () {
    $vendor = '1000000002';
    $otherVendor = '1000000003';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    $otherRoutes = DeliveryRoute::where('document_employee', $otherVendor)
        ->orderBy('id_ruta')
        ->pluck('id_ruta')
        ->all();
    $otherClients = Client::where('document_employee', $otherVendor)
        ->orderBy('id_client')
        ->pluck('id_client')
        ->all();

    expect($otherRoutes)->not->toBeEmpty()
        ->and($otherClients)->not->toBeEmpty();

    deactivateEmployeeWithToken($adminToken, $vendor);

    expect(DeliveryRoute::where('document_employee', $vendor)->count())->toBe(0)
        ->and(DeliveryRoute::where('document_employee', $otherVendor)->orderBy('id_ruta')->pluck('id_ruta')->all())->toBe($otherRoutes)
        ->and(Client::where('document_employee', $otherVendor)->orderBy('id_client')->pluck('id_client')->all())->toBe($otherClients);
});

it('marks the pending suggestion as rejected with the administrator as resolver', function () {
    $vendor = '1000000002';
    $admin = '1000000001';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    deactivateEmployeeWithToken($adminToken, $vendor);

    $suggestion = RouteSuggestion::find('SG00000001');

    expect($suggestion)->not->toBeNull()
        ->and($suggestion->status)->toBe('R')
        ->and($suggestion->resolved_at)->not->toBeNull()
        ->and($suggestion->resolved_by)->toBe($admin);
});

it('deactivates the employee location and hides it from the admin map', function () {
    $vendor = '1000000002';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    deactivateEmployeeWithToken($adminToken, $vendor);

    $location = EmployeeLocation::where('document_employee', $vendor)->first();

    expect($location)->not->toBeNull()
        ->and($location->is_active)->toBeFalse();

    $locations = $this->withToken($adminToken)->getJson('/api/v1/location')
        ->assertOk()
        ->json();

    expect(collect($locations)->pluck('document_employee')->all())->not->toContain($vendor);
});

it('revokes the employee tokens and blocks the old and new sessions', function () {
    $vendor = '1000000002';
    $vendorToken = loginEmployeeDeactivationUser('vendedor@misterchef.com', 'vendedor123');
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    deactivateEmployeeWithToken($adminToken, $vendor);

    expect(Employee::find($vendor)->tokens()->count())->toBe(0);

    // Cada petición real usa un guard nuevo; en tests hay que limpiar el guard cacheado.
    $this->app['auth']->forgetGuards();

    $this->withToken($vendorToken)->getJson('/api/v1/me')->assertUnauthorized();

    $this->postJson('/api/v1/login', [
        'email' => 'vendedor@misterchef.com',
        'password' => 'vendedor123',
    ])->assertUnauthorized();
});

it('does not restore clients or routes when the vendor is reactivated', function () {
    $vendor = '1000000002';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    deactivateEmployeeWithToken($adminToken, $vendor);

    $this->withToken($adminToken)
        ->patchJson("/api/v1/employees/{$vendor}/status", ['status' => 'A'])
        ->assertOk()
        ->assertJsonPath('employee.status', 'A')
        ->assertJsonPath('released.clients', 0)
        ->assertJsonPath('released.routes', 0)
        ->assertJsonPath('released.suggestions', 0);

    expect(Employee::find($vendor)->status)->toBe('A')
        ->and(Client::where('document_employee', $vendor)->count())->toBe(0)
        ->and(DeliveryRoute::where('document_employee', $vendor)->count())->toBe(0);
});

it('is idempotent when the vendor is deactivated twice in a row', function () {
    $vendor = '1000000002';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    deactivateEmployeeWithToken($adminToken, $vendor);

    $resolvedAt = RouteSuggestion::find('SG00000001')->resolved_at->toDateTimeString();

    $this->withToken($adminToken)
        ->patchJson("/api/v1/employees/{$vendor}/status", ['status' => 'I'])
        ->assertOk()
        ->assertJsonPath('employee.status', 'I')
        ->assertJsonPath('released.clients', 0)
        ->assertJsonPath('released.routes', 0)
        ->assertJsonPath('released.suggestions', 0);

    $suggestion = RouteSuggestion::find('SG00000001');

    expect($suggestion->status)->toBe('R')
        ->and($suggestion->resolved_at->toDateTimeString())->toBe($resolvedAt)
        ->and(Client::where('document_employee', $vendor)->count())->toBe(0)
        ->and(DeliveryRoute::where('document_employee', $vendor)->count())->toBe(0)
        ->and(EmployeeLocation::where('document_employee', $vendor)->first()->is_active)->toBeFalse();
});

it('forbids vendors from changing an employee status', function () {
    $vendor = '1000000002';
    $vendorToken = loginEmployeeDeactivationUser('vendedor2@misterchef.com', 'vendedor123');

    $this->withToken($vendorToken)
        ->patchJson("/api/v1/employees/{$vendor}/status", ['status' => 'I'])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Solo los administradores pueden cambiar el estado de un empleado.',
        ]);

    expect(Employee::find($vendor)->status)->toBe('A')
        ->and(Client::where('document_employee', $vendor)->count())->toBe(4);
});

it('keeps the invoices and shows the released clients on the map without a seller', function () {
    $vendor = '1000000002';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    $clientIds = Client::where('document_employee', $vendor)->pluck('id_client')->all();
    $invoiceIds = Invoice::whereIn('id_client', $clientIds)
        ->orderBy('id_invoice')
        ->pluck('id_invoice')
        ->all();

    expect($invoiceIds)->not->toBeEmpty();

    deactivateEmployeeWithToken($adminToken, $vendor);

    expect(Invoice::whereIn('id_invoice', $invoiceIds)->count())->toBe(count($invoiceIds));

    $map = $this->withToken($adminToken)->getJson('/api/v1/clients/map')
        ->assertOk()
        ->json();

    foreach ($clientIds as $clientId) {
        $row = collect($map)->firstWhere('id_client', $clientId);

        expect($row)->not->toBeNull()
            ->and($row['document_employee'])->toBeNull()
            ->and($row['seller_name'])->toBeNull();
    }
});

it('only revokes tokens when an administrator is deactivated', function () {
    $admin = '1000000001';
    $adminToken = loginEmployeeDeactivationUser('admin@misterchef.com', 'admin123');

    $clientsBefore = Client::orderBy('id_client')->pluck('document_employee', 'id_client')->all();
    $routesBefore = DeliveryRoute::orderBy('id_ruta')->pluck('id_ruta')->all();
    $suggestionStatusBefore = RouteSuggestion::find('SG00000001')->status;

    $this->withToken($adminToken)
        ->patchJson("/api/v1/employees/{$admin}/status", ['status' => 'I'])
        ->assertOk()
        ->assertJsonPath('employee.status', 'I')
        ->assertJsonPath('released.clients', 0)
        ->assertJsonPath('released.routes', 0)
        ->assertJsonPath('released.suggestions', 0);

    expect(Employee::find($admin)->status)->toBe('I')
        ->and(Employee::find($admin)->tokens()->count())->toBe(0)
        ->and(Client::orderBy('id_client')->pluck('document_employee', 'id_client')->all())->toBe($clientsBefore)
        ->and(DeliveryRoute::orderBy('id_ruta')->pluck('id_ruta')->all())->toBe($routesBefore)
        ->and(RouteSuggestion::find('SG00000001')->status)->toBe($suggestionStatusBefore);
});
