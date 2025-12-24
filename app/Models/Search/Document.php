<?php

namespace App\Models\Search;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';

    protected $fillable = [
        'doc_type',
        'ba_no',
        'title',
        'lp_no',
        'doc_date',
        'file_path',
    ];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'doc_date' => 'date',
    ];
}
