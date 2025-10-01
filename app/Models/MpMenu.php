<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpMenu extends Model
{
    protected $table = 'mp_menus';
    protected $primaryKey = 'menu_id';
    public $timestamps = false; // created_at exists, no updated_at

    protected $fillable = [
        'key_name',
        'name',
    ];

    // Positions that can access this menu
    public function positions()
    {
        return $this->belongsToMany(MpPosition::class, 'mp_position_menus', 'menu_id', 'position_id');
    }
}
