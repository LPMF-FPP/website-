<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertWitnessMilestone extends Model
{
    use HasFactory;

    protected $fillable = ['expert_witness_request_id', 'code', 'completed_at', 'completed_by', 'note'];

    protected $casts = ['completed_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExpertWitnessRequest::class, 'expert_witness_request_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
