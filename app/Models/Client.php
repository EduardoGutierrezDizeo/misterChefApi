<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'client';
    protected $primaryKey = 'id_client';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_client',
        'client_name1',
        'client_name2',
        'client_last_name1',
        'client_last_name2',
        'business_name',
        'address',
        'longitude',
        'latitude',
        'phone_number',
        'status',
        'document_employee',
        'id_departament',
        'id_city',
    ];

    protected $casts = [
        'status'    => 'boolean',
        'longitude' => 'float',
        'latitude' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'document_employee', 'document_employee');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'id_city', 'id_city');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'id_client', 'id_client');
    }

    public function routes()
    {
        return $this->hasMany(DeliveryRoute::class, 'id_client', 'id_client');
    }

    public function routeSuggestion()
    {
        return $this->hasOne(RouteSuggestion::class, 'id_client', 'id_client');
    }
}