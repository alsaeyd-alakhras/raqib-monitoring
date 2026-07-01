<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Section extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'name'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
