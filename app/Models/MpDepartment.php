<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpDepartment extends Model
{
    protected $table = 'mp_departments';
    protected $primaryKey = 'dept_id';
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}
