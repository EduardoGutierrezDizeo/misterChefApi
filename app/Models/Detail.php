<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detail extends Model
{
    protected $table = 'detail';
    protected $primaryKey = ['line_number', 'id_invoice'];
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'line_number',
        'amount',
        'subtotal',
        'id_product',
        'id_invoice',
    ];

    protected $casts = [
        'line_number'   => 'integer',
        'amount' => 'float',
        'subtotal' => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'id_invoice', 'id_invoice');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product');
    }
}