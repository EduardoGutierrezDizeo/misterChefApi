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
     * Este criterio por proximidad será reemplazado más adelante por un algoritmo
     * por zonas, manteniendo esta misma firma.
     *
     * @return array<string, array<int, Client>>
     */
    public function assign(Collection $clients, Collection $sellers): array
    {
        $sortedClients = $clients->sortBy('id_client')->values();
        $sortedSellers = $sellers->sortBy('document_employee')->values();

        $assignments = [];
        $assignedClients = [];

        foreach ($sortedSellers as $seller) {
            $assignments[$seller->document_employee] = [];
            $assignedClients[$seller->document_employee] = [];
        }

        foreach ($sortedClients as $client) {
            $bestDocument = null;
            $bestScore = PHP_FLOAT_MAX;

            foreach ($sortedSellers as $seller) {
                $document = $seller->document_employee;
                $sellerClients = $assignedClients[$document];

                $averageDistance = 0.0;

                if (count($sellerClients) > 0) {
                    $totalDistance = 0.0;

                    foreach ($sellerClients as $assignedClient) {
                        $totalDistance += self::haversineDistance(
                            (float) $client->latitude,
                            (float) $client->longitude,
                            (float) $assignedClient->latitude,
                            (float) $assignedClient->longitude,
                        );
                    }

                    $averageDistance = $totalDistance / count($sellerClients);
                }

                $score = $averageDistance + (count($sellerClients) * 10);

                if ($score < $bestScore || ($score === $bestScore && ($bestDocument === null || strcmp($document, $bestDocument) < 0))) {
                    $bestScore = $score;
                    $bestDocument = $document;
                }
            }

            if ($bestDocument !== null) {
                $assignments[$bestDocument][] = $client;
                $assignedClients[$bestDocument][] = $client;
            }
        }

        return $assignments;
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
