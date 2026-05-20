<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'department';
    protected $primaryKey = 'id_departament';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_departament',
        'name_departament',
    ];

    public function cities()
    {
        return $this->hasMany(City::class, 'id_departament', 'id_departament');
    }
}