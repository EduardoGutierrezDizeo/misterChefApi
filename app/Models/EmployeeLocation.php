<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLocation extends Model
{
    protected $table = 'employee_location';
    protected $primaryKey = 'document_employee';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'document_employee',
        'latitude',
        'longitude',
        'updated_at',
        'is_active',
    ];

    protected $casts = [
        'latitude'   => 'decimal:7',
        'longitude'  => 'decimal:7',
        'updated_at' => 'datetime',
        'is_active'  => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'document_employee', 'document_employee');
    }
}