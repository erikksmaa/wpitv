<?php

namespace App\Http\Controllers;

use App\Exceptions\MonthlyActivityException;
use App\Models\MonthlyActivityAttachment;
use App\Models\MonthlyActivityReport;
use App\Services\MonthlyActivityFiles;
use App\Services\MonthlyActivityLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyActivityAttachmentController
{
    public function store(Request $request, MonthlyActivityReport $report, MonthlyActivityLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('update', $report);
        $input = $request->validate([
            'attachment' => ['required', 'file', 'max:10240'],
            'version' => ['required', 'integer', 'between:1,4294967295'],
            'item_id' => ['nullable', 'integer', 'min:1'],
        ], ['attachment.max' => 'Ukuran file maksimal 10 MB.', 'attachment.required' => 'Pilih file untuk diunggah.', 'attachment.uploaded' => 'Unggahan gagal. Periksa ukuran file dan coba kembali.']);

        $target = redirect()->route('admin.monthly-reports.show', $report->id);
        try {
            $lifecycle->uploadAttachment($request->user(), $report, (int) $input['version'], isset($input['item_id']) ? (int) $input['item_id'] : null, $request->file('attachment'));
        } catch (MonthlyActivityException $exception) {
            return $target->withErrors(['attachment' => $exception->getMessage()]);
        }

        return $target->with('success', 'Lampiran disimpan.');
    }

    public function download(MonthlyActivityReport $report, int $attachment, MonthlyActivityFiles $files): StreamedResponse
    {
        Gate::authorize('view', $report);
        $file = $report->attachments()->attached()->findOrFail($attachment);

        return $this->stream($file, $files);
    }

    public function historical(MonthlyActivityReport $report, int $revision, int $attachment, MonthlyActivityFiles $files): StreamedResponse
    {
        Gate::authorize('view', $report);
        $version = $report->versions()->where('revision_number', $revision)->firstOrFail();
        $entry = collect($version->snapshot['attachments'] ?? [])->first(fn ($entry) => $entry['id'] === $attachment);
        abort_unless($entry, 404);
        $file = $report->attachments()->findOrFail($attachment);
        abort_unless(hash_equals($entry['sha256'], $file->sha256), 410, 'File lampiran tidak tersedia.');

        return $this->stream($file, $files);
    }

    private function stream(MonthlyActivityAttachment $file, MonthlyActivityFiles $files): StreamedResponse
    {
        $stream = $files->open($file);
        abort_unless(is_resource($stream), 410, 'File lampiran tidak tersedia.');

        return response()->streamDownload(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $files->downloadName($file), [
            'Content-Type' => $file->mime_type,
            'Content-Length' => (string) $file->size_bytes,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }
}
