<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * @var list<string>
     */
    protected $appends = ['city_name'];

    public function getCityNameAttribute(): string
    {
        return (string) $this->name_city;
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'id_departament', 'id_departament');
    }

    public function clients()
    {
        return $this->hasMany(Client::class, ['id_departament', 'id_city'], ['id_departament', 'id_city']);
    }
}
