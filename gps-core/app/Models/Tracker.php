<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tracker extends Model
{
    protected $table = 'tracker';
    protected $primaryKey = 'imei';
    public $timestamps = false;
}
