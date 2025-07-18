<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IcdEntity extends Model
{
    protected $fillable = [
        'who_id',
        'parent_who_id',
        'code',
        'title',
        'definition',
        'release_id',
        'release_date',
        'raw_json',
    ];

    protected $casts = [
        'release_date' => 'date',
        'raw_json' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_who_id', 'who_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_who_id', 'who_id');
    }
}
