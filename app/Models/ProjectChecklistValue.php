<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectChecklistValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'checklist_item_id',
        'coordinator_value',
        'monitor_value',
        'person_name',
        'attachment_path',
        'attachment_original_name',
        'attachment_uploaded_at',
    ];

    protected $casts = [
        'attachment_uploaded_at' => 'datetime',
    ];

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function attachmentUrl(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }
}
