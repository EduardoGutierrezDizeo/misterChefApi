<?php

use App\Models\Client;
use App\Models\Employee;
use App\Services\RouteDistributionService;
use Database\Seeders\OcanaClientsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function makeZoneClient(string $idClient, float $latitude, float $longitude, ?string $documentEmployee = null): Client
{
    return new Client([
        'id_client' => $idClient,
        'business_name' => "Negocio {$idClient}",
        'address' => "Dirección {$idClient}",
        'latitude' => $latitude,
        'longitude' => $longitude,
        'status' => true,
        'document_employee' => $documentEmployee,
    ]);
}

function makeZoneSeller(string $documentEmployee): Employee
{
    return new Employee([
        'document_employee' => $documentEmployee,
        'name_1' => 'Vendedor',
        'last_name_1' => $documentEmployee,
        'type' => 'V',
        'status' => 'A',
    ]);
}

/**
 * @param  iterable<int, string>  $documents
 * @return Collection<int, Employee>
 */
function makeZoneSellers(iterable $documents)
{
    return collect($documents)->map(fn (string $document): Employee => makeZoneSeller($document))->values();
}

/**
 * @param  array<string|int, array<int, Client>>  $assignments
 * @return array<string, array<int, string>>
 */
function zoneAssignmentIds(array $assignments): array
{
    $normalized = [];

    foreach ($assignments as $document => $clients) {
        $normalized[(string) $document] = collect($clients)
            ->pluck('id_client')
            ->sort()
            ->values()
            ->all();
    }

    ksort($normalized, SORT_STRING);

    return $normalized;
}

/**
 * @param  array<string|int, array<int, Client>>  $assignments
 * @param  Collection<int, Client>  $allClients
 */
function zoneSquaredCost(array $assignments, $allClients): float
{
    $scale = cos(deg2rad((float) $allClients->avg(fn (Client $client): float => (float) $client->latitude)));
    $total = 0.0;

    foreach ($assignments as $clients) {
        if ($clients === []) {
            continue;
        }

        $sumX = 0.0;
        $sumY = 0.0;

        foreach ($clients as $client) {
            $sumX += (float) $client->longitude * $scale;
            $sumY += (float) $client->latitude;
        }

        $count = count($clients);
        $centerX = $sumX / $count;
        $centerY = $sumY / $count;

        foreach ($clients as $client) {
            $deltaX = ((float) $client->longitude * $scale) - $centerX;
            $deltaY = (float) $client->latitude - $centerY;
            $total += ($deltaX * $deltaX) + ($deltaY * $deltaY);
        }
    }

    return $total;
}

