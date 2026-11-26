@use('App\Services\SocialReport','Social')
@php($s=$doc->snapshot)
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $doc->identity['title'] }} · {{ $doc->month }}</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#162235;font:15px/1.45 system-ui,sans-serif}
main,nav{max-width:1120px;margin:1rem auto;padding:1rem 2rem}main{background:white;border:1px solid #ccd4dd;border-radius:8px}
nav{display:flex;justify-content:flex-end;gap:1rem;padding-block:0}nav a{color:#174989}
header{display:flex;align-items:center;gap:1.3rem;border-bottom:3px solid #253f65;padding:1rem 0}header img{width:76px;height:auto}
h1{font-size:1.55rem;line-height:1.2;margin:.25rem 0}header p{margin:0}.draft{font-weight:bold;color:#893b09}
h2{font-size:1rem;color:#193754;margin:1.4rem 0 .5rem}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;table-layout:fixed;font-variant-numeric:tabular-nums}
th,td{border:1px solid #a8b5c1;padding:.45rem;text-align:right;overflow-wrap:anywhere}th:first-child{text-align:left}thead th{background:#eaf0f5;text-align:center}tbody tr:last-child{font-weight:bold;background:#f1f5f8}
dl{margin:0;border:1px solid #bac5cf}dt{font-weight:bold;padding:.45rem .7rem;background:#eff3f6;break-after:avoid}dd{margin:0;padding:.55rem .7rem;white-space:pre-wrap;overflow-wrap:anywhere}
footer{display:grid;grid-template-columns:1fr 1fr;gap:2rem;text-align:center;padding:1.5rem 0;break-inside:avoid}footer strong{display:block;margin-top:2.3rem;overflow-wrap:anywhere}
@media screen and (max-width:650px){main,nav{margin:.5rem;padding:.75rem}table{min-width:640px}header img{width:48px}h1{font-size:1.2rem}}
@media (prefers-color-scheme:dark){@media screen{body{background:#111925;color:#e8eef5}main{background:#1b2737;border-color:#405066}h2,nav a{color:#a8c9f3}thead th,dt,tbody tr:last-child{background:#29394c}.draft{color:#ffc780}}}
@page{size:A4 landscape;margin:12mm}@media print{body{background:white;color:#111;font:10pt/1.28 Arial,sans-serif}main{max-width:none;margin:0;padding:0;border:0}nav{display:none}header{padding:0 0 6pt}header img{width:50px}h1{font-size:15pt}h2{margin:9pt 0 4pt;break-after:avoid}.scroll{overflow:visible}table{min-width:0}thead{display:table-header-group}tr{break-inside:avoid}th,td{padding:3pt}dl{display:block}dd{orphans:3;widows:3}footer{padding:10pt 0}*{print-color-adjust:exact}}
</style></head><body>
@if($approved)<nav aria-label="Dokumen resmi"><a href="{{ route('admin.social.preview',[$version,'xlsx']) }}">Unduh Excel</a><a href="{{ route('admin.social.preview',[$version,'print']) }}" onclick="window.print();return false">Cetak / Simpan PDF</a></nav>@endif
<main><header><img src="{{ asset('logo-wpi.png') }}" alt=""><div><p>{{ $doc->identity['organization'] }}</p><h1>{{ $doc->identity['title'] }}</h1><p>{{ $doc->month }} @unless($approved)<span class="draft">· Draft</span>@endunless</p></div></header>
<h2>1. Ringkasan Metrik Utama per Platform</h2>
<div class="scroll"><table><thead><tr><th>Platform</th><th>Followers awal</th><th>Followers akhir</th><th>Pertumbuhan (%)</th><th>Total reach</th><th>Total impressions</th><th>Rata-rata engagement rate (%)</th></tr></thead><tbody>
@foreach(Social::PLATFORMS+['total'=>'Total / rata-rata'] as $p=>$label)
<tr><th scope="row">{{ $label }}</th>
@foreach(['start','end','growth','reach','impressions','er'] as $key)
@php($v=$key==='growth'?($s['growth'][$p] ?? null):($p==='total'?($s['totals'][$key] ?? null):($s['metrics'][$p][$key] ?? null)))
<td>{{ !is_numeric($v)?'—':(in_array($key,['er','growth'])?number_format($v,2,',','.').' %':number_format($v,0,',','.')) }}</td>
@endforeach</tr>@endforeach</tbody></table></div>
<h2>2. Rekapitulasi Aktivitas &amp; Jumlah Konten</h2>
<div class="scroll"><table><thead><tr><th>Platform</th>@foreach(Social::TYPES as $label)<th>{{ $label }}</th>@endforeach<th>Total konten</th></tr></thead><tbody>
@foreach(Social::PLATFORMS+['total'=>'Total konten'] as $p=>$label)<tr><th scope="row">{{ $label }}</th>@foreach(array_keys(Social::TYPES+['total'=>'Total']) as $key)<td>{{ $s['counts'][$p][$key] }}</td>@endforeach</tr>@endforeach
</tbody></table></div>
<h2>3. Analisis, Evaluasi, dan Rencana Strategi Berikutnya</h2>
<dl>@foreach(Social::ANALYSIS as $key=>$label)<dt>{{ $label }}</dt><dd>{{ $doc->analysis($key) ?: '—' }}</dd>@endforeach</dl>
<footer><div>Mengetahui<br>{{ $s['approved_by']['position'] ?? ($doc->identity['approver_position'] ?? 'Kepala UPT Studio TV') }}<strong>{{ $s['approved_by']['name'] ?? 'Belum disetujui' }}</strong></div><div>{{ $doc->identity['city'] }}, {{ $doc->month }}<br>{{ $s['author']['position'] ?? ($doc->identity['author_position'] ?? 'News Division') }}<strong>{{ $s['author']['name'] ?? '—' }}</strong></div></footer>
</main>@if($print)<script>window.addEventListener('load',()=>window.print())</script>@endif
</body></html>
