<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestVisitItem extends Model
{
    protected $fillable = [
        'guest_visit_id',
        'test_request_id',
        'investigator_id',
        'activity_type',
    ];

    public function guestVisit(): BelongsTo
    {
        return $this->belongsTo(GuestVisit::class);
    }

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }
}
