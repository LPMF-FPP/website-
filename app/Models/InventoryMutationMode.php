<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMutationMode extends Model
{
    public const SINGLETON_ID = 1;

    public const DISABLED = 'disabled';

    public const OPENING_BASELINE = 'opening_baseline';

    public const ACTIVE = 'active';

    protected $table = 'inventory_mutation_modes';

    protected $fillable = [
        'mode',
    ];
}
