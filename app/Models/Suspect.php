<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suspect extends Model
{
    protected $fillable = [
        'test_request_id',
        'name',
        'gender',
        'age',
        'order_no',
    ];

    protected $casts = [
        'age' => 'integer',
        'order_no' => 'integer',
    ];

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }
}
