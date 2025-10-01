<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpVehicle extends Model
{
    protected $connection = 'oracle';
    protected $table = 'mp_vehicles';
    protected $primaryKey = 'vehicle_id';
    public $timestamps = true;

    protected $fillable = ['vehicle_type_id', 'license_plate', 'description', 'status', 'capacity'];

    public function type()
    {
        return $this->belongsTo(MpVehicleType::class, 'vehicle_type_id', 'vehicle_type_id');
    }
}
