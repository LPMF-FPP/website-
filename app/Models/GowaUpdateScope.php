<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GowaUpdateScope extends Model
{
    protected $table = 'gowa_update_scopes';

    public $incrementing = false;

    protected $primaryKey = 'scope';

    protected $keyType = 'string';

    protected $fillable = ['scope', 'current_fence', 'active_operation_id', 'intervention_generation'];

    protected $casts = ['current_fence' => 'integer'];

    public function operations(): HasMany
    {
        return $this->hasMany(GowaUpdateOperation::class, 'scope', 'scope');
    }
}
