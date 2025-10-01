<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpRoutePlace extends Model
{
    protected $connection = 'oracle';
    protected $table = 'mp_route_places';
    protected $primaryKey = 'route_place_id';
    public $timestamps = false;

    protected $fillable = ['route_id', 'place_id', 'sequence_no', 'duration_min'];

    public function route()
    {
        return $this->belongsTo(MpRoute::class, 'route_id', 'route_id');
    }

    public function place()
    {
        return $this->belongsTo(MpPlace::class, 'place_id', 'place_id');
    }
}
