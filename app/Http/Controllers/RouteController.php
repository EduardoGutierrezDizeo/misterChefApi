<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use App\Models\RouteSuggestion;
use App\Services\RouteDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RouteController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/routes
    // Domiciliario ve su ruta con los puntos de los clientes en el mapa
    // Administrador ve todas las rutas
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $employee = $request->user();

        $query = DeliveryRoute::with([
            'client' => function ($q) {
                $q->select('id_client', 'client_name1', 'client_last_name1',
                    'business_name', 'address', 'latitude', 'longitude',
                    'phone_number', 'status');
            },
            'employee' => function ($q) {
                $q->select('document_employee', 'name_1', 'last_name_1', 'phone_number');
            },
        ]);

        if ($employee->type === 'V') {
            $query->where('document_employee', $employee->document_employee);
        }

        $routes = $query->get();

        // Agrupar por domiciliario para el mapa
        $grouped = $routes->groupBy('document_employee')->map(function ($items, $docEmployee) {
            $emp = $items->first()->employee;

            return [
                'document_employee' => $docEmployee,
                'employee_name' => $emp->name_1.' '.$emp->last_name_1,
                'phone_number' => $emp->phone_number,
                'total_clients' => $items->count(),
                'clients' => $items->map(function ($r) {
                    return [
                        'id_ruta' => $r->id_ruta,
                        'id_client' => $r->client->id_client,
                        'client_name1' => $r->client->client_name1,
                        'client_last_name1' => $r->client->client_last_name1,
                        'name' => $r->client->client_name1.' '.$r->client->client_last_name1,
                        'business_name' => $r->client->business_name,
                        'address' => $r->client->address,
                        'latitude' => $r->client->latitude,
                        'longitude' => $r->client->longitude,
                        'phone_number' => $r->client->phone_number,
                        'status' => $r->client->status,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($grouped, 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/routes/navigate/{clientId}
    // Domiciliario obtiene las coordenadas de un cliente para navegar en Google Maps
    // ─────────────────────────────────────────────────────────────────────────
    public function navigate(Request $request, $clientId)
    {
        $employee = $request->user();

        // Verificar que el cliente pertenece a la ruta del domiciliario
        $route = DeliveryRoute::where('id_client', $clientId)
            ->where('document_employee', $employee->document_employee)
            ->with('client')
            ->first();

        if (! $route) {
            return response()->json([
                'message' => 'Este cliente no pertenece a tu ruta.',
            ], 403);
        }

        $client = $route->client;

        if (! $client->latitude || ! $client->longitude) {
            return response()->json([
                'message' => 'Este cliente no tiene coordenadas registradas.',
            ], 422);
        }

        return response()->json([
            'id_client' => $client->id_client,
            'name' => $client->client_name1.' '.$client->client_last_name1,
            'business_name' => $client->business_name,
            'address' => $client->address,
            'latitude' => $client->latitude,
            'longitude' => $client->longitude,
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/routes/distribute
    // Distribuye automáticamente los clientes sin ruta entre los domiciliarios
    // activos usando algoritmo de proximidad geográfica (K-means geográfico)
    // ─────────────────────────────────────────────────────────────────────────
    public function distribute(Request $request)
    {
        $employee = $request->user();

        if ($employee->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden distribuir rutas.',
            ], 403);
        }

        // Clientes activos sin ruta asignada
        $clientsWithoutRoute = Client::where('status', true)
            ->whereNotIn('id_client', DeliveryRoute::pluck('id_client'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($clientsWithoutRoute->isEmpty()) {
            return response()->json([
                'message' => 'No hay clientes pendientes de asignación.',
            ], 200);
        }

        // Domiciliarios activos
        $deliveryEmployees = Employee::where('type', 'V')
            ->where('status', 'A')
            ->get();

        if ($deliveryEmployees->isEmpty()) {
            return response()->json([
                'message' => 'No hay domiciliarios activos disponibles.',
            ], 422);
        }

        // Algoritmo de distribución por zonas geográficas
        // Para cada cliente sin ruta, encuentra el domiciliario más cercano
        // considerando el balance de carga (cantidad actual de clientes)
        $currentCounts = DeliveryRoute::select('document_employee', DB::raw('count(*) as total'))
            ->groupBy('document_employee')
            ->pluck('total', 'document_employee')
            ->toArray();

        foreach ($deliveryEmployees as $emp) {
            if (! isset($currentCounts[$emp->document_employee])) {
                $currentCounts[$emp->document_employee] = 0;
            }
        }

        $suggestions = [];
        $tempCounts = $currentCounts;

        foreach ($clientsWithoutRoute as $client) {
            $bestEmployee = null;
            $bestScore = PHP_FLOAT_MAX;

            foreach ($deliveryEmployees as $emp) {
                // Calcular distancia promedio del cliente a los clientes existentes del domiciliario
                $existingClients = DeliveryRoute::where('document_employee', $emp->document_employee)
                    ->with('client')
                    ->get()
                    ->pluck('client')
                    ->filter(fn ($c) => $c && $c->latitude && $c->longitude);

                if ($existingClients->isEmpty()) {
                    // Si el domiciliario no tiene clientes, usar distancia 0
                    $avgDistance = 0;
                } else {
                    $totalDist = $existingClients->sum(function ($c) use ($client) {
                        return $this->haversineDistance(
                            $client->latitude, $client->longitude,
                            $c->latitude, $c->longitude
                        );
                    });
                    $avgDistance = $totalDist / $existingClients->count();
                }

                // Score = distancia promedio + penalización por carga (10km por cada cliente extra)
                $loadPenalty = $tempCounts[$emp->document_employee] * 10;
                $score = $avgDistance + $loadPenalty;

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestEmployee = $emp;
                }
            }

            $suggestions[] = [
                'client' => $client,
                'employee' => $bestEmployee,
                'distance_km' => round($bestScore, 3),
            ];

            $tempCounts[$bestEmployee->document_employee]++;
        }

        // Guardar sugerencias en BD para que el admin apruebe o rechace
        DB::transaction(function () use ($suggestions) {
            foreach ($suggestions as $s) {
                // Evitar duplicados si ya existe una sugerencia pendiente para ese cliente
                $exists = RouteSuggestion::where('id_client', $s['client']->id_client)
                    ->where('status', 'P')
                    ->exists();

                if (! $exists) {
                    RouteSuggestion::create([
                        'id_suggestion' => strtoupper(Str::random(10)),
                        'id_client' => $s['client']->id_client,
                        'document_employee' => $s['employee']->document_employee,
                        'status' => 'P',
                        'distance_km' => $s['distance_km'],
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Sugerencias de distribución generadas correctamente.',
            'total' => count($suggestions),
            'suggestions' => collect($suggestions)->map(fn ($s) => [
                'id_client' => $s['client']->id_client,
                'client_name' => $s['client']->client_name1.' '.$s['client']->client_last_name1,
                'business_name' => $s['client']->business_name,
                'suggested_employee' => $s['employee']->name_1.' '.$s['employee']->last_name_1,
                'distance_km' => $s['distance_km'],
            ])->values(),
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/routes/distribution/preview
    // Vista previa de la redistribución total, sin escribir en la BD
    // ─────────────────────────────────────────────────────────────────────────
    public function distributionPreview(Request $request, RouteDistributionService $service)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden distribuir rutas.',
            ], 403);
        }

        $plan = $service->plan();

        if ($plan['summary']['sellers'] === 0) {
            return response()->json([
                'message' => 'No hay vendedores activos para distribuir.',
            ], 422);
        }

        if ($plan['summary']['clients'] === 0) {
            return response()->json([
                'message' => 'No hay clientes elegibles para distribuir.',
            ], 422);
        }

        return response()->json([
            'fingerprint' => $plan['fingerprint'],
            'summary' => $plan['summary'],
            'sellers' => $plan['sellers'],
            'skipped' => $plan['skipped'],
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/routes/distribution/apply
    // Aplica la redistribución total validando el fingerprint de la vista previa
    // ─────────────────────────────────────────────────────────────────────────
    public function distributionApply(Request $request, RouteDistributionService $service)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden distribuir rutas.',
            ], 403);
        }

        $request->validate([
            'fingerprint' => 'required|string',
        ]);

        $plan = $service->plan();

        if ($plan['summary']['sellers'] === 0) {
            return response()->json([
                'message' => 'No hay vendedores activos para distribuir.',
            ], 422);
        }

        if ($plan['summary']['clients'] === 0) {
            return response()->json([
                'message' => 'No hay clientes elegibles para distribuir.',
            ], 422);
        }

        if ($plan['fingerprint'] !== $request->fingerprint) {
            return response()->json([
                'message' => 'El reparto cambió desde la vista previa. Vuelve a calcularlo.',
            ], 409);
        }

        $applied = $service->apply($request->user()->document_employee);

        return response()->json([
            'message' => 'Distribución aplicada correctamente.',
            'applied' => $applied,
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/route-suggestions
    // Administrador ve todas las sugerencias pendientes
    // ─────────────────────────────────────────────────────────────────────────
    public function suggestions(Request $request)
    {
        $employee = $request->user();

        if ($employee->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden ver las sugerencias.',
            ], 403);
        }

        $suggestions = RouteSuggestion::with(['client', 'employee', 'resolvedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($suggestions, 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/v1/route-suggestions/{id}/approve
    // Administrador aprueba la sugerencia — se crea la ruta
    // ─────────────────────────────────────────────────────────────────────────
    public function approveSuggestion(Request $request, $id)
    {
        $employee = $request->user();

        if ($employee->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden aprobar sugerencias.',
            ], 403);
        }

        $suggestion = RouteSuggestion::find($id);

        if (! $suggestion) {
            return response()->json(['message' => 'Sugerencia no encontrada.'], 404);
        }

        if ($suggestion->status !== 'P') {
            return response()->json(['message' => 'Esta sugerencia ya fue procesada.'], 422);
        }

        $seller = Employee::find($suggestion->document_employee);

        if (! $seller || $seller->type !== 'V' || $seller->status !== 'A') {
            return response()->json([
                'message' => 'El vendedor destino no existe o no está activo.',
            ], 422);
        }

        DB::transaction(function () use ($suggestion, $employee, $seller) {
            DeliveryRoute::where('id_client', $suggestion->id_client)->delete();

            DeliveryRoute::create([
                'id_ruta' => strtoupper(Str::random(8)),
                'id_client' => $suggestion->id_client,
                'document_employee' => $seller->document_employee,
            ]);

            Client::whereKey($suggestion->id_client)
                ->update(['document_employee' => $seller->document_employee]);

            $suggestion->update([
                'status' => 'A',
                'resolved_at' => now(),
                'resolved_by' => $employee->document_employee,
            ]);
        });

        return response()->json([
            'message' => 'Sugerencia aprobada. Cliente asignado a la ruta del domiciliario.',
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/v1/route-suggestions/{id}/reject
    // Administrador rechaza la sugerencia y elige manualmente el domiciliario
    // ─────────────────────────────────────────────────────────────────────────
    public function rejectSuggestion(Request $request, $id)
    {
        $employee = $request->user();

        if ($employee->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden rechazar sugerencias.',
            ], 403);
        }

        $suggestion = RouteSuggestion::find($id);

        if (! $suggestion) {
            return response()->json(['message' => 'Sugerencia no encontrada.'], 404);
        }

        if ($suggestion->status !== 'P') {
            return response()->json(['message' => 'Esta sugerencia ya fue procesada.'], 422);
        }

        $request->validate([
            'document_employee' => 'required|string',
        ]);

        $seller = Employee::find($request->document_employee);

        if (! $seller || $seller->type !== 'V' || $seller->status !== 'A') {
            return response()->json([
                'message' => 'El vendedor destino no existe o no está activo.',
            ], 422);
        }

        DB::transaction(function () use ($suggestion, $employee, $seller) {
            DeliveryRoute::where('id_client', $suggestion->id_client)->delete();

            DeliveryRoute::create([
                'id_ruta' => strtoupper(Str::random(8)),
                'id_client' => $suggestion->id_client,
                'document_employee' => $seller->document_employee,
            ]);

            Client::whereKey($suggestion->id_client)
                ->update(['document_employee' => $seller->document_employee]);

            $suggestion->update([
                'status' => 'R',
                'document_employee' => $seller->document_employee,
                'resolved_at' => now(),
                'resolved_by' => $employee->document_employee,
            ]);
        });

        return response()->json([
            'message' => 'Sugerencia rechazada. Cliente asignado manualmente al domiciliario seleccionado.',
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/routes/{id}
    // Elimina una asignación de ruta
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden eliminar rutas.',
            ], 403);
        }

        $route = DeliveryRoute::find($id);

        if (! $route) {
            return response()->json(['message' => 'Ruta no encontrada.'], 404);
        }

        $route->delete();

        return response()->json([
            'message' => 'Asignación eliminada correctamente.',
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fórmula de Haversine — calcula distancia en km entre dos coordenadas
    // ─────────────────────────────────────────────────────────────────────────
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        return RouteDistributionService::haversineDistance($lat1, $lon1, $lat2, $lon2);
    }
}
