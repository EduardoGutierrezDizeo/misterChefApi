<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use App\Models\RouteSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RouteDistributionService
{
    public function eligibleClients(): Collection
    {
        return Client::where('status', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id_client')
            ->get();
    }

    public function skippedClients(): Collection
    {
        return Client::where('status', true)
            ->where(function ($query) {
                $query->whereNull('latitude')
                    ->orWhereNull('longitude');
            })
            ->orderBy('id_client')
            ->get();
    }

    public function sellers(): Collection
    {
        return Employee::where('type', 'V')
            ->where('status', 'A')
            ->orderBy('document_employee')
            ->get();
    }

    /**
     * Asigna clientes a vendedores en memoria, sin consultar ni escribir en la BD.
     *
     * El reparto agrupa por zonas geográficas con cupos exactos: los clientes se
     * proyectan a un plano local, se parte el conjunto por bisecciones sucesivas
     * según el cupo de cada zona, se refinan los límites con intercambios que
     * reducen la distancia cuadrada a los centroides y, finalmente, cada zona se
     * empareja con el vendedor que hoy tiene más clientes en ella. Los tamaños de
     * zona difieren como máximo en un cliente y el resultado es determinista.
     *
     * @return array<string, array<int, Client>>
     */
    public function assign(Collection $clients, Collection $sellers): array
    {
        $sortedClients = $clients->sortBy('id_client')->values();
        $sortedSellers = $sellers->sortBy('document_employee')->values();
        $sellerDocuments = $sortedSellers->pluck('document_employee')->all();

        $assignments = [];

        foreach ($sellerDocuments as $document) {
            $assignments[$document] = [];
        }

        if ($sellerDocuments === [] || $sortedClients->isEmpty()) {
            return $assignments;
        }

        $projectedClients = $this->projectClients($sortedClients);
        $zoneSizes = $this->zoneSizes($sortedClients->count(), count($sellerDocuments));
        $zones = $this->partitionZones($projectedClients, $zoneSizes);

        $this->refineZones($zones);

        $ownerByZone = $this->matchZonesToSellers($zones, $sellerDocuments);
        $zonesBySeller = array_fill(0, count($sellerDocuments), []);

        foreach ($ownerByZone as $zoneIndex => $sellerIndex) {
            $zonesBySeller[$sellerIndex] = $zones[$zoneIndex];
        }

        foreach ($sellerDocuments as $sellerIndex => $document) {
            $sellerClients = array_map(
                fn (array $projected): Client => $projected['client'],
                $zonesBySeller[$sellerIndex],
            );

            usort(
                $sellerClients,
                fn (Client $first, Client $second): int => strcmp((string) $first->id_client, (string) $second->id_client),
            );

            $assignments[$document] = $sellerClients;
        }

        return $assignments;
    }

    /**
     * Proyección plana local (equirectangular): la longitud se multiplica por el
     * coseno de la latitud media porque un grado de longitud se acorta con la
     * latitud, mientras que un grado de latitud mide prácticamente lo mismo. Para
     * una ciudad es suficiente para comparar cercanías sin trigonometría por par.
     *
     * @param  Collection<int, Client>  $clients
     * @return array<int, array{client: Client, x: float, y: float}>
     */
    private function projectClients(Collection $clients): array
    {
        $meanLatitude = (float) $clients->avg(fn (Client $client): float => (float) $client->latitude);
        $longitudeScale = cos(deg2rad($meanLatitude));

        return $clients->map(fn (Client $client): array => [
            'client' => $client,
            'x' => (float) $client->longitude * $longitudeScale,
            'y' => (float) $client->latitude,
        ])->values()->all();
    }

    /**
     * Cupos por zona: base = intdiv(K, N) y las primeras K % N zonas reciben uno más.
     * Así la diferencia de clientes entre dos zonas nunca supera 1.
     *
     * @return array<int, int>
     */
    private function zoneSizes(int $clientCount, int $sellerCount): array
    {
        $base = intdiv($clientCount, $sellerCount);
        $extra = $clientCount % $sellerCount;
        $sizes = [];

        for ($index = 0; $index < $sellerCount; $index++) {
            $sizes[] = $base + ($index < $extra ? 1 : 0);
        }

        return $sizes;
    }

    /**
     * Divide recursivamente los clientes en zonas del tamaño indicado. En cada
     * bisección ordena por el eje de mayor extensión (empate: x), usa el otro eje
     * y el id_client como desempate, y entrega los primeros cupos a la primera
     * mitad. El orden de las zonas es el mismo que el de $sizes.
     *
     * @param  array<int, array{client: Client, x: float, y: float}>  $items
     * @param  array<int, int>  $sizes
     * @return array<int, array<int, array{client: Client, x: float, y: float}>>
     */
    private function partitionZones(array $items, array $sizes): array
    {
        if (count($sizes) === 1) {
            return [$items];
        }

        if ($items === []) {
            return array_fill(0, count($sizes), []);
        }

        $firstHalfZoneCount = intdiv(count($sizes), 2);
        $firstSizes = array_slice($sizes, 0, $firstHalfZoneCount);
        $secondSizes = array_slice($sizes, $firstHalfZoneCount);
        $firstClientCount = array_sum($firstSizes);

        [$axis, $otherAxis] = $this->widestAxis($items);

        usort($items, fn (array $first, array $second): int => [
            $first[$axis],
            $first[$otherAxis],
            (string) $first['client']->id_client,
        ] <=> [
            $second[$axis],
            $second[$otherAxis],
            (string) $second['client']->id_client,
        ]);

        return array_merge(
            $this->partitionZones(array_slice($items, 0, $firstClientCount), $firstSizes),
            $this->partitionZones(array_slice($items, $firstClientCount), $secondSizes),
        );
    }

    /**
     * @param  array<int, array{client: Client, x: float, y: float}>  $items
     * @return array{0: string, 1: string} Eje principal y eje de desempate.
     */
    private function widestAxis(array $items): array
    {
        $xValues = array_column($items, 'x');
        $yValues = array_column($items, 'y');
        $xExtent = max($xValues) - min($xValues);
        $yExtent = max($yValues) - min($yValues);

        return $xExtent >= $yExtent ? ['x', 'y'] : ['y', 'x'];
    }

    /**
     * Refina los límites de las zonas sin cambiar sus tamaños. En cada pasada
     * calcula los centroides y evalúa intercambios entre pares de zonas; solo
     * aplica los que reducen estrictamente la suma de distancias cuadradas a los
     * centroides. Se detiene al completar 20 pasadas o una pasada sin cambios.
     *
     * @param  array<int, array<int, array{client: Client, x: float, y: float}>>  $zones
     */
    private function refineZones(array &$zones): void
    {
        for ($pass = 0; $pass < 20; $pass++) {
            $centroids = [];

            foreach ($zones as $zoneIndex => $zone) {
                $centroids[$zoneIndex] = $this->centroid($zone);
            }

            $swapped = false;
            $zoneCount = count($zones);

            for ($firstZone = 0; $firstZone < $zoneCount - 1; $firstZone++) {
                for ($secondZone = $firstZone + 1; $secondZone < $zoneCount; $secondZone++) {
                    if ($centroids[$firstZone] === null || $centroids[$secondZone] === null) {
                        continue;
                    }

                    $firstCount = count($zones[$firstZone]);
                    $secondCount = count($zones[$secondZone]);

                    for ($firstIndex = 0; $firstIndex < $firstCount; $firstIndex++) {
                        for ($secondIndex = 0; $secondIndex < $secondCount; $secondIndex++) {
                            $firstItem = $zones[$firstZone][$firstIndex];
                            $secondItem = $zones[$secondZone][$secondIndex];

                            $currentCost = $this->squaredDistance($firstItem, $centroids[$firstZone])
                                + $this->squaredDistance($secondItem, $centroids[$secondZone]);
                            $swappedCost = $this->squaredDistance($firstItem, $centroids[$secondZone])
                                + $this->squaredDistance($secondItem, $centroids[$firstZone]);

                            if ($currentCost - $swappedCost > 1e-12) {
                                $zones[$firstZone][$firstIndex] = $secondItem;
                                $zones[$secondZone][$secondIndex] = $firstItem;
                                $swapped = true;
                            }
                        }
                    }
                }
            }

            if (! $swapped) {
                break;
            }
        }
    }

    /**
     * @param  array<int, array{client: Client, x: float, y: float}>  $zone
     * @return array{x: float, y: float}|null
     */
    private function centroid(array $zone): ?array
    {
        if ($zone === []) {
            return null;
        }

        $sumX = 0.0;
        $sumY = 0.0;

        foreach ($zone as $item) {
            $sumX += $item['x'];
            $sumY += $item['y'];
        }

        $count = count($zone);

        return ['x' => $sumX / $count, 'y' => $sumY / $count];
    }

    /**
     * @param  array{client: Client, x: float, y: float}  $item
     * @param  array{x: float, y: float}  $centroid
     */
    private function squaredDistance(array $item, array $centroid): float
    {
        $deltaX = $item['x'] - $centroid['x'];
        $deltaY = $item['y'] - $centroid['y'];

        return ($deltaX * $deltaX) + ($deltaY * $deltaY);
    }

    /**
     * Empareja cada zona con un vendedor maximizando el solapamiento con la
     * asignación vigente (document_employee actual de cada cliente). Elige siempre
     * el par libre con más clientes coincidentes; ante empate gana la zona de menor
     * índice y luego el vendedor de menor documento.
     *
     * @param  array<int, array<int, array{client: Client, x: float, y: float}>>  $zones
     * @param  array<int, string>  $sellerDocuments
     * @return array<int, int> Índice de zona => índice de vendedor.
     */
    private function matchZonesToSellers(array $zones, array $sellerDocuments): array
    {
        $overlap = [];

        foreach ($zones as $zoneIndex => $zone) {
            foreach ($sellerDocuments as $sellerIndex => $document) {
                $overlap[$zoneIndex][$sellerIndex] = 0;

                foreach ($zone as $item) {
                    if ($item['client']->document_employee === $document) {
                        $overlap[$zoneIndex][$sellerIndex]++;
                    }
                }
            }
        }

        $ownerByZone = [];
        $usedSellers = [];
        $zoneCount = count($zones);

        for ($assignment = 0; $assignment < $zoneCount; $assignment++) {
            $bestZone = null;
            $bestSeller = null;
            $bestOverlap = -1;

            foreach ($zones as $zoneIndex => $zone) {
                if (isset($ownerByZone[$zoneIndex])) {
                    continue;
                }

                foreach ($sellerDocuments as $sellerIndex => $document) {
                    if (isset($usedSellers[$sellerIndex])) {
                        continue;
                    }

                    if ($overlap[$zoneIndex][$sellerIndex] > $bestOverlap) {
                        $bestOverlap = $overlap[$zoneIndex][$sellerIndex];
                        $bestZone = $zoneIndex;
                        $bestSeller = $sellerIndex;
                    }
                }
            }

            if ($bestZone === null || $bestSeller === null) {
                break;
            }

            $ownerByZone[$bestZone] = $bestSeller;
            $usedSellers[$bestSeller] = true;
        }

        return $ownerByZone;
    }

    /**
     * @return array{
     *     fingerprint: string,
     *     summary: array{clients: int, sellers: int, skipped_without_coordinates: int},
     *     sellers: array<int, array<string, mixed>>,
     *     skipped: array<int, array<string, string>>,
     *     assignments: array<string, array<int, Client>>
     * }
     */
    public function plan(): array
    {
        $clients = $this->eligibleClients();
        $sellers = $this->sellers();
        $skipped = $this->skippedClients();
        $assignments = $this->assign($clients, $sellers);

        $currentClients = Client::query()
            ->select('document_employee', DB::raw('count(*) as total'))
            ->whereNotNull('document_employee')
            ->groupBy('document_employee')
            ->pluck('total', 'document_employee');

        $sellerPlans = [];
        $pairs = [];

        foreach ($sellers as $seller) {
            $document = $seller->document_employee;
            $sellerClients = collect($assignments[$document] ?? []);

            $sellerPlans[] = [
                'document_employee' => $document,
                'name' => trim(implode(' ', array_filter([
                    $seller->name_1,
                    $seller->name_2,
                    $seller->last_name_1,
                    $seller->last_name_2,
                ]))),
                'current_clients' => (int) ($currentClients[$document] ?? 0),
                'new_clients' => $sellerClients->count(),
                'clients' => $sellerClients->map(function ($client) use ($document, &$pairs) {
                    $pairs[$client->id_client] = $document;

                    return [
                        'id_client' => $client->id_client,
                        'business_name' => $client->business_name,
                        'address' => $client->address,
                        'latitude' => $client->latitude,
                        'longitude' => $client->longitude,
                        'previous_document_employee' => $client->document_employee,
                    ];
                })->values()->all(),
            ];
        }

        ksort($pairs, SORT_STRING);

        return [
            'fingerprint' => sha1(json_encode($pairs)),
            'summary' => [
                'clients' => $clients->count(),
                'sellers' => $sellers->count(),
                'skipped_without_coordinates' => $skipped->count(),
            ],
            'sellers' => $sellerPlans,
            'skipped' => $skipped->map(function ($client) {
                return [
                    'id_client' => $client->id_client,
                    'business_name' => $client->business_name,
                    'reason' => 'sin_coordenadas',
                ];
            })->values()->all(),
            'assignments' => $assignments,
        ];
    }

    /**
     * @return array{clients: int, routes: int, suggestions_closed: int}
     */
    public function apply(string $adminDocument): array
    {
        return DB::transaction(function () use ($adminDocument) {
            $plan = $this->plan();
            $assignments = $plan['assignments'];

            if ($plan['summary']['sellers'] === 0 || $plan['summary']['clients'] === 0) {
                return [
                    'clients' => 0,
                    'routes' => 0,
                    'suggestions_closed' => 0,
                ];
            }

            $eligibleClientIds = collect($assignments)
                ->flatMap(fn (array $clients) => collect($clients)->pluck('id_client'))
                ->values()
                ->all();

            DeliveryRoute::whereIn('id_client', $eligibleClientIds)->delete();

            $appliedClients = 0;
            $appliedRoutes = 0;

            foreach ($assignments as $document => $sellerClients) {
                foreach ($sellerClients as $client) {
                    Client::whereKey($client->id_client)
                        ->update(['document_employee' => $document]);

                    DeliveryRoute::create([
                        'id_ruta' => strtoupper(Str::random(8)),
                        'id_client' => $client->id_client,
                        'document_employee' => $document,
                    ]);

                    $appliedClients++;
                    $appliedRoutes++;
                }
            }

            $suggestionsClosed = RouteSuggestion::where('status', 'P')
                ->update([
                    'status' => 'R',
                    'resolved_at' => now(),
                    'resolved_by' => $adminDocument,
                ]);

            return [
                'clients' => $appliedClients,
                'routes' => $appliedRoutes,
                'suggestions_closed' => $suggestionsClosed,
            ];
        });
    }

    public static function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
