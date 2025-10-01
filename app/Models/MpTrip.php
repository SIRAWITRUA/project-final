<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpTrip extends Model
{
    protected $connection = 'oracle';
    protected $table = 'mp_trips';
    protected $primaryKey = 'trip_id';
    public $timestamps = true;

    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'service_date',
        'depart_time',
        'round_no',
        'estimated_minutes',
        'capacity',
        'reserved_seats',
        'status',
        'notes',
    ];

    protected $casts = [
        'route_id' => 'integer',
        'vehicle_id' => 'integer',
        'driver_id' => 'integer',
        'estimated_minutes' => 'integer',
        'capacity' => 'integer',
        'reserved_seats' => 'integer',
        'round_no' => 'integer',
        'service_date' => 'date:Y-m-d',
    ];

    public function route()
    {
        return $this->belongsTo(MpRoute::class, 'route_id', 'route_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(MpVehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(MpEmployee::class, 'driver_id', 'employee_id');
    }
}
