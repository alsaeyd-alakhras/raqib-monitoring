<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['center_id', 'name'];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
