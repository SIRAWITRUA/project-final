<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpVehicleType extends Model
{
    protected $connection = 'oracle';
    protected $table = 'mp_vehicle_types';
    protected $primaryKey = 'vehicle_type_id';
    public $timestamps = true;

    protected $fillable = ['name'];

    public function vehicles()
    {
        return $this->hasMany(MpVehicle::class, 'vehicle_type_id', 'vehicle_type_id');
    }
}
