<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginEmployee(string $email, string $password): string
{
    $response = test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    return $response->json('token');
}

beforeEach(function () {
    $this->seed();
});

it('authenticates the seeded employees with the documented credentials', function () {
    $admin = $this->postJson('/api/v1/login', [
        'email' => 'admin@misterchef.com',
        'password' => 'admin123',
    ])->assertOk()
        ->assertJsonPath('employee.type', 'A')
        ->assertJsonPath('employee.status', 'A')
        ->assertJsonPath('employee.first_login', false);

    expect($admin->json('token'))->toBeString()->not->toBeEmpty();

    $this->postJson('/api/v1/login', [
        'email' => 'vendedor@misterchef.com',
        'password' => 'vendedor123',
    ])->assertOk()
        ->assertJsonPath('employee.type', 'V');

    $this->postJson('/api/v1/login', [
        'email' => 'inactivo@misterchef.com',
        'password' => 'vendedor123',
    ])->assertUnauthorized();

    $this->postJson('/api/v1/login', [
        'email' => 'admin@misterchef.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized();
});

it('seeds the colombian geography with the keys the app dropdowns read', function () {
    $token = loginEmployee('admin@misterchef.com', 'admin123');

    $departments = $this->withToken($token)->getJson('/api/v1/departments')
        ->assertOk()
        ->json();

    expect($departments)->toHaveCount(32)
        ->and($departments[0])->toHaveKeys(['id_departament', 'name_departament', 'department_name']);

    $northSantander = collect($departments)->firstWhere('name_departament', 'Norte de Santander');
    expect($northSantander['id_departament'])->toBe('D023');

    $cities = $this->withToken($token)->getJson('/api/v1/cities?id_departament=D023')
        ->assertOk()
        ->json();

    expect($cities)->toHaveCount(18)
        ->and(collect($cities)->firstWhere('name_city', 'Ocaña'))
        ->not->toBeNull()
        ->and(collect($cities)->firstWhere('city_name', 'Ocaña'))
        ->not->toBeNull();
});

it('provides employees, product types and products for the app screens', function () {
    $token = loginEmployee('admin@misterchef.com', 'admin123');

    $employees = $this->withToken($token)->getJson('/api/v1/employees')->assertOk()->json();

    expect($employees)->toHaveCount(4)
        ->and($employees[0])->not->toHaveKey('password')
        ->and(collect($employees)->pluck('type')->sort()->values()->all())->toBe(['A', 'V', 'V', 'V']);

    $types = $this->withToken($token)->getJson('/api/v1/product-types')->assertOk()->json();

    expect($types)->toHaveCount(4)
        ->and(collect($types)->pluck('type')->all())
        ->toBe(['Condimentos', 'Cereales', 'Frutos secos', 'Salsas']);

    $products = $this->withToken($token)->getJson('/api/v1/products')->assertOk()->json();

    expect($products)->toHaveCount(12);

    foreach ($products as $product) {
        expect($product)->toHaveKeys(['id_product', 'product_name', 'selling_price', 'stock', 'minimun_stock', 'status', 'id_produc_type', 'product_type'])
            ->and($product['product_type'])->toHaveKeys(['id_produc_type', 'type']);
    }

    $lowStock = $this->withToken($token)->getJson('/api/v1/products/low-stock')->assertOk()->json();

    expect(collect($lowStock)->pluck('id_product')->all())->toContain('P0009');
});

it('returns clients with the nested city and department keys the detail screen reads', function () {
    $token = loginEmployee('admin@misterchef.com', 'admin123');

    $clients = $this->withToken($token)->getJson('/api/v1/clients')->assertOk()->json();

    expect($clients)->toHaveCount(8);

    foreach ($clients as $client) {
        expect($client['status'])->toBeTrue()
            ->and($client['city']['city_name'])->not->toBe('')
            ->and($client['city']['department']['department_name'])->not->toBe('')
            ->and($client['city']['department']['department_name'])->toBe('Norte de Santander')
            ->and($client['employee'])->toHaveKeys(['name_1', 'last_name_1']);
    }

    $detail = $this->withToken($token)->getJson('/api/v1/clients/CLI001')->assertOk()->json();

    expect($detail)->toHaveKey('invoices')
        ->and($detail['invoices'])->not->toBeEmpty()
        ->and($detail['city']['city_name'])->toBe('Ocaña');
});

it('scopes clients, invoices and routes to the logged in vendor', function () {
    $token = loginEmployee('vendedor@misterchef.com', 'vendedor123');

    $clients = $this->withToken($token)->getJson('/api/v1/clients')->assertOk()->json();

    expect($clients)->toHaveCount(4)
        ->and(collect($clients)->unique('document_employee')->pluck('document_employee')->all())->toBe(['1000000002']);

    $invoices = $this->withToken($token)->getJson('/api/v1/invoices')->assertOk()->json();

    expect($invoices)->toHaveCount(4)
        ->and(collect($invoices)->pluck('id_invoice')->sort()->values()->all())->toBe(['F0001', 'F0003', 'F0005', 'F0007']);

    $routes = $this->withToken($token)->getJson('/api/v1/routes')->assertOk()->json();

    expect($routes)->toHaveCount(1)
        ->and($routes[0])->toHaveKeys(['employee_name', 'total_clients', 'clients'])
        ->and($routes[0]['total_clients'])->toBe(3)
        ->and($routes[0]['clients'][0])->toHaveKeys(['id_client', 'client_name1', 'business_name', 'latitude', 'longitude', 'address', 'status']);
});

it('serves the daily stats the home dashboard consumes', function () {
    $token = loginEmployee('admin@misterchef.com', 'admin123');

    $this->withToken($token)->getJson('/api/v1/invoices/stats')
        ->assertOk()
        ->assertJson([
            'total_pedidos' => 4,
            'total_ventas' => 46700,
            'clientes_visitados' => 2,
        ]);
});

it('keeps invoice details, audits and stock consistent for the order detail screen', function () {
    $token = loginEmployee('admin@misterchef.com', 'admin123');

    $invoice = $this->withToken($token)->getJson('/api/v1/invoices/F0001')
        ->assertOk()
        ->json();

    expect($invoice['status'])->toBe('C')
        ->and($invoice['total'])->toEqual(13800)
        ->and($invoice['details'])->not->toBeEmpty()
        ->and($invoice['details'][0]['product'])->toHaveKeys(['id_product', 'product_name', 'selling_price'])
        ->and($invoice['audits'])->not->toBeEmpty()
        ->and($invoice['audits'][0])->toHaveKeys(['action_type', 'employee'])
        ->and($invoice['audits'][0]['employee'])->toHaveKeys(['name_1', 'last_name_1']);

    expect(Product::find('P0001')->stock)->toBe(118)
        ->and(Product::find('P0004')->stock)->toBe(197);
});

it('provides pending route suggestions and active locations for the admin screens', function () {
    $token = loginEmployee('admin@misterchef.com', 'admin123');

    $suggestions = $this->withToken($token)->getJson('/api/v1/route-suggestions')->assertOk()->json();

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['status'])->toBe('P')
        ->and($suggestions[0])->toHaveKeys(['id_suggestion', 'distance_km', 'client', 'employee'])
        ->and($suggestions[0]['client'])->toHaveKeys(['business_name', 'client_name1', 'address'])
        ->and($suggestions[0]['employee'])->toHaveKeys(['name_1', 'last_name_1', 'document_employee']);

    $locations = $this->withToken($token)->getJson('/api/v1/location')->assertOk()->json();

    expect($locations)->not->toBeEmpty()
        ->and($locations[0])->toHaveKeys(['document_employee', 'name', 'latitude', 'longitude', 'last_update']);
});

it('lets an employee change the password and skip the forced redirect on next login', function () {
    $token = loginEmployee('vendedor2@misterchef.com', 'vendedor123');

    $this->withToken($token)->postJson('/api/v1/change-password', [
        'password' => 'nuevaClave123',
        'password_confirmation' => 'nuevaClave123',
    ])->assertOk();

    $this->postJson('/api/v1/login', [
        'email' => 'vendedor2@misterchef.com',
        'password' => 'nuevaClave123',
    ])->assertOk()
        ->assertJsonPath('employee.first_login', false);
});
