<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SocialReport
{
    public const PLATFORMS = ['instagram'=>'Instagram','tiktok'=>'TikTok','facebook'=>'Facebook','youtube'=>'YouTube'];
    public const TYPES = ['photo'=>'Feed / Foto','carousel'=>'Carousel','short_video'=>'Reels / Video Pendek','story'=>'Stories','other'=>'Live / Lainnya'];
    public const METRICS = ['start'=>'Followers awal','end'=>'Followers akhir','reach'=>'Total reach','impressions'=>'Total impressions','er'=>'Engagement rate (%)'];
    public const ANALYSIS = [
        'best' => 'Top Performing Content (Konten Terbaik)',
        'lowest' => 'Underperforming Content (Konten Terendah)',
        'insight' => 'Insight / Temuan Utama',
        'evaluation' => 'Kendala & Evaluasi Operasional',
        'strategy' => 'Rencana Strategi Bulan Berikutnya',
    ];
    public const STATES = ['draft'=>'Draf','submitted'=>'Diajukan','revision'=>'Revisi','approved'=>'Disetujui'];

    public static function category(string $type): string
    {
        return match ($type) {
            'reel','short_video' => 'short_video',
            'photo','carousel','story' => $type,
            default => 'other',
        };
    }

    public static function publications(string $month)
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
        return DB::table('tvclub_social_posts as p')->join('tvclub_social_accounts as a','a.id','=','p.account_id')
            ->whereNull('p.archived_at')->where('published_at','>=',$start->utc())
            ->where('published_at','<',$start->addMonth()->utc())
            ->orderBy('published_at')->orderBy('p.id')->get(['p.id','p.title','p.content_type','a.platform']);
    }

    public static function summary(array $metrics, iterable $posts): array
    {
        $totals = [];
        foreach (array_keys(self::METRICS) as $key) {
            $values = [];
            foreach (array_keys(self::PLATFORMS) as $platform) {
                $value = $metrics[$platform][$key] ?? null;
                if (is_numeric($value)) { $values[] = $value; }
            }
            $totals[$key] = !$values ? null : ($key === 'er' ? array_sum($values)/count($values) : array_sum($values));
        }
        $counts = array_fill_keys(array_keys(self::PLATFORMS), array_fill_keys(array_keys(self::TYPES), 0));
        foreach ($posts as $post) { $counts[$post->platform][self::category($post->content_type)]++; }
        $columnTotals = [];
        foreach (array_keys(self::TYPES) as $key) {
            $columnTotals[$key] = array_sum(array_column($counts, $key));
        }
        $counts['total'] = $columnTotals;
        foreach ($counts as &$row) { $row['total'] = array_sum($row); }
        unset($row);
        $growth = [];
        foreach ([...$metrics,'total'=>$totals] as $platform=>$row) {
            $a = $row['start'] ?? null;
            $b = $row['end'] ?? null;
            $growth[$platform] = !is_numeric($a) || !is_numeric($b) ? null : ($a > 0 ? 100*($b-$a)/$a : 0);
        }
        return compact('counts','totals','growth');
    }

    public static function event(string $type,int $id,string $action,array $details): void
    {
        DB::table('tvclub_social_events')->insert(['target_type'=>$type,'target_id'=>$id,'action'=>$action,'actor_id'=>auth()->id(),
            'changes'=>json_encode(['actor'=>auth()->user()->name]+$details,JSON_THROW_ON_ERROR),'created_at'=>now('UTC')]);
    }
}