function loginZoneDistributionAdmin(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

it('gives each seller one complete group when two groups are clearly separated', function () {
    $clients = collect();

    foreach (range(1, 5) as $index) {
        $clients->push(makeZoneClient(sprintf('A%02d', $index), 8.3000 + ($index * 0.0001), -73.2600));
    }

    foreach (range(1, 5) as $index) {
        $clients->push(makeZoneClient(sprintf('B%02d', $index), 8.2000 + ($index * 0.0001), -73.2600));
    }

    $assignments = (new RouteDistributionService)->assign($clients, makeZoneSellers(['1000000002', '1000000003']));

    expect($assignments)->toHaveCount(2);

    $groupPrefixes = [];

    foreach ($assignments as $sellerClients) {
        expect($sellerClients)->toHaveCount(5);

        $prefixes = collect($sellerClients)
            ->pluck('id_client')
            ->map(fn (string $id): string => substr($id, 0, 1))
            ->unique()
            ->values()
            ->all();

        expect($prefixes)->toHaveCount(1);

        $groupPrefixes[] = $prefixes[0];
    }

    sort($groupPrefixes);

    expect($groupPrefixes)->toBe(['A', 'B']);
});

it('gives each seller one complete group when three groups are separated', function () {
    $clients = collect();
    $groups = [
        'A' => 8.1000,
        'B' => 8.2000,
        'C' => 8.3000,
    ];

    foreach ($groups as $prefix => $latitude) {
        foreach (range(1, 4) as $index) {
            $clients->push(makeZoneClient(sprintf('%s%02d', $prefix, $index), $latitude + ($index * 0.0001), -73.2600));
        }
    }

    $assignments = (new RouteDistributionService)->assign(
        $clients,
        makeZoneSellers(['1000000002', '1000000003', '1000000004']),
    );

    expect($assignments)->toHaveCount(3);

    $prefixes = [];

    foreach ($assignments as $sellerClients) {
        expect($sellerClients)->toHaveCount(4);

        $groupPrefixes = collect($sellerClients)
            ->pluck('id_client')
            ->map(fn (string $id): string => substr($id, 0, 1))
            ->unique()
            ->values()
            ->all();

        expect($groupPrefixes)->toHaveCount(1);

        $prefixes[] = $groupPrefixes[0];
    }

    expect($prefixes)->toBe(['A', 'B', 'C']);
});

it('keeps a strict balance for every client and seller count', function () {
    mt_srand(20261113);

    $service = new RouteDistributionService;
    $sellerDocuments = ['1000000002', '1000000003', '1000000004', '1000000005', '1000000006'];

    for ($sellerCount = 1; $sellerCount <= 5; $sellerCount++) {
        $sellers = makeZoneSellers(array_slice($sellerDocuments, 0, $sellerCount));

        for ($clientCount = 1; $clientCount <= 40; $clientCount++) {
            $clients = collect();

            for ($index = 1; $index <= $clientCount; $index++) {
                $clients->push(makeZoneClient(
                    sprintf('K%03d', $index),
                    8.2000 + ((mt_rand() / mt_getrandmax()) * 0.1000),
                    -73.3000 + ((mt_rand() / mt_getrandmax()) * 0.1000),
                ));
            }

            $assignments = $service->assign($clients, $sellers);
            $counts = array_map('count', $assignments);

            expect($assignments)->toHaveCount($sellerCount)
                ->and(max($counts) - min($counts))->toBeLessThanOrEqual(1);

            expect(array_map('strval', array_keys($assignments)))
                ->toBe(array_slice($sellerDocuments, 0, $sellerCount));

            $assignedIds = collect($assignments)
                ->flatMap(fn (array $sellerClients) => collect($sellerClients)->pluck('id_client'))
                ->sort()
                ->values()
                ->all();

            expect($assignedIds)->toBe($clients->pluck('id_client')->sort()->values()->all());
        }
    }
});

it('returns the same result for the same clients in another order', function () {
    mt_srand(77123);

    $clients = collect();

    for ($index = 1; $index <= 24; $index++) {
        $clients->push(makeZoneClient(
            sprintf('D%03d', $index),
            8.2000 + ((mt_rand() / mt_getrandmax()) * 0.1000),
            -73.3000 + ((mt_rand() / mt_getrandmax()) * 0.1000),
            $index % 3 === 0 ? '1000000003' : null,
        ));
    }

    $sellers = makeZoneSellers(['1000000002', '1000000003', '1000000004']);
    $service = new RouteDistributionService;

    $first = $service->assign($clients, $sellers);
    $second = $service->assign($clients->shuffle()->values(), $sellers->reverse()->values());

    expect(zoneAssignmentIds($second))->toBe(zoneAssignmentIds($first));
});

it('preserves current assignments when the separated groups are crossed', function () {
    $clients = collect();

    foreach (range(1, 5) as $index) {
        $clients->push(makeZoneClient(
            sprintf('A%02d', $index),
            8.3000 + ($index * 0.0001),
            -73.2600,
            '1000000003',
        ));
    }

    foreach (range(1, 5) as $index) {
        $clients->push(makeZoneClient(
            sprintf('B%02d', $index),
            8.2000 + ($index * 0.0001),
            -73.2600,
            '1000000002',
        ));
    }

    $assignments = (new RouteDistributionService)->assign(
        $clients,
        makeZoneSellers(['1000000002', '1000000003']),
    );

    $before = $clients->mapWithKeys(fn (Client $client): array => [
        $client->id_client => (string) $client->document_employee,
    ])->all();

    $after = [];

    foreach ($assignments as $document => $sellerClients) {
        foreach ($sellerClients as $client) {
            $after[$client->id_client] = (string) $document;
        }
    }

    ksort($before, SORT_STRING);
    ksort($after, SORT_STRING);

    expect($after)->toBe($before);
});

it('handles fewer clients than sellers, no clients and no sellers', function () {
    $service = new RouteDistributionService;
    $sellers = makeZoneSellers(['1000000002', '1000000003', '1000000004']);

    $assignments = $service->assign(
        collect([
            makeZoneClient('E01', 8.2500, -73.2600),
            makeZoneClient('E02', 8.2600, -73.2700),
        ]),
        $sellers,
    );

    $counts = array_map('count', $assignments);

    expect(array_map('strval', array_keys($assignments)))
        ->toBe(['1000000002', '1000000003', '1000000004'])
        ->and($counts)->toEqualCanonicalizing([1, 1, 0]);

    $withoutClients = $service->assign(collect(), $sellers);

    expect($withoutClients)->toBe([
        '1000000002' => [],
        '1000000003' => [],
        '1000000004' => [],
    ]);

    $withoutSellers = $service->assign(
        collect([makeZoneClient('E03', 8.2500, -73.2600)]),
        collect(),
    );

    expect($withoutSellers)->toBe([]);
});

it('produces more compact zones than a round-robin assignment', function () {
    $clients = collect();
    $sellerDocuments = ['1000000002', '1000000003', '1000000004'];

    $groups = [
        'A' => ['latitude' => 8.3000, 'longitude' => -73.3500],
        'B' => ['latitude' => 8.2500, 'longitude' => -73.2500],
        'C' => ['latitude' => 8.2000, 'longitude' => -73.1500],
    ];

    foreach ($groups as $prefix => $center) {
        foreach (range(1, 10) as $index) {
            $clients->push(makeZoneClient(
                sprintf('%s%02d', $prefix, $index),
                $center['latitude'] + (($index - 5) * 0.0001),
                $center['longitude'] + (($index - 5) * 0.0001),
            ));
        }
    }

    $service = new RouteDistributionService;
    $zoneAssignments = $service->assign($clients, makeZoneSellers($sellerDocuments));

    $roundRobin = array_fill_keys($sellerDocuments, []);
    $sortedClients = $clients->sortBy('id_client')->values();

    foreach ($sortedClients as $index => $client) {
        $roundRobin[$sellerDocuments[$index % count($sellerDocuments)]][] = $client;
    }

    expect(zoneSquaredCost($zoneAssignments, $clients))
        ->toBeLessThan(zoneSquaredCost($roundRobin, $clients));
});

it('distributes the seeded Ocana clients through the preview', function () {
    $this->seed(OcanaClientsSeeder::class);

    $token = loginZoneDistributionAdmin('admin@misterchef.com', 'admin123');

    $preview = $this->withToken($token)
        ->postJson('/api/v1/routes/distribution/preview')
        ->assertOk()
        ->json();

    $eligibleCount = Client::where('status', true)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->count();

    $skippedClients = Client::where('status', true)
        ->where(function ($query) {
            $query->whereNull('latitude')->orWhereNull('longitude');
        })
        ->orderBy('id_client')
        ->pluck('id_client')
        ->all();

    $sellerCounts = collect($preview['sellers'])->pluck('new_clients')->all();

    expect($preview['summary']['clients'])->toBe($eligibleCount)
        ->and(collect($preview['sellers'])->sum('new_clients'))->toBe($eligibleCount)
        ->and(max($sellerCounts) - min($sellerCounts))->toBeLessThanOrEqual(1)
        ->and($preview['summary']['skipped_without_coordinates'])->toBe(count($skippedClients))
        ->and(collect($preview['skipped'])->pluck('id_client')->sort()->values()->all())->toBe($skippedClients)
        ->and(collect($preview['skipped'])->every(fn (array $client): bool => $client['reason'] === 'sin_coordenadas'))->toBeTrue();
});
