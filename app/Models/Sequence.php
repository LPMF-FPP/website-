<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use HasFactory;

    protected $table = 'number_sequences';

    protected $fillable = [
        'scope',
        'bucket',
        'current_value',
        'reset_period',
    ];
}
