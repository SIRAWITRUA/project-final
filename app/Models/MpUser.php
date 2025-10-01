<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class MpUser extends Authenticatable
{
    protected $table = 'mp_users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'gender',
    ];

    protected $hidden = [
        'password_hash',
    ];


    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}
