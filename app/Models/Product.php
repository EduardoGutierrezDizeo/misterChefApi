<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'product';
    protected $primaryKey = 'id_product';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_product',
        'product_name',
        'stock',
        'minimun_stock',
        'selling_price',
        'status',
        'id_produc_type',
    ];

    protected $casts = [
        'stock' => 'integer',
        'minimun_stock' => 'integer',
        'selling_price' => 'float',
        'status'        => 'integer',
    ];

    public function productType()
    {
        return $this->belongsTo(ProductType::class, 'id_produc_type', 'id_produc_type');
    }

    public function details()
    {
        return $this->hasMany(Detail::class, 'id_product', 'id_product');
    }
}