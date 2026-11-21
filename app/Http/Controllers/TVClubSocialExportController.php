<?php

namespace App\Http\Controllers;

use App\Models\TVClubSocialEvent;
use App\Models\TVClubSocialReportVersion;
use App\Services\EquipmentReports;
use App\Support\MonthlyActivityRules;
use App\Support\TVClubSocialRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TVClubSocialExportController
{
    public function __invoke(Request $request, int $version, string $type): StreamedResponse
    {
        Gate::authorize('export', TVClubSocialReportVersion::class);
        abort_unless(in_array($type, ['posts', 'followers'], true), 404);
        $report = TVClubSocialReportVersion::findOrFail($version, ['id', 'period_start', 'revision_number', 'snapshot', 'snapshot_hash']);
        $snapshot = $report->snapshot;
        $posts = $type === 'posts';
        abort_unless(is_array($snapshot['posts'] ?? null) && is_array($snapshot['accounts'] ?? null)
            && count($snapshot['posts']) <= 5000 && count($snapshot['accounts']) <= 500
            && hash_equals($report->snapshot_hash, MonthlyActivityRules::snapshotHash($snapshot)), 409, 'Snapshot laporan tidak dapat diekspor.');

        $accounts = array_column($snapshot['accounts'], null, 'id');
        $rows = $posts ? $snapshot['posts'] : $snapshot['accounts'];
        $metrics = TVClubSocialRules::POST_METRICS;
        $rowCount = count($rows);
        $coverage = array_fill_keys($metrics, 0);
        if ($posts) {
            foreach ($rows as $row) {
                foreach ($metrics as $metric) {
                    $coverage[$metric] += (int) isset($row['observation']['metrics'][$metric]);
                }
            }
        }
        $month = $report->period_start->format('Y-m');
        $revision = $report->revision_number;
        try {
            DB::transaction(function () use ($request, $report, $type, $rowCount): void {
                $audit = new TVClubSocialEvent;
                if (! $audit->forceFill([
                    'action' => 'exported', 'target_type' => 'report', 'target_id' => $report->id,
                    'actor_id' => $request->user()->id, 'created_at' => $audit->freshTimestamp(),
                    'changes' => ['type' => $type, 'row_count' => $rowCount],
                ])->save()) {
                    throw new RuntimeException;
                }
            });
        } catch (Throwable) {
            abort(500, 'Ekspor laporan gagal.');
        }

        return response()->streamDownload(function () use ($rows, $posts, $accounts, $metrics, $coverage, $rowCount, $month, $revision): void {
            $stream = fopen('php://output', 'wb');
            try {
                fwrite($stream, "\xEF\xBB\xBF");
                $header = ['periode_wib', 'revisi', 'platform', 'akun'];
                fputcsv($stream, [...$header, ...($posts
                    ? ['judul', ...$metrics, 'observasi_wib', 'cakupan_views', 'cakupan_likes', 'cakupan_comments', 'cakupan_shares']
                    : ['followers', 'observasi_wib', 'cakupan_followers'])], ',', '"', '', "\r\n");
                foreach ($rows as $row) {
                    $account = $posts ? $accounts[$row['account_id']] : $row;
                    $observation = $row['observation'];
                    $time = $observation ? CarbonImmutable::parse($observation['observed_at'])->timezone('Asia/Jakarta')->format('Y-m-d H:i:s.u') : null;
                    $values = $observation['metrics'] ?? [];
                    $cells = [$month, $revision, $account['platform'], $account['handle']];
                    $cells = $posts ? [...$cells, $row['title'], ...array_map(fn ($metric) => $values[$metric] ?? null, $metrics), $time,
                        ...array_map(fn ($metric) => $coverage[$metric].' dari '.$rowCount, $metrics)]
                        : [...$cells, $values['followers'] ?? null, $time, isset($values['followers']) ? '1 dari 1' : '0 dari 1'];
                    fputcsv($stream, array_map(EquipmentReports::csvCell(...), $cells), ',', '"', '', "\r\n");
                }
            } finally {
                fclose($stream);
            }
        }, "media-sosial-$month-r$revision-$type.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
