@props(['status'])
@php
[$label,$color,$icon]=match($status){
'unavailable'=>['Tidak Tersedia','danger','slash-circle'],'total'=>['Total unit','dark','grid'],'available'=>['Tersedia','success','check-circle'],'good'=>['Baik','success','check-circle'],
'active'=>['Aktif','primary','clock'],'borrowed'=>['Dipinjam','primary','arrow-up-right'],'lost'=>['Hilang','lost','question-circle'],
'inactive'=>['Nonaktif','secondary','pause-circle'],'minor_damage'=>['Rusak ringan','danger','tools'],'major_damage'=>['Rusak berat','danger','tools'],'damaged'=>['Rusak','danger','tools'],
'overdue'=>['Terlambat','danger','clock'],'partially_returned'=>['Kembali sebagian','warning text-dark','arrow-return-left'],'returned'=>['Selesai','success','check'],default=>['—','secondary','dash']};
@endphp
<span class="badge bg-{{ $color }}" @if($color==='lost') style="background-color:#6941a5" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ $label }}</span>
