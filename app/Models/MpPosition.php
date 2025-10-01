<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpPosition extends Model
{
    protected $table = 'mp_positions';
    protected $primaryKey = 'position_id';
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    // Menus this position can access
    public function menus()
    {
        return $this->belongsToMany(MpMenu::class, 'mp_position_menus', 'position_id', 'menu_id');
    }
}
