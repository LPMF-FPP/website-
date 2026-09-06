<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuestVisit extends Model
{
    use SoftDeletes;

    protected $table = 'guest_visits';

    protected $fillable = [
        'investigator_id',
        'test_request_id',
        'visit_date',
        'visit_time',
        'purpose',
        'purpose_detail',
        'host_id',
        'visitor_name',
        'visitor_identity',
        'visitor_institution',
        'visitor_relation',
        'visitor_phone',
        'notes',
        'created_by',
    ];

    protected $guarded = ['id', 'status', 'nda_accepted', 'nda_accepted_at', 'check_out_at'];

    protected $casts = [
        'visit_date' => 'date',
        'check_out_at' => 'datetime',
        'nda_accepted' => 'boolean',
        'nda_accepted_at' => 'datetime',
    ];

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GuestVisitItem::class);
    }

    public function getRequestCountAttribute(): int
    {
        $count = $this->items_count ?? $this->items()->count();

        return $count > 0 ? $count : ($this->test_request_id ? 1 : 0);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->visitor_name ?? $this->investigator?->name ?? 'Tidak diketahui';
    }

    public function isVisitorVerified(): bool
    {
        return filled($this->visitor_name);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCheckedOut(): bool
    {
        return $this->status === 'checked_out';
    }
}
