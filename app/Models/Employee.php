<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'employee';
    protected $primaryKey = 'document_employee';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'document_employee',
        'name_1',
        'name_2',
        'last_name_1',
        'last_name_2',
        'phone_number',
        'status',
        'email',
        'password',
        'type',
        'commission_percentage',
        'hire_date',
        'can_modify_invoice',
        'first_login',  
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'commission_percentage' => 'decimal:2',
        'first_login'           => 'boolean',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class, 'document_employee', 'document_employee');
    }

    public function routes()
    {
        return $this->hasMany(DeliveryRoute::class, 'document_employee', 'document_employee');
    }

    public function auditInvoices()
    {
        return $this->hasMany(AuditInvoice::class, 'document_employee', 'document_employee');
    }

    public function location()
    {
        return $this->hasOne(EmployeeLocation::class, 'document_employee', 'document_employee');
    }

    public function routeSuggestions()
    {
        return $this->hasMany(RouteSuggestion::class, 'document_employee', 'document_employee');
    }
}