<?php

use App\Http\Controllers\EquipmentPhotoController;
use App\Http\Controllers\EquipmentReturnPhotoController;
use App\Livewire\Admin\Dashboard\Index as AdminDashboard;
use App\Livewire\Admin\Programs\Form;
use App\Livewire\Admin\Programs\Index;
use App\Livewire\Public\About as PublicAbout;
use App\Livewire\Public\Home as PublicHome;
use App\Livewire\Public\Peliputan\Create;
use App\Livewire\Public\Peliputan\Status;
use App\Livewire\Public\Peliputan\Success;
use App\Livewire\Public\Programs\Show;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', PublicHome::class)->name('public.home');
Route::get('/tentang', PublicAbout::class)->name('public.about');
Route::get('/program', App\Livewire\Public\Programs\Index::class)->name('program.index');
Route::get('/program/{program:slug}', Show::class)->name('program.show');
Route::get('/tayangan', App\Livewire\Public\Videos\Index::class)->name('video.index');
Route::get('/tayangan/{video}', App\Livewire\Public\Videos\Show::class)->name('video.show');
Route::get('/berita', App\Livewire\Public\News\Index::class)->name('news.index');
Route::get('/berita/{news:slug}', App\Livewire\Public\News\Show::class)->name('news.show');
Route::get('/tv-club', App\Livewire\Public\TVClub\Index::class)->name('tvclub.index');
Route::get('/tv-club/generasi', App\Livewire\Public\TVClub\Generations\Index::class)->name('tvclub.generations.index');
Route::get('/tv-club/generasi/{generation}', App\Livewire\Public\TVClub\Generations\Show::class)->name('tvclub.generations.show');
Route::get('/tv-club/anggota', App\Livewire\Public\TVClub\Members\Index::class)->name('tvclub.members.index');
Route::get('/tv-club/anggota/{member}', App\Livewire\Public\TVClub\Members\Show::class)->name('tvclub.members.show');
Route::get('/tv-club/tim-produksi', App\Livewire\Public\TVClub\Groups\Index::class)->name('tvclub.groups.index');
Route::get('/peliputan', App\Livewire\Public\Peliputan\Index::class)->name('peliputan.index');
Route::get('/peliputan/ajukan', Create::class)->name('peliputan.create');
Route::get('/peliputan/berhasil/{code}', Success::class)->middleware('throttle:15,1')->name('peliputan.success');
Route::get('/peliputan/status', Status::class)->name('peliputan.status');
Route::get('/live', App\Livewire\Public\LiveStreams\Index::class)->name('live.index');
Route::get('/live/{liveStream}', App\Livewire\Public\LiveStreams\Show::class)->whereNumber('liveStream')->name('live.show');
Route::get('/agenda', App\Livewire\Public\Agenda\Index::class)->name('agenda.index');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'session.valid', 'active', 'role:'.implode(',', User::ADMIN_ROLES)])->group(function () {
    Route::get('/inventory', function (\Illuminate\Http\Request $request) {
        $route = match ($request->input('tab')) {
            'categories' => 'admin.inventory.categories',
            'audit' => 'admin.inventory.activity',
            default => 'admin.inventory.equipment',
        };
        return redirect()->route($route, $request->except('tab'));
    })->name('admin.inventory.index');
    Route::get('/inventory/categories', App\Livewire\Admin\Inventory\Index::class)->defaults('section', 'categories')->name('admin.inventory.categories');
    Route::get('/inventory/equipment', App\Livewire\Admin\Inventory\Index::class)->defaults('section', 'models')->name('admin.inventory.equipment');
    Route::get('/inventory/activity', App\Livewire\Admin\Inventory\Index::class)->defaults('section', 'audit')->name('admin.inventory.activity');
    Route::get('/equipment-reports/{section}/csv', App\Http\Controllers\EquipmentReportExportController::class)->whereIn('section', ['inventory', 'loans'])->name('admin.equipment-reports.csv');
    Route::redirect('/equipment-reports/dashboard', '/admin/inventory/equipment');
    Route::get('/equipment-reports/{section?}', App\Livewire\Admin\EquipmentReports\Index::class)->whereIn('section', ['inventory', 'loans'])->name('admin.equipment-reports.index');
    Route::get('/equipment-loans', App\Livewire\Admin\EquipmentLoans\Index::class)->name('admin.equipment-loans.index');
    Route::get('/equipment-loans/create', App\Livewire\Admin\EquipmentLoans\Index::class)->name('admin.equipment-loans.create');
    Route::get('/equipment-loans/{loanId}', App\Livewire\Admin\EquipmentLoans\Show::class)->whereNumber('loanId')->name('admin.equipment-loans.show');
    Route::get('/equipment-loans/{loan}/photos/{photo}', [EquipmentReturnPhotoController::class, 'show'])->whereNumber(['loan', 'photo'])->name('admin.equipment-loans.photos');
    Route::get('/inventory/photos/{kind}/{photo}', [EquipmentPhotoController::class, 'show'])->whereIn('kind', ['model', 'unit'])->whereNumber('photo')->name('admin.inventory.photos');
    Route::get('/social/accounts', App\Livewire\Admin\Social\Catalog::class)->defaults('section', 'accounts')->name('admin.social.accounts');
    Route::get('/social/posts', App\Livewire\Admin\Social\Catalog::class)->defaults('section', 'posts')->name('admin.social.posts');
    Route::get('/social/reports', App\Livewire\Admin\Social\Report::class)->name('admin.social.reports');
    Route::get('/social/reports/draft/{period}', [App\Http\Controllers\SocialDocumentController::class,'draft'])->where('period','[0-9]{4}-(0[1-9]|1[0-2])')->name('admin.social.draft');
    Route::get('/social/reports/versions/{version}/{format?}', [App\Http\Controllers\SocialDocumentController::class,'version'])->whereNumber('version')->whereIn('format',['preview','xlsx','print'])->name('admin.social.preview');
    Route::get('/', AdminDashboard::class)->name('admin.index');
    Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');

    Route::middleware('role:'.User::ROLE_SUPER_ADMIN.','.User::ROLE_PRODUCTION)->group(function () {
        Route::get('/live-streams', App\Livewire\Admin\LiveStreams\Index::class)->name('admin.live-streams.index');
        Route::get('/live-streams/create', App\Livewire\Admin\LiveStreams\Form::class)->name('admin.live-streams.create');
        Route::get('/live-streams/{streamId}/edit', App\Livewire\Admin\LiveStreams\Form::class)->whereNumber('streamId')->name('admin.live-streams.edit');
        Route::get('/live-streams/{streamId}', App\Livewire\Admin\LiveStreams\Show::class)->whereNumber('streamId')->name('admin.live-streams.show');
    });

    // Program Management
    Route::get('/programs', Index::class)->name('admin.programs.index');
    Route::get('/programs/create', Form::class)->name('admin.programs.create');
    Route::get('/programs/{program}/edit', Form::class)->name('admin.programs.edit');

    // Video Management
    Route::get('/videos', App\Livewire\Admin\Videos\Index::class)->name('admin.videos.index');
    Route::get('/videos/create', App\Livewire\Admin\Videos\Form::class)->name('admin.videos.create');
    Route::get('/videos/{video}/edit', App\Livewire\Admin\Videos\Form::class)->name('admin.videos.edit');

    // News Management
    Route::get('/news', App\Livewire\Admin\News\Index::class)->name('admin.news.index');
    Route::get('/news/create', App\Livewire\Admin\News\Form::class)->name('admin.news.create');
    Route::get('/news/{news}/edit', App\Livewire\Admin\News\Form::class)->name('admin.news.edit');

    // Visitor Statistics
    Route::get('/visitor-statistics', App\Livewire\Admin\VisitorStatistics\Index::class)->name('admin.visitor-statistics.index');

    // WPiTV Organization Structure
    Route::get('/organisasi-wpitv', App\Livewire\Admin\Organization\Index::class)->name('admin.organization.index');

    // TV Club Generations Management
    Route::get('/tvclub/generations', App\Livewire\Admin\TVClub\Generations\Index::class)->name('admin.tvclub.generations.index');
    Route::get('/tvclub/generations/create', App\Livewire\Admin\TVClub\Generations\Form::class)->name('admin.tvclub.generations.create');
    Route::get('/tvclub/generations/{generation}/edit', App\Livewire\Admin\TVClub\Generations\Form::class)->name('admin.tvclub.generations.edit');
    Route::get('/tvclub/generations/{generation}/organization', App\Livewire\Admin\TVClub\Organization\Index::class)->name('admin.tvclub.generations.organization');

    // TV Club Members Management
    Route::get('/tvclub/members', App\Livewire\Admin\TVClub\Members\Index::class)->name('admin.tvclub.members.index');
    Route::get('/tvclub/members/create', App\Livewire\Admin\TVClub\Members\Form::class)->name('admin.tvclub.members.create');
    Route::get('/tvclub/members/{member}/edit', App\Livewire\Admin\TVClub\Members\Form::class)->name('admin.tvclub.members.edit');

    // TV Club Groups Management
    Route::get('/tvclub/groups', App\Livewire\Admin\TVClub\Groups\Index::class)->name('admin.tvclub.groups.index');
    Route::get('/tvclub/groups/create', App\Livewire\Admin\TVClub\Groups\Form::class)->name('admin.tvclub.groups.create');
    Route::get('/tvclub/groups/{group}/edit', App\Livewire\Admin\TVClub\Groups\Form::class)->name('admin.tvclub.groups.edit');

    Route::get('/users', App\Livewire\Admin\Users\Index::class)->name('admin.users.index');
    Route::get('/users/create', App\Livewire\Admin\Users\Form::class)->name('admin.users.create');
    Route::get('/users/{user}/edit', App\Livewire\Admin\Users\Form::class)->name('admin.users.edit');

    Route::get('/roles', App\Livewire\Admin\Roles\Index::class)->name('admin.roles.index');
    Route::get('/roles/create', App\Livewire\Admin\Roles\Form::class)->name('admin.roles.create');
    Route::get('/roles/{role}/edit', App\Livewire\Admin\Roles\Form::class)->name('admin.roles.edit');
    Route::get('/settings', App\Livewire\Admin\Settings\Index::class)->name('admin.settings.index');

    // Peliputan / Coverage Management
    Route::get('/peliputan', App\Livewire\Admin\Peliputan\Index::class)->name('admin.peliputan.index');
    Route::get('/peliputan/create', App\Livewire\Admin\Peliputan\Form::class)->name('admin.peliputan.create');
    Route::get('/peliputan/{coverage}/edit', App\Livewire\Admin\Peliputan\Form::class)->name('admin.peliputan.edit');

    // WPiTV Agenda Management
    Route::get('/agenda', App\Livewire\Admin\Agenda\Index::class)->name('admin.agenda.index');
    Route::get('/agenda/create', App\Livewire\Admin\Agenda\Form::class)->name('admin.agenda.create');
    Route::get('/agenda/create/from-peliputan/{coverage}', App\Livewire\Admin\Agenda\Form::class)->name('admin.agenda.from-coverage');
    Route::get('/agenda/{agenda}/edit', App\Livewire\Admin\Agenda\Form::class)->name('admin.agenda.edit');
});

require __DIR__.'/auth.php';
