<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Kegiatan Bulanan WPiTV</title>
    <link rel="stylesheet" href="{{ asset('css/monthly-report-print.css') }}">
</head>
<body>
@php($rules = \App\Support\MonthlyActivityRules::class)
<main>
    <nav><a href="{{ route('admin.monthly-reports.show', $report->id) }}">Kembali ke laporan</a><p>Untuk menyimpan PDF, buka menu Cetak di browser dan pilih Simpan sebagai PDF.</p></nav>
    <header>
        <h1>Laporan Kegiatan Bulanan WPiTV</h1>
        <p>{{ $rules::DIVISION_LABELS[$report->division] }} · {{ $report->period_start->locale('id')->translatedFormat('F Y') }}</p>
        <p>Disetujui · Versi kiriman {{ $version->revision_number }} · {{ $approval->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
    </header>
    @if ($snapshot['nil_reason'])
        <p><strong>Alasan nihil</strong> · {{ $snapshot['nil_reason'] }}</p>
    @endif
    @foreach ($snapshot['items'] as $item)
        <article>
            <h2>{{ $item['title'] }}</h2>
            <p>{{ $item['activity_date'] }} WIB</p>
            <dl>
                @foreach (array_diff_key($rules::ITEM_FIELDS, ['title' => true]) as $field => $label)
                    <dt>{{ $label }}</dt>
                    <dd>{{ $item[$field] }}</dd>
                @endforeach
            </dl>
        </article>
    @endforeach
    @if ($snapshot['attachments'])
        <section>
            <h2>Dokumentasi Versi Kiriman</h2>
            <ul>
                @foreach ($snapshot['attachments'] as $entry)
                    <li>
                        <a href="{{ route('admin.monthly-reports.attachments.historical', [$report->id, $version->revision_number, $entry['id']]) }}">{{ $entry['display_name'] }}</a>
                        @if ($entry['item_id']) · {{ $itemTitles[$entry['item_id']] ?? 'Kegiatan' }} @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</main>
</body>
</html>
