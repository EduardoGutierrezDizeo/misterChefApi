<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = config('misterchef.admin_password');

        if (! filled($adminPassword)) {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'ADMIN_PASSWORD no está definida. Configura la variable de entorno ADMIN_PASSWORD '
                    .'con una contraseña segura antes de ejecutar los seeders en producción.'
                );
            }

            $adminPassword = 'admin123';
        }

        $employees = [
            [
                'document_employee' => '1000000001',
                'name_1' => 'Andrea',
                'name_2' => 'María',
                'last_name_1' => 'Rojas',
                'last_name_2' => 'Suárez',
                'phone_number' => '3001234567',
                'status' => 'A',
                'email' => 'admin@misterchef.com',
                'password' => Hash::make($adminPassword),
                'type' => 'A',
                'commission_percentage' => 0,
                'hire_date' => '2025-01-15',
                'can_modify_invoice' => 'S',
                'first_login' => false,
            ],
            [
                'document_employee' => '1000000002',
                'name_1' => 'Jorge',
                'name_2' => 'Luis',
                'last_name_1' => 'Martínez',
                'last_name_2' => 'Cruz',
                'phone_number' => '3002345678',
                'status' => 'A',
                'email' => 'vendedor@misterchef.com',
                'password' => Hash::make('vendedor123'),
                'type' => 'V',
                'commission_percentage' => 5,
                'hire_date' => '2025-02-01',
                'can_modify_invoice' => 'S',
                'first_login' => false,
            ],
            [
                'document_employee' => '1000000003',
                'name_1' => 'Diana',
                'name_2' => 'Carolina',
                'last_name_1' => 'Fernández',
                'last_name_2' => 'López',
                'phone_number' => '3003456789',
                'status' => 'A',
                'email' => 'vendedor2@misterchef.com',
                'password' => Hash::make('vendedor123'),
                'type' => 'V',
                'commission_percentage' => 5,
                'hire_date' => '2025-03-10',
                'can_modify_invoice' => 'S',
                'first_login' => false,
            ],
            [
                'document_employee' => '1000000004',
                'name_1' => 'Fernando',
                'name_2' => null,
                'last_name_1' => 'Gutiérrez',
                'last_name_2' => null,
                'phone_number' => '3004567890',
                'status' => 'I',
                'email' => 'inactivo@misterchef.com',
                'password' => Hash::make('vendedor123'),
                'type' => 'V',
                'commission_percentage' => 0,
                'hire_date' => '2025-04-01',
                'can_modify_invoice' => 'N',
                'first_login' => false,
            ],
        ];

        foreach ($employees as $employee) {
            Employee::updateOrCreate(
                ['document_employee' => $employee['document_employee']],
                $employee,
            );
        }
    }
}
