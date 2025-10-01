<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpRoute extends Model
{
    protected $connection = 'oracle';
    protected $table = 'mp_routes';
    protected $primaryKey = 'route_id';
    public $timestamps = false;

    protected $fillable = ['name'];

    public function routePlaces()
    {
        return $this->hasMany(MpRoutePlace::class, 'route_id', 'route_id');
    }
}
