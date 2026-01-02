<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'test_request_id',
        'last_opened_at',
    ];

    protected $casts = [
        'last_opened_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class, 'test_request_id');
    }
}
