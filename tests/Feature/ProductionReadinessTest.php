<?php

use App\Models\EmployeeLocation;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

it('hides stale locations from the admin map while keeping fresh ones', function () {
    // Ubicación fresca: reportada hace 1 minuto (dentro de la ventana de 5 minutos)
    EmployeeLocation::updateOrCreate(
        ['document_employee' => '1000000002'],
        [
            'latitude' => 8.2340,
            'longitude' => -73.2648,
            'is_active' => true,
            'updated_at' => now()->subMinute(),
        ],
    );

    // Ubicación obsoleta: reportada hace 10 minutos (fuera de la ventana)
    EmployeeLocation::updateOrCreate(
        ['document_employee' => '1000000003'],
        [
            'latitude' => 8.2400,
            'longitude' => -73.2700,
            'is_active' => true,
            'updated_at' => now()->subMinutes(10),
        ],
    );

    $token = $this->postJson('/api/v1/login', [
        'email' => 'admin@misterchef.com',
        'password' => 'admin123',
    ])->assertOk()->json('token');

    $locations = $this->withToken($token)->getJson('/api/v1/location')
        ->assertOk()
        ->json();

    expect($locations)->toHaveCount(1)
        ->and($locations[0]['document_employee'])->toBe('1000000002')
        ->and($locations[0])->toHaveKeys([
            'document_employee',
            'name',
            'latitude',
            'longitude',
            'last_update',
        ]);
});

it('sends the chatbot request to groq using the model from config', function () {
    config()->set('services.groq.model', 'llama-test-model');
    config()->set('services.groq.url', 'https://api.groq.com/openai/v1/chat/completions');
    config()->set('services.groq.key', 'test-api-key');

    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hola desde Groq']],
            ],
        ]),
    ]);

    $token = $this->postJson('/api/v1/login', [
        'email' => 'vendedor@misterchef.com',
        'password' => 'vendedor123',
    ])->assertOk()->json('token');

    $this->withToken($token)->postJson('/api/v1/chatbot', [
        'message' => 'hola',
    ])->assertOk()->assertJsonPath('response', 'Hola desde Groq');

    Http::assertSent(function (Request $request) {
        return str_starts_with($request->url(), 'https://')
            && $request->data()['model'] === 'llama-test-model';
    });
});

it('rate limits location updates beyond thirty requests per minute', function () {
    $token = $this->postJson('/api/v1/login', [
        'email' => 'vendedor@misterchef.com',
        'password' => 'vendedor123',
    ])->assertOk()->json('token');

    foreach (range(1, 30) as $attempt) {
        $this->withToken($token)->postJson('/api/v1/location', [
            'latitude' => 8.2340,
            'longitude' => -73.2648,
        ])->assertOk();
    }

    $this->withToken($token)->postJson('/api/v1/location', [
        'latitude' => 8.2340,
        'longitude' => -73.2648,
    ])->assertStatus(429);
});

it('refuses to seed the admin user in production without ADMIN_PASSWORD', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('misterchef.admin_password', null);

    expect(fn () => (new EmployeeSeeder)->run())->toThrow(RuntimeException::class);
});
