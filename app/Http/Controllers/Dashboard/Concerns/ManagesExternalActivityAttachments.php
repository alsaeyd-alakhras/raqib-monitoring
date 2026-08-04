<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Models\MonitoringActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesExternalActivityAttachments
{
    private function mergeActivityAttachments(Request $request, MonitoringActivity $activity): void
    {
        $attachments = $activity->attachmentsList();
        $changed = false;

        $pendingUrls = $request->input('activity_attachment_urls', []);
        if (is_array($pendingUrls)) {
            foreach ($pendingUrls as $url) {
                $url = trim((string) $url);
                if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $host = parse_url($url, PHP_URL_HOST) ?: 'رابط خارجي';
                $attachments[] = [
                    'id' => Str::uuid()->toString(),
                    'type' => 'url',
                    'path' => null,
                    'url' => $url,
                    'original_name' => 'رابط خارجي — ' . $host,
                    'uploaded_at' => now()->toIso8601String(),
                ];
                $changed = true;
            }
        }

        /** @var list<UploadedFile> $files */
        $files = $request->file('activity_attachments', []);
        if (! is_array($files)) {
            $files = $files instanceof UploadedFile ? [$files] : [];
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $directory = $activity->attachmentsStorageDirectory();
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $filename = Str::uuid()->toString() . '.' . $extension;

            Storage::disk('public')->putFileAs($directory, $file, $filename);

            $attachments[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'file',
                'path' => $directory . '/' . $filename,
                'url' => null,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_at' => now()->toIso8601String(),
            ];
            $changed = true;
        }

        if ($changed) {
            $activity->syncAttachments($attachments);
            $activity->save();
        }
    }

    public function deleteExternalActivityAttachment(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorizeExternalEdit($monitoring_activity);

        $validated = $request->validate([
            'attachment_id' => ['required', 'string', 'max:64'],
        ]);

        $attachmentId = (string) $validated['attachment_id'];
        $remaining = [];

        foreach ($monitoring_activity->attachmentsList() as $row) {
            $rowId = (string) ($row['id'] ?? '');

            if ($rowId === $attachmentId) {
                if (($row['type'] ?? '') === 'file' && ! empty($row['path'])) {
                    Storage::disk('public')->delete($row['path']);
                }

                continue;
            }

            $remaining[] = $row;
        }

        $monitoring_activity->syncAttachments($remaining);
        $monitoring_activity->updated_by = auth()->id();
        $monitoring_activity->save();

        return back()->with('success', 'تم حذف المرفق.');
    }
}
