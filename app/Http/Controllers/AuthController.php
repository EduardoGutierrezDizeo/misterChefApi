<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $employee = Employee::where('email', $request->email)->first();

        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        if ($employee->status !== 'A') {
            return response()->json([
                'message' => 'El empleado se encuentra inactivo. Contacte al administrador.',
            ], 401);
        }

        $token = $employee->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'token'   => $token,
            'employee' => [
                'document_employee'  => $employee->document_employee,
                'first_login'        => (bool) $employee->first_login,
                'name_1'             => $employee->name_1,
                'name_2'             => $employee->name_2,
                'last_name_1'        => $employee->last_name_1,
                'last_name_2'        => $employee->last_name_2,
                'email'              => $employee->email,
                'type'               => $employee->type,
                'status'             => $employee->status,
                'can_modify_invoice' => $employee->can_modify_invoice,
                'phone_number'       => $employee->phone_number,
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ], 200);
    }

    public function me(Request $request)
    {
        $employee = $request->user();

        return response()->json([
            'document_employee'    => $employee->document_employee,
            'name_1'               => $employee->name_1,
            'name_2'               => $employee->name_2,
            'last_name_1'          => $employee->last_name_1,
            'last_name_2'          => $employee->last_name_2,
            'email'                => $employee->email,
            'type'                 => $employee->type,
            'status'               => $employee->status,
            'can_modify_invoice'   => $employee->can_modify_invoice,
            'phone_number'         => $employee->phone_number,
            'commission_percentage'=> $employee->commission_percentage,
            'hire_date'            => $employee->hire_date,
        ], 200);
    }
    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $employee = $request->user();
        $employee->update([
            'password'    => Hash::make($request->password),
            'first_login' => false,
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ], 200);
    }
}