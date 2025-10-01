<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpPlace extends Model
{
    protected $connection = 'oracle';
    protected $table = 'mp_places';
    protected $primaryKey = 'place_id';
    public $timestamps = false;

    protected $fillable = ['name'];

    public function routePlaces()
    {
        return $this->hasMany(MpRoutePlace::class, 'place_id', 'place_id');
    }
}
