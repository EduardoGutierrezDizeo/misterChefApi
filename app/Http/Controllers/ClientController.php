<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    // GET /api/v1/clients
    public function index(Request $request)
    {
        $employee = $request->user();

        $query = Client::with(['city', 'city.department', 'employee']);

        // Vendedor solo ve sus propios clientes
        // Administrador ve todos
        if ($employee->type === 'V') {
            $query->where('document_employee', $employee->document_employee);
        }

        // Filtro opcional por status: ?status=true o ?status=false
        if ($request->has('status')) {
            $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        $clients = $query->get();

        return response()->json($clients, 200);
    }

    // GET /api/v1/clients/{id}
    public function show(Request $request, $id)
    {
        $employee = $request->user();

        $client = Client::with(['city', 'city.department', 'employee', 'invoices'])->find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        // Vendedor solo puede ver sus propios clientes
        if ($employee->type === 'V' && $client->document_employee !== $employee->document_employee) {
            return response()->json([
                'message' => 'No tienes permiso para ver este cliente.',
            ], 403);
        }

        return response()->json($client, 200);
    }

    // POST /api/v1/clients
    public function store(Request $request)
    {
        $request->validate([
            'id_client'         => 'required|string|max:5|unique:client,id_client',
            'client_name1'      => 'required|string|max:25',
            'client_name2'      => 'nullable|string|max:25',
            'client_last_name1' => 'required|string|max:25',
            'client_last_name2' => 'nullable|string|max:25',
            'business_name'     => 'required|string|max:25',
            'address'           => 'required|string|max:50',
            'longitude'         => 'required|numeric|between:-180,180',
            'latitude'          => 'required|numeric|between:-90,90',
            'phone_number'      => 'nullable|string|max:12',
            'status'            => 'required|boolean',
            'id_departament'    => 'required|string|exists:department,id_departament',
            'id_city'           => 'required|string|exists:city,id_city',
        ]);

        // El cliente queda asignado al empleado autenticado
        $data = $request->all();
        $data['document_employee'] = $request->user()->document_employee;

        $client = Client::create($data);
        $client->load(['city', 'city.department', 'employee']);

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'client'  => $client,
        ], 201);
    }

    // PUT /api/v1/clients/{id}
    public function update(Request $request, $id)
    {
        $employee = $request->user();

        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        // Solo el vendedor asignado o un administrador puede actualizar
        if ($employee->type === 'V' && $client->document_employee !== $employee->document_employee) {
            return response()->json([
                'message' => 'No tienes permiso para actualizar este cliente.',
            ], 403);
        }

        $request->validate([
            'client_name1'      => 'sometimes|string|max:25',
            'client_name2'      => 'nullable|string|max:25',
            'client_last_name1' => 'sometimes|string|max:25',
            'client_last_name2' => 'nullable|string|max:25',
            'business_name'     => 'sometimes|string|max:25',
            'address'           => 'sometimes|string|max:50',
            'longitude'         => 'sometimes|numeric|between:-180,180',
            'latitude'          => 'sometimes|numeric|between:-90,90',
            'phone_number'      => 'nullable|string|max:12',
            'id_departament'    => 'sometimes|string|exists:department,id_departament',
            'id_city'           => 'sometimes|string|exists:city,id_city',
        ]);

        $client->update($request->only([
            'client_name1',
            'client_name2',
            'client_last_name1',
            'client_last_name2',
            'business_name',
            'address',
            'longitude',
            'latitude',
            'phone_number',
            'id_departament',
            'id_city',
        ]));

        $client->load(['city', 'city.department', 'employee']);

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'client'  => $client,
        ], 200);
    }

    // PATCH /api/v1/clients/{id}/status
    public function changeStatus(Request $request, $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        $request->validate([
            'status' => 'required|boolean',
        ]);

        $client->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Estado del cliente actualizado correctamente.',
            'client'  => $client,
        ], 200);
    }
}