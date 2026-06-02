<?php

namespace App\Http\Controllers;

use App\Models\EmployeeLocation;
use App\Models\Department;
use App\Models\City;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // POST /api/v1/location
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
    public function deactivate(Request $request)
    {
        EmployeeLocation::where('document_employee', $request->user()->document_employee)
            ->update(['is_active' => false]);

        return response()->json([
            'message' => 'Ubicación desactivada. Ya no apareces en el mapa del administrador.',
        ], 200);
    }

    // GET /api/v1/departments
    public function departments()
    {
        $departments = Department::select('id_departament', 'name_departament')->get();

        return response()->json($departments, 200);
    }

    // GET /api/v1/cities?id_departament=D001
    public function cities(Request $request)
    {
        $query = City::select('id_city', 'name_city', 'id_departament');

        if ($request->has('id_departament')) {
            $query->where('id_departament', $request->id_departament);
        }

        return response()->json($query->get(), 200);
    }
}