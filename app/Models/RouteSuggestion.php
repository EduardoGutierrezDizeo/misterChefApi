<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteSuggestion extends Model
{
    protected $table = 'route_suggestion';
    protected $primaryKey = 'id_suggestion';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_suggestion',
        'id_client',
        'document_employee',
        'status',
        'distance_km',
        'created_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'distance_km' => 'decimal:3',
        'created_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client', 'id_client');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'document_employee', 'document_employee');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(Employee::class, 'resolved_by', 'document_employee');
    }
}