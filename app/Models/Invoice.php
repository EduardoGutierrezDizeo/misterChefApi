<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoice';
    protected $primaryKey = 'id_invoice';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_invoice',
        'date',
        'total',
        'status',
        'id_client',
    ];

    protected $casts = [
        'date' => 'date',
        'total' => 'float',
        'status' => 'string',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client', 'id_client');
    }

    public function details()
    {
        return $this->hasMany(Detail::class, 'id_invoice', 'id_invoice');
    }

    public function audits()
{
    return $this->hasMany(AuditInvoice::class, 'id_invoice', 'id_invoice');
}
}