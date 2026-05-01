<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Customer.php

class Customer extends Model
{
    protected $table = 'customer'; // 👈 สำคัญ (ไม่ใช่ customers)

    protected $primaryKey = 'customer_id'; // 👈 สำคัญ

    public $timestamps = false; // 👈 เพราะใช้ created_date / changed_date

    protected $casts = [
        'station_show' => 'boolean',
        'poi_show' => 'boolean',
        'zone_show' => 'boolean',
        'over_speed_report' => 'boolean',

        'enable_canbus' => 'boolean',
        'enable_engine_cut' => 'boolean',
        'enable_geocoding' => 'boolean',
        'enable_attendance' => 'boolean',
        'enable_fuel_chk' => 'boolean',
        'enable_fare_cal' => 'boolean',
        'enable_batt_mont' => 'boolean',
        'enable_passenger' => 'boolean',
    ];

    public function getFeaturesAttribute()
    {
        return [
            'canbus' => $this->enable_canbus,
            'engineCut' => $this->enable_engine_cut,
            'fuel' => $this->enable_fuel_chk,
        ];
    }
}
