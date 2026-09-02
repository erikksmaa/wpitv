@foreach(app(App\Support\AdminNavigation::class)->sections(auth()->user()) as $section)
    <li class="sidebar-title">{{ $section['title'] }}</li>
    @if($section['parent'])
        @php($sectionActive = request()->routeIs(...array_column($section['links'], 'pattern')))
        <li class="sidebar-item has-sub {{ $sectionActive ? 'active' : '' }}">
            <a href="#" class="sidebar-link"><i class="bi {{ $section['links'][0]['icon'] }}"></i><span>{{ $section['parent'] }}</span></a>
            <ul class="submenu {{ $sectionActive ? 'submenu-open' : '' }}">
                @foreach($section['links'] as $link)
                    <li class="submenu-item {{ request()->routeIs($link['pattern']) ? 'active' : '' }}">
                        <a href="{{ route($link['route']) }}" class="submenu-link">{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </li>
    @else
        @foreach($section['links'] as $link)
            <li class="sidebar-item {{ request()->routeIs($link['pattern']) ? 'active' : '' }}">
                <a href="{{ route($link['route']) }}" class="sidebar-link"><i class="bi {{ $link['icon'] }}"></i><span>{{ $link['label'] }}</span></a>
            </li>
        @endforeach
    @endif
@endforeach
@can('view-visitor-statistics')
    <li class="sidebar-title">Analitik</li>
    <li class="sidebar-item {{ request()->routeIs('admin.visitor-statistics.*') ? 'active' : '' }}">
        <a href="{{ route('admin.visitor-statistics.index') }}" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i><span>Statistik Pengunjung</span></a>
    </li>
@endcan
