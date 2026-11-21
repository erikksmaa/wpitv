<?php

namespace App\Http\Controllers;

use App\Models\MonthlyActivityReport;
use App\Support\CsvCell;
use App\Support\MonthlyActivityRules as Rules;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyActivityDocumentController
{
    public function csv(Request $request, MonthlyActivityReport $report): StreamedResponse
    {
        [$snapshot, $approval, $version] = $this->approved($report);
        $period = $report->period_start->format('Y-m');
        $metadata = [Rules::DIVISION_LABELS[$report->division], $period, Rules::STATUS_LABELS['approved'], $version->revision_number, $approval->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s').' WIB'];
        $fields = array_keys(Rules::ITEM_FIELDS);
        $rows = [];
        foreach ($snapshot['items'] as $item) {
            $rows[] = [...$metadata, $item['activity_date'], ...array_map(fn ($field) => $item[$field], $fields), ''];
        }
        if ($rows === []) {
            $rows[] = [...$metadata, ...array_fill(0, count($fields) + 1, ''), $snapshot['nil_reason'] ?? ''];
        }

        $actor = $request->user();
        $saved = $report->events()->make()->forceFill([
            'report_version_id' => $version->id, 'action' => 'updated', 'from_status' => 'approved', 'to_status' => 'approved',
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'created_at' => $report->freshTimestamp(),
            'changes' => ['operation' => 'report_exported', 'format' => 'csv', 'row_count' => count($rows)],
        ])->save();
        abort_unless($saved, 500, 'Ekspor laporan gagal.');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'wb');
            try {
                fwrite($stream, "\xEF\xBB\xBF");
                $header = ['Divisi', 'Periode', 'Status', 'Versi kiriman', 'Waktu persetujuan', 'Tanggal pelaksanaan', ...array_values(Rules::ITEM_FIELDS), 'Alasan nihil'];
                foreach ([$header, ...$rows] as $row) {
                    fputcsv($stream, array_map(CsvCell::sanitize(...), $row), ',', '"', '', "\r\n");
                }
            } finally {
                fclose($stream);
            }
        }, 'laporan-'.$report->division.'-'.$period.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function print(MonthlyActivityReport $report): Response
    {
        [$snapshot, $approval, $version] = $this->approved($report);

        $itemTitles = array_column($snapshot['items'], 'title', 'id');

        return response()->view('admin.monthly-report-print', compact('report', 'snapshot', 'approval', 'version', 'itemTitles'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function approved(MonthlyActivityReport $report): array
    {
        Gate::authorize('view', $report);
        abort_unless($report->status === 'approved', 409, 'Dokumen resmi tersedia setelah laporan disetujui.');
        $approval = $report->events()->where('action', 'approved')->with('reportVersion')->latest('id')->firstOrFail();
        $version = $approval->reportVersion;
        $snapshot = $version?->snapshot;
        abort_unless(is_array($snapshot['items'] ?? null) && count($snapshot['items']) <= Rules::ITEM_LIMIT
            && is_array($snapshot['attachments'] ?? null) && count($snapshot['attachments']) <= Rules::ATTACHMENT_LIMIT
            && hash_equals($version->snapshot_hash, Rules::snapshotHash($snapshot)), 409, 'Versi laporan resmi tidak tersedia.');

        return [$snapshot, $approval, $version];
    }
}
