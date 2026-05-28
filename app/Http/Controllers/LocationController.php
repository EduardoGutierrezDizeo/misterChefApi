<?php

namespace App\Http\Controllers;

use App\Models\EmployeeLocation;
use App\Models\Employee;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // POST /api/v1/location
    // Domiciliario envía su ubicación cada 30 segundos
    public function update(Request $request)
    {
        $employee = $request->user();

        if ($employee->type !== 'V') {
            return response()->json([
                'message' => 'Solo los domiciliarios pueden enviar ubicación.',
            ], 403);
        }

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ]);

        // updateOrCreate — si ya existe actualiza, si no existe crea
        $location = EmployeeLocation::updateOrCreate(
            ['document_employee' => $employee->document_employee],
            [
                'latitude'   => $request->latitude,
                'longitude'  => $request->longitude,
                'is_active'  => $request->input('is_active', true),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'message'  => 'Ubicación actualizada correctamente.',
            'location' => $location,
        ], 200);
    }

    // GET /api/v1/location
    // Administrador ve la última ubicación de todos los domiciliarios activos
    public function index(Request $request)
    {
        $employee = $request->user();

        if ($employee->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden ver las ubicaciones.',
            ], 403);
        }

        $locations = EmployeeLocation::with('employee')
            ->where('is_active', true)
            ->get()
            ->map(function ($loc) {
                return [
                    'document_employee' => $loc->document_employee,
                    'name'              => $loc->employee->name_1 . ' ' . $loc->employee->last_name_1,
                    'latitude'          => $loc->latitude,
                    'longitude'         => $loc->longitude,
                    'last_update'       => $loc->updated_at,
                ];
            });

        return response()->json($locations, 200);
    }

    // PATCH /api/v1/location/deactivate
    // Domiciliario se marca como inactivo al terminar su jornada
    public function deactivate(Request $request)
    {
        $employee = $request->user();

        EmployeeLocation::where('document_employee', $employee->document_employee)
            ->update(['is_active' => false]);

        return response()->json([
            'message' => 'Ubicación desactivada. Ya no apareces en el mapa del administrador.',
        ], 200);
    }
}