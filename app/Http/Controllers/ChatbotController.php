<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\DeliveryRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    // POST /api/v1/chatbot
    public function handle(Request $request)
    {
        $request->validate([
            'message'   => 'required|string|max:500',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $employee = $request->user();
        $message  = strtolower(trim($request->message));

        $context  = $this->buildContext($message, $employee, $request);
        $response = $this->askGroq($request->message, $context, $employee);

        return response()->json([
            'response' => $response,
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Detecta la intención y consulta los datos necesarios de la BD
    // ─────────────────────────────────────────────────────────────────────────
    private function buildContext(string $message, $employee, Request $request): string
    {
        $context = '';

        // ── Stock de producto ─────────────────────────────────────────────────
        if ($this->contains($message, ['stock', 'unidades', 'quedan', 'hay', 'disponible', 'inventario', 'cantidad'])) {
            $products = Product::with('productType')->get();
            $context .= "INVENTARIO ACTUAL:\n";
            foreach ($products as $p) {
                $alerta = $p->stock <= $p->minimun_stock ? ' ⚠️ STOCK BAJO' : '';
                $estado = $p->status ? 'Activo' : 'Inactivo';
                $context .= "- {$p->product_name} ({$p->productType->type}): {$p->stock} unidades disponibles, precio \${$p->selling_price}, stock mínimo: {$p->minimun_stock}, estado: {$estado}{$alerta}\n";
            }
        }

        // ── Clientes del vendedor ─────────────────────────────────────────────
        if ($this->contains($message, ['cliente', 'clientes', 'visita', 'ruta', 'pendiente', 'falta', 'recorrido'])) {
            $today   = now()->toDateString();
            $clientes = Client::where('document_employee', $employee->document_employee)
                ->where('status', true)
                ->get();

            $clientesVisitados = Invoice::whereHas('client', function ($q) use ($employee) {
                    $q->where('document_employee', $employee->document_employee);
                })
                ->whereRaw('DATE(date) = ?', [$today])
                ->where('status', 'C')
                ->pluck('id_client')
                ->toArray();

            $context .= "\nCLIENTES EN TU RUTA (Total: {$clientes->count()}):\n";
            foreach ($clientes as $c) {
                $visitado = in_array($c->id_client, $clientesVisitados) ? '✅ Visitado hoy' : '⏳ Pendiente';
                $context .= "- {$c->client_name1} {$c->client_last_name1} ({$c->business_name}), dirección: {$c->address}, lat: {$c->latitude}, lng: {$c->longitude} → {$visitado}\n";
            }

            $pendientes = $clientes->count() - count($clientesVisitados);
            $context   .= "Resumen: {$pendientes} clientes pendientes de visitar hoy.\n";
        }

        // ── Reporte del día ───────────────────────────────────────────────────
        if ($this->contains($message, ['hoy', 'día', 'dia', 'reporte', 'resumen', 'ventas', 'pedidos', 'realizado', 'avance', 'progreso'])) {
            $today    = now()->toDateString();
            $invoices = Invoice::whereHas('client', function ($q) use ($employee) {
                    $q->where('document_employee', $employee->document_employee);
                })
                ->whereRaw('DATE(date) = ?', [$today])
                ->get();

            $totalVentas  = $invoices->where('status', 'C')->sum('total');
            $totalPedidos = $invoices->count();
            $confirmadas  = $invoices->where('status', 'C')->count();
            $pendientes   = $invoices->where('status', 'P')->count();
            $anuladas     = $invoices->where('status', 'A')->count();

            $context .= "\nREPORTE DEL DÍA ({$today}):\n";
            $context .= "- Total pedidos generados: {$totalPedidos}\n";
            $context .= "- Pedidos confirmados: {$confirmadas}\n";
            $context .= "- Pedidos pendientes: {$pendientes}\n";
            $context .= "- Pedidos anulados: {$anuladas}\n";
            $context .= "- Total en ventas confirmadas: \${$totalVentas}\n";
        }

        // ── Semanas sin compra ────────────────────────────────────────────────
        if ($this->contains($message, ['semana', 'semanas', 'última compra', 'ultimo pedido', 'no compra', 'sin comprar', 'tiempo'])) {
            $clientes = Client::where('document_employee', $employee->document_employee)
                ->where('status', true)
                ->with(['invoices' => function ($q) {
                    $q->where('status', 'C')->orderBy('date', 'desc');
                }])
                ->get();

            $context .= "\nÚLTIMA COMPRA POR CLIENTE:\n";
            foreach ($clientes as $c) {
                $ultimaFactura = $c->invoices->first();
                if ($ultimaFactura) {
                    $semanas = now()->diffInWeeks($ultimaFactura->date);
                    $context .= "- {$c->client_name1} {$c->client_last_name1} ({$c->business_name}): última compra hace {$semanas} semana(s) ({$ultimaFactura->date})\n";
                } else {
                    $context .= "- {$c->client_name1} {$c->client_last_name1} ({$c->business_name}): nunca ha realizado una compra\n";
                }
            }
        }

        // ── Clientes cercanos por ubicación ──────────────────────────────────
        if ($this->contains($message, ['cerca', 'cercano', 'próximo', 'proximo', 'distancia', 'ubicación', 'ubicacion'])) {
            if ($request->latitude && $request->longitude) {
                $clientes = Client::where('document_employee', $employee->document_employee)
                    ->where('status', true)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->get()
                    ->map(function ($c) use ($request) {
                        $c->distancia = $this->haversineDistance(
                            $request->latitude, $request->longitude,
                            $c->latitude, $c->longitude
                        );
                        return $c;
                    })
                    ->sortBy('distancia')
                    ->take(3);

                $context .= "\nCLIENTES MÁS CERCANOS A TU UBICACIÓN ACTUAL:\n";
                foreach ($clientes as $c) {
                    $dist = round($c->distancia, 2);
                    $context .= "- {$c->client_name1} {$c->client_last_name1} ({$c->business_name}): {$dist} km — {$c->address}\n";
                }
            } else {
                $context .= "\nNOTA: Para consultar clientes cercanos envía tu ubicación (latitude y longitude) en el body del request.\n";
            }
        }

        // ── Recomendaciones de ruta ───────────────────────────────────────────
        if ($this->contains($message, ['recomend', 'orden', 'optimizar', 'eficiente', 'mejor ruta', 'por donde', 'empezar'])) {
            $clientes = Client::where('document_employee', $employee->document_employee)
                ->where('status', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get();

            $today     = now()->toDateString();
            $visitados = Invoice::whereHas('client', function ($q) use ($employee) {
                    $q->where('document_employee', $employee->document_employee);
                })
                ->whereRaw('DATE(date) = ?', [$today])
                ->where('status', 'C')
                ->pluck('id_client')
                ->toArray();

            $pendientes = $clientes->filter(fn($c) => !in_array($c->id_client, $visitados));

            $context .= "\nCLIENTES PENDIENTES CON COORDENADAS (para optimizar ruta):\n";
            foreach ($pendientes as $c) {
                $context .= "- {$c->client_name1} {$c->client_last_name1} ({$c->business_name}): lat {$c->latitude}, lng {$c->longitude}, dirección: {$c->address}\n";
            }
        }

        if (empty($context)) {
            $context = "El empleado es {$employee->name_1} {$employee->last_name_1}, tipo: " . ($employee->type === 'A' ? 'Administrador' : 'Domiciliario') . ".";
        }

        return $context;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Envía la pregunta + contexto a Groq y retorna la respuesta
    // ─────────────────────────────────────────────────────────────────────────
    private function askGroq(string $question, string $context, $employee): string
    {
        $systemPrompt = "Eres un asistente inteligente para la empresa Mister Chef, una empresa de distribución de alimentos. " .
            "Respondes preguntas de los domiciliarios y administradores sobre su trabajo diario. " .
            "Usa un tono amigable y profesional. Responde siempre en español. " .
            "Sé conciso pero completo. Si hay alertas importantes (stock bajo, clientes sin visitar) mencionálas. " .
            "El empleado que pregunta es: {$employee->name_1} {$employee->last_name_1}.\n\n" .
            "DATOS ACTUALES DEL SISTEMA:\n{$context}";

        $payload = [
            'model'    => 'llama-3.3-70b-versatile',
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role'    => 'user',
                    'content' => $question,
                ],
            ],
            'temperature' => 0.7,
            'max_tokens'  => 500,
        ];

        $apiKey = config('services.groq.key');
        $url    = config('services.groq.url');

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? 'No pude generar una respuesta.';
        }

        return 'Error ' . $response->status() . ': ' . $response->body();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Verifica si el mensaje contiene alguna de las palabras clave
    // ─────────────────────────────────────────────────────────────────────────
    private function contains(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fórmula de Haversine — distancia en km entre dos coordenadas
    // ─────────────────────────────────────────────────────────────────────────
    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}