<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'attachment_type',
        'attachment_url',
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
        return filled($this->attachment_path) || filled($this->attachment_url);
    }

    public function isExternalUrl(): bool
    {
        return $this->attachment_type === 'url' && filled($this->attachment_url);
    }

    public function attachmentUrl(): ?string
    {
        if ($this->isExternalUrl()) {
            return $this->attachment_url;
        }

        if (! $this->attachment_path) {
            return null;
        }

        return asset('storage/' . ltrim($this->attachment_path, '/'));
    }

    public function attachmentDisplayLabel(): ?string
    {
        if ($this->isExternalUrl()) {
            $host = parse_url((string) $this->attachment_url, PHP_URL_HOST);

            return $host ? 'رابط خارجي — ' . $host : 'رابط خارجي';
        }

        return $this->attachment_original_name ?: 'مرفق';
    }
}
