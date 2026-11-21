<?php

namespace App\Support;

use App\Models\Coverage;
use App\Models\EquipmentLoan;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\LiveStream;
use App\Models\MonthlyActivityReport;
use App\Models\News;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialPost;
use App\Models\TVClubSocialReportVersion;
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
            'monthly_reports' => ['Laporan Bulanan', 'admin.monthly-reports', MonthlyActivityReport::class, 'bi-journal-text'],
            'live_streams' => ['Live Streaming', 'admin.live-streams', LiveStream::class, 'bi-broadcast'],
            'equipment_reports' => ['Laporan Inventaris & Peminjaman', 'admin.equipment-reports', EquipmentLoan::class, 'bi-file-earmark-spreadsheet', 'viewReport'],
            'equipment_loans' => ['Peminjaman Alat', 'admin.equipment-loans', EquipmentLoan::class, 'bi-box-arrow-up-right'],
            'inventory_units' => ['Unit Inventaris', 'admin.inventory.units', InventoryUnit::class, 'bi-camera'],
            'inventory_categories' => ['Kategori Inventaris', 'admin.inventory.categories', InventoryCategory::class, 'bi-box-seam'],
            'programs' => ['Program', 'admin.programs', Program::class, 'bi-collection-play-fill'],
            'videos' => ['Tayangan', 'admin.videos', Video::class, 'bi-camera-video-fill'],
            'news' => ['Berita', 'admin.news', News::class, 'bi-newspaper'],
            'generations' => ['Generasi & Struktur Organisasi', 'admin.tvclub.generations', TVClubGeneration::class, 'bi-diagram-3-fill'],
            'members' => ['Kru / Anggota', 'admin.tvclub.members', TVClubMember::class, 'bi-people-fill'],
            'groups' => ['Kelompok Produksi', 'admin.tvclub.groups', TVClubGroup::class, 'bi-camera-reels-fill'],
            'social_accounts' => ['Akun Media Sosial', 'admin.tvclub.social-accounts', TVClubSocialAccount::class, 'bi-person-badge'],
            'social_posts' => ['Konten Media Sosial', 'admin.tvclub.social-posts', TVClubSocialPost::class, 'bi-collection-play'],
            'social_reports' => ['Laporan Media Sosial', 'admin.tvclub.social-reports', TVClubSocialReportVersion::class, 'bi-journal-text'],
            'coverage' => ['Peliputan Acara', 'admin.peliputan', Coverage::class, 'bi-camera-reels-fill'],
            'agenda' => ['Agenda WPiTV', 'admin.agenda', WpitvAgenda::class, 'bi-calendar2-week-fill'],
            'organization' => ['Organisasi WPiTV', 'admin.organization', WpitvOrganizationPosition::class, 'bi-diagram-3-fill'],
            'users' => ['Pengguna', 'admin.users', User::class, 'bi-person-gear'],
            'roles' => ['Peran', 'admin.roles', Role::class, 'bi-shield-lock-fill'],
        ];
        $groups = match ($user->role->name) {
            User::ROLE_SUPER_ADMIN => [
                ['Konten', 'Media & Artikel', ['programs', 'videos', 'news']],
                ['Operasional', null, ['coverage', 'agenda', 'live_streams', 'equipment_loans', 'inventory_categories', 'inventory_units', 'equipment_reports', 'monthly_reports']],
                ['TV Club', 'Portal TV Club', ['generations', 'members', 'groups', 'social_accounts', 'social_posts', 'social_reports']],
                ['Organisasi', null, ['organization']],
                ['Sistem', 'Kontrol Akses', ['users', 'roles']],
            ],
            User::ROLE_PRODUCTION => [
                ['Konten', 'Media & Produksi', ['programs', 'videos']],
                ['Operasional', null, ['coverage', 'agenda', 'live_streams', 'equipment_loans', 'inventory_categories', 'inventory_units', 'equipment_reports', 'monthly_reports']],
                ['TV Club', 'Portal TV Club', ['generations', 'members', 'groups']],
                ['Organisasi', null, ['organization']],
            ],
            User::ROLE_NEWS => [
                ['Editorial', 'Berita & Tayangan', ['news', 'videos']],
                ['Operasional', null, ['monthly_reports']],
            ],
            User::ROLE_TVCLUB => [
                ['Program Acara', null, ['programs', 'videos']],
                ['TV Club', 'Portal TV Club', ['generations', 'members', 'groups', 'social_accounts', 'social_posts', 'social_reports']],
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
