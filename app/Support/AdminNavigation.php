<?php

namespace App\Support;

use App\Models\Coverage;
use App\Models\LiveStream;
use App\Models\News;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\User;
use App\Models\Video;
use App\Models\WpitvAgenda;
use App\Models\WpitvOrganizationPosition;
use Illuminate\Support\Facades\Gate;

class AdminNavigation
{
    public function sections(User $user): array
    {
        Gate::forUser($user)->authorize('access-admin');
        $items = [
            'live_streams' => ['Live Streaming', 'admin.live-streams', LiveStream::class, 'bi-broadcast'],
            'programs' => ['Program', 'admin.programs', Program::class, 'bi-collection-play-fill'],
            'videos' => ['Tayangan', 'admin.videos', Video::class, 'bi-camera-video-fill'],
            'news' => ['Berita', 'admin.news', News::class, 'bi-newspaper'],
            'generations' => ['Generasi & Struktur Organisasi', 'admin.tvclub.generations', TVClubGeneration::class, 'bi-diagram-3-fill'],
            'members' => ['Kru / Anggota', 'admin.tvclub.members', TVClubMember::class, 'bi-people-fill'],
            'groups' => ['Kelompok Produksi', 'admin.tvclub.groups', TVClubGroup::class, 'bi-camera-reels-fill'],
            'coverage' => ['Peliputan Acara', 'admin.peliputan', Coverage::class, 'bi-camera-reels-fill'],
            'agenda' => ['Agenda WPiTV', 'admin.agenda', WpitvAgenda::class, 'bi-calendar2-week-fill'],
            'organization' => ['Organisasi WPiTV', 'admin.organization', WpitvOrganizationPosition::class, 'bi-diagram-3-fill'],
            'users' => ['Pengguna', 'admin.users', User::class, 'bi-person-gear'],
            'roles' => ['Peran', 'admin.roles', Role::class, 'bi-shield-lock-fill'],
        ];
        $groups = match ($user->role->name) {
            User::ROLE_SUPER_ADMIN => [
                ['Konten', 'Media & Artikel', ['programs', 'videos', 'news']],
                ['Operasional', null, ['coverage', 'agenda', 'live_streams']],
                ['TV Club', 'Portal TV Club', ['generations', 'members', 'groups']],
                ['Organisasi', null, ['organization']],
                ['Sistem', 'Kontrol Akses', ['users', 'roles']],
            ],
            User::ROLE_PRODUCTION => [
                ['Konten', 'Media & Produksi', ['programs', 'videos']],
                ['Operasional', null, ['coverage', 'agenda', 'live_streams']],
                ['TV Club', 'Portal TV Club', ['generations', 'members', 'groups']],
                ['Organisasi', null, ['organization']],
            ],
            User::ROLE_NEWS => [
                ['Editorial', 'Berita & Tayangan', ['news', 'videos']],
            ],
            User::ROLE_TVCLUB => [
                ['Program Acara', null, ['programs', 'videos']],
                ['TV Club', 'Portal TV Club', ['generations', 'members', 'groups']],
            ],
        };

        $sections = [];
        foreach ($groups as [$title, $parent, $keys]) {
            $links = [];
            foreach ($keys as $key) {
                [$label, $route, $model, $icon] = $items[$key];
                $ability = $items[$key][4] ?? 'viewAny';
                if (! Gate::forUser($user)->allows($ability, $model)) {
                    continue;
                }
                $links[] = [
                    'label' => $label, 'route' => $route.'.index', 'pattern' => $route.'.*', 'icon' => $icon,
                ];
            }
            if ($links !== []) {
                $sections[] = ['title' => $title, 'parent' => $parent, 'links' => $links];
            }
        }

        return $sections;
    }
}
