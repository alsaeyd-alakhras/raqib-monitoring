<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'name',
        'has_person_field',
        'has_file_field',
        'order',
        'is_active',
    ];

    protected $casts = [
        'has_person_field' => 'boolean',
        'has_file_field' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class, 'group_id');
    }
}
