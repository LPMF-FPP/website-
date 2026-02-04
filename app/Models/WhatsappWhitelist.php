<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappWhitelist extends Model
{
    protected $fillable = [
        'phone_number',
        'name',
        'added_by',
    ];
}
