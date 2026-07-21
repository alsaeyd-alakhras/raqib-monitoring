<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'attachments',
    ];

    protected $casts = [
        'attachment_uploaded_at' => 'datetime',
        'attachments' => 'array',
    ];

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return list<array{id: string, type: string, path: ?string, url: ?string, original_name: ?string, uploaded_at: ?string}> */
    public function attachmentsList(): array
    {
        $stored = is_array($this->attachments) ? $this->attachments : [];

        if ($stored !== []) {
            return array_values(array_map(fn (array $row) => $this->normalizeAttachmentRow($row), $stored));
        }

        if ($this->attachment_path || $this->attachment_url) {
            return [$this->normalizeAttachmentRow([
                'id' => 'legacy',
                'type' => $this->attachment_type ?? ($this->attachment_url ? 'url' : 'file'),
                'path' => $this->attachment_path,
                'url' => $this->attachment_url,
                'original_name' => $this->attachment_original_name,
                'uploaded_at' => $this->attachment_uploaded_at?->toIso8601String(),
            ])];
        }

        return [];
    }

    public function hasAttachment(): bool
    {
        return $this->attachmentsList() !== [];
    }

    public function isExternalUrl(): bool
    {
        $list = $this->attachmentsList();

        return ($list[0]['type'] ?? '') === 'url' && count($list) === 1;
    }

    public function attachmentUrl(): ?string
    {
        $first = $this->attachmentsList()[0] ?? null;

        if (! $first) {
            return null;
        }

        if (($first['type'] ?? '') === 'url') {
            return $first['url'] ?? null;
        }

        $path = $first['path'] ?? null;

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public function attachmentDisplayLabel(): ?string
    {
        $list = $this->attachmentsList();

        if ($list === []) {
            return null;
        }

        if (count($list) === 1) {
            $row = $list[0];

            if (($row['type'] ?? '') === 'url') {
                $host = parse_url((string) ($row['url'] ?? ''), PHP_URL_HOST);

                return $host ? 'رابط خارجي — ' . $host : 'رابط خارجي';
            }

            return $row['original_name'] ?: 'مرفق';
        }

        return count($list) . ' مرفقات';
    }

    /** @param  array<string, mixed>  $row */
    public function attachmentRowUrl(array $row): ?string
    {
        if (($row['type'] ?? '') === 'url') {
            return $row['url'] ?? null;
        }

        $path = $row['path'] ?? null;

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    /** @param  array<string, mixed>  $row */
    public function attachmentRowLabel(array $row): string
    {
        if (($row['type'] ?? '') === 'url') {
            $host = parse_url((string) ($row['url'] ?? ''), PHP_URL_HOST);

            return $host ? 'رابط — ' . $host : 'رابط خارجي';
        }

        return (string) ($row['original_name'] ?? 'مرفق');
    }

    /** @param  array<string, mixed>  $row */
    private function normalizeAttachmentRow(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? Str::uuid()->toString()),
            'type' => (string) ($row['type'] ?? 'file'),
            'path' => $row['path'] ?? null,
            'url' => $row['url'] ?? null,
            'original_name' => $row['original_name'] ?? null,
            'uploaded_at' => $row['uploaded_at'] ?? null,
        ];
    }

    /** @param  list<array<string, mixed>>  $attachments */
    public function syncAttachments(array $attachments): void
    {
        $normalized = array_values(array_map(fn (array $row) => $this->normalizeAttachmentRow($row), $attachments));

        $this->attachments = $normalized;
        $this->syncLegacyAttachmentColumnsFromList($normalized);
    }

    /** @param  list<array<string, mixed>>  $attachments */
    private function syncLegacyAttachmentColumnsFromList(array $attachments): void
    {
        $first = $attachments[0] ?? null;

        if (! $first) {
            $this->attachment_path = null;
            $this->attachment_original_name = null;
            $this->attachment_uploaded_at = null;
            $this->attachment_type = 'file';
            $this->attachment_url = null;

            return;
        }

        if (($first['type'] ?? '') === 'url') {
            $this->attachment_path = null;
            $this->attachment_url = $first['url'] ?? null;
            $this->attachment_type = 'url';
            $this->attachment_original_name = $first['original_name'] ?? null;
            $this->attachment_uploaded_at = filled($first['uploaded_at'] ?? null)
                ? \Carbon\Carbon::parse($first['uploaded_at'])
                : now();

            return;
        }

        $this->attachment_path = $first['path'] ?? null;
        $this->attachment_url = null;
        $this->attachment_type = 'file';
        $this->attachment_original_name = $first['original_name'] ?? null;
        $this->attachment_uploaded_at = filled($first['uploaded_at'] ?? null)
            ? \Carbon\Carbon::parse($first['uploaded_at'])
            : now();
    }
}
