<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

class City extends Model
{
    use Compoships;
    protected $table = 'city';
    protected $primaryKey = ['id_departament', 'id_city'];
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_city',
        'name_city',
        'id_departament',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'id_departament', 'id_departament');
    }

    public function clients()
    {
        return $this->hasMany(Client::class, ['id_departament', 'id_city'], ['id_departament', 'id_city']);
    }
}