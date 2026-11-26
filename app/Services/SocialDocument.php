<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

final class SocialDocument
{
    public readonly string $month;
    public readonly array $identity;

    public function __construct(public readonly array $snapshot)
    {
        $this->month = CarbonImmutable::createFromFormat('!Y-m', $snapshot['period'], 'Asia/Jakarta')->locale('id')->translatedFormat('F Y');
        $this->identity = ($snapshot['identity'] ?? []) + config('social-media.document');
    }

    public function analysis(string $key): string
    {
        $text = $this->snapshot['analysis'][$key] ?? '';
        $reference = $this->snapshot['references'][$key] ?? null;
        return $reference ? trim($text."\n".SocialReport::PLATFORMS[$reference['platform']].' · '.$reference['title']) : $text;
    }

    public function workbook(): Spreadsheet
    {
        $book = (new Xls)->load(base_path('docs/templates/LAPORAN AKTIVITAS SOSIAL MEDIA.xls'));
        $book->removeSheetByIndex(1);
        $sheet = $book->setActiveSheetIndex(0);
        $sheet->setTitle($this->month);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:G36');
        $s = $this->snapshot;
        $texts = ['C1'=>$this->identity['title'], 'C2'=>$this->identity['organization'], 'C3'=>'BULAN : '.mb_strtoupper($this->month),
            'B30'=>'Mengetahui', 'D30'=>$this->identity['city'].', '.$this->month,
            'B31'=>$s['approved_by']['position'] ?? ($this->identity['approver_position'] ?? 'Kepala UPT Studio TV'),
            'D31'=>$s['author']['position'] ?? ($this->identity['author_position'] ?? 'News Division'),
            'B36'=>$s['approved_by']['name'] ?? '—', 'D36'=>$s['author']['name'] ?? '—'];
        foreach (array_keys(SocialReport::ANALYSIS) as $i=>$key) {
            $row = 23+$i;
            $value = $this->analysis($key);
            $texts['C'.$row] = $value;
            $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
            $lines = array_sum(array_map(fn($line)=>max(1,ceil(mb_strlen($line)/65)),explode("\n",$value)));
            $sheet->getRowDimension($row)->setRowHeight(min(409,max(34,$lines*16)));
        }
        foreach ($texts as $cell=>$text) {
            $sheet->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);
        }
        foreach (array_keys(SocialReport::PLATFORMS) as $i=>$platform) {
            $r = 8+$i;
            foreach (['B'=>'start','C'=>'end','E'=>'reach','F'=>'impressions','G'=>'er'] as $col=>$key) {
                $value = $s['metrics'][$platform][$key] ?? null;
                $sheet->setCellValue($col.$r,is_numeric($value) ? $value/($key==='er'?100:1) : null);
            }
            foreach (array_keys(SocialReport::TYPES) as $j=>$key) {
                $sheet->setCellValue(chr(66+$j).(16+$i),$s['counts'][$platform][$key]);
            }
        }
        foreach (range(8,12) as $r) {
            $sheet->setCellValue('D'.$r, sprintf('=IF(COUNT(B%1$d:C%1$d)<2,"",IF(B%1$d>0,(C%1$d-B%1$d)/B%1$d,0))',$r));
        }
        foreach (['B','C','E','F','G'] as $col) {
            $range = "{$col}8:{$col}11";
            $aggregate = $col==='G'?'AVERAGE':'SUM';
            $sheet->setCellValue($col.'12', '=IF(COUNT('.$range.')=0,"",'.$aggregate.'('.$range.'))');
        }
        return $book;
    }
}
