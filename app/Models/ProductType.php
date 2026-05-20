<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    protected $table = 'product_type';
    protected $primaryKey = 'id_produc_type';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_produc_type',
        'type',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'id_produc_type', 'id_produc_type');
    }
}