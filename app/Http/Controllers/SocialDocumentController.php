<?php

namespace App\Http\Controllers;

use App\Services\SocialDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SocialDocumentController
{
    public function draft(string $period)
    {
        Gate::authorize('social.view');
        $row = DB::table('tvclub_social_reports')->where('period_start',$period.'-01')->firstOrFail();
        if (in_array($row->status,['submitted','approved'],true)) {
            return redirect()->route('admin.social.preview',$row->version_id);
        }
        $draft = json_decode($row->draft,true,512,JSON_THROW_ON_ERROR);
        abort_unless(isset($draft['counts']),409,'Simpan kembali draft untuk menyiapkan pratinjau.');
        return $this->page(new SocialDocument($draft),null,false);
    }

    public function version(int $version, string $format = 'preview')
    {
        Gate::authorize('social.view');
        $row = DB::table('tvclub_social_report_versions')->where('id',$version)->firstOrFail();
        $s = json_decode($row->snapshot,true,512,JSON_THROW_ON_ERROR);
        abort_unless(isset($s['period'],$s['counts'],$s['author']['name']) && $s['period'].'-01' === $row->period_start,409,'Format versi laporan tidak didukung.');
        $approved = ($s['status'] ?? '') === 'approved' && !empty($s['approved_by']['id']) && !empty($s['approved_by']['name']);
        abort_if($format !== 'preview' && !$approved,409,'Dokumen resmi tersedia setelah laporan disetujui.');
        $doc = new SocialDocument($s);
        if ($format !== 'xlsx') {
            return $this->page($doc,$version,$approved,$format==='print');
        }
        $book = $doc->workbook();
        return response()->streamDownload(function () use ($book) {
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, 'Laporan Aktivitas Media Sosial - '.$doc->month.'.xlsx',[
            'Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'private, no-store',
        ]);
    }

    private function page(SocialDocument $doc, ?int $version, bool $approved, bool $print = false)
    {
        return response()->view('admin.social-document',compact('doc','version','approved','print'))->header('Cache-Control','private, no-store');
    }
}
