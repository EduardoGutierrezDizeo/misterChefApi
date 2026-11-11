<?php

use App\Models\Client;
use App\Models\DeliveryRoute;
use Database\Seeders\OcanaClientsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<int, string>
 */
function ocanaBusinessNames(): array
{
    $records = json_decode(
        file_get_contents(database_path('seeders/data/clientes_ocana.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return array_column($records, 'business_name');
}

beforeEach(function () {
    $this->seed();
    $this->seed(OcanaClientsSeeder::class);
});

it('seeds the 44 real Ocaña businesses without vendor or route', function () {
    $names = ocanaBusinessNames();

    expect(Client::count())->toBe(52)
        ->and(Client::whereIn('business_name', $names)->count())->toBe(44)
        ->and(Client::whereNull('document_employee')->count())->toBe(44);

    $seeded = Client::whereIn('business_name', $names)->get();

    foreach ($seeded as $client) {
        expect($client->document_employee)->toBeNull()
            ->and($client->status)->toBeTrue()
            ->and($client->latitude)->not->toBeNull()
            ->and($client->longitude)->not->toBeNull()
            ->and($client->latitude)->toBeGreaterThanOrEqual(8.20)
            ->and($client->latitude)->toBeLessThanOrEqual(8.30)
            ->and($client->longitude)->toBeGreaterThanOrEqual(-73.40)
            ->and($client->longitude)->toBeLessThanOrEqual(-73.30);
    }

    expect(DeliveryRoute::whereIn('id_client', $seeded->pluck('id_client'))->count())->toBe(0);
});

it('is idempotent when the seeder runs twice', function () {
    $this->seed(OcanaClientsSeeder::class);

    expect(Client::count())->toBe(52)
        ->and(Client::whereNull('document_employee')->count())->toBe(44);
});
