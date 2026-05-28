<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    // GET /api/v1/employees
    public function index(Request $request)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden listar empleados.',
            ], 403);
        }

        $employees = Employee::select([
            'document_employee', 'name_1', 'name_2',
            'last_name_1', 'last_name_2', 'phone_number',
            'status', 'email', 'type', 'commission_percentage',
            'hire_date', 'can_modify_invoice',
        ])->get();

        return response()->json($employees, 200);
    }

    // GET /api/v1/employees/{id}
    public function show(Request $request, $id)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden ver empleados.',
            ], 403);
        }

        $employee = Employee::select([
            'document_employee', 'name_1', 'name_2',
            'last_name_1', 'last_name_2', 'phone_number',
            'status', 'email', 'type', 'commission_percentage',
            'hire_date', 'can_modify_invoice',
        ])->find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Empleado no encontrado.',
            ], 404);
        }

        return response()->json($employee, 200);
    }

    // POST /api/v1/employees
    public function store(Request $request)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden crear empleados.',
            ], 403);
        }

        $request->validate([
            'document_employee'    => 'required|string|max:15|unique:employee,document_employee',
            'name_1'               => 'required|string|max:20',
            'name_2'               => 'nullable|string|max:20',
            'last_name_1'          => 'required|string|max:20',
            'last_name_2'          => 'nullable|string|max:20',
            'phone_number'         => 'nullable|string|max:12',
            'email'                => 'required|email|max:50|unique:employee,email',
            'password'             => 'required|string|min:6',
            'type'                 => 'required|in:A,V',
            'status'               => 'sometimes|in:A,I',
            'commission_percentage'=> 'nullable|numeric|min:0|max:100',
            'hire_date'            => 'nullable|date',
            'can_modify_invoice'   => 'sometimes|in:S,N',
        ]);

        $employee = Employee::create([
            'document_employee'     => $request->document_employee,
            'name_1'                => $request->name_1,
            'name_2'                => $request->name_2,
            'last_name_1'           => $request->last_name_1,
            'last_name_2'           => $request->last_name_2,
            'phone_number'          => $request->phone_number,
            'email'                 => $request->email,
            'password'              => Hash::make($request->password),
            'type'                  => $request->type,
            'status'                => $request->input('status', 'A'),
            'commission_percentage' => $request->input('commission_percentage', 0),
            'hire_date'             => $request->hire_date,
            'can_modify_invoice'    => $request->input('can_modify_invoice', 'N'),
        ]);

        return response()->json([
            'message'  => 'Empleado creado correctamente.',
            'employee' => [
                'document_employee'    => $employee->document_employee,
                'name_1'               => $employee->name_1,
                'name_2'               => $employee->name_2,
                'last_name_1'          => $employee->last_name_1,
                'last_name_2'          => $employee->last_name_2,
                'phone_number'         => $employee->phone_number,
                'email'                => $employee->email,
                'type'                 => $employee->type,
                'status'               => $employee->status,
                'commission_percentage'=> $employee->commission_percentage,
                'hire_date'            => $employee->hire_date,
                'can_modify_invoice'   => $employee->can_modify_invoice,
            ],
        ], 201);
    }

    // PUT /api/v1/employees/{id}
    public function update(Request $request, $id)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden actualizar empleados.',
            ], 403);
        }

        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Empleado no encontrado.',
            ], 404);
        }

        $request->validate([
            'name_1'               => 'sometimes|string|max:20',
            'name_2'               => 'nullable|string|max:20',
            'last_name_1'          => 'sometimes|string|max:20',
            'last_name_2'          => 'nullable|string|max:20',
            'phone_number'         => 'nullable|string|max:12',
            'email'                => 'sometimes|email|max:50|unique:employee,email,' . $id . ',document_employee',
            'password'             => 'nullable|string|min:6',
            'type'                 => 'sometimes|in:A,V',
            'commission_percentage'=> 'nullable|numeric|min:0|max:100',
            'hire_date'            => 'nullable|date',
            'can_modify_invoice'   => 'sometimes|in:S,N',
        ]);

        $data = $request->only([
            'name_1', 'name_2', 'last_name_1', 'last_name_2',
            'phone_number', 'email', 'type',
            'commission_percentage', 'hire_date', 'can_modify_invoice',
        ]);

        // Solo actualizar contraseña si se envió
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $employee->update($data);

        return response()->json([
            'message'  => 'Empleado actualizado correctamente.',
            'employee' => [
                'document_employee'    => $employee->document_employee,
                'name_1'               => $employee->name_1,
                'name_2'               => $employee->name_2,
                'last_name_1'          => $employee->last_name_1,
                'last_name_2'          => $employee->last_name_2,
                'phone_number'         => $employee->phone_number,
                'email'                => $employee->email,
                'type'                 => $employee->type,
                'status'               => $employee->status,
                'commission_percentage'=> $employee->commission_percentage,
                'hire_date'            => $employee->hire_date,
                'can_modify_invoice'   => $employee->can_modify_invoice,
            ],
        ], 200);
    }

    // PATCH /api/v1/employees/{id}/status
    public function changeStatus(Request $request, $id)
    {
        if ($request->user()->type !== 'A') {
            return response()->json([
                'message' => 'Solo los administradores pueden cambiar el estado de un empleado.',
            ], 403);
        }

        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Empleado no encontrado.',
            ], 404);
        }

        $request->validate([
            'status' => 'required|in:A,I',
        ]);

        $employee->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Estado del empleado actualizado correctamente.',
            'employee' => [
                'document_employee' => $employee->document_employee,
                'name_1'            => $employee->name_1,
                'last_name_1'       => $employee->last_name_1,
                'status'            => $employee->status,
            ],
        ], 200);
    }
}