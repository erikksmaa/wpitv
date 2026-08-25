<?php

use App\Livewire\Admin\Dashboard\Index as AdminDashboard;
use App\Livewire\Admin\Programs\Form;
use App\Livewire\Admin\Programs\Index;
use App\Livewire\Public\About as PublicAbout;
use App\Livewire\Public\Home as PublicHome;
use App\Livewire\Public\Peliputan\Create;
use App\Livewire\Public\Peliputan\Status;
use App\Livewire\Public\Peliputan\Success;
use App\Livewire\Public\Programs\Show;
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
Route::get('/tv-club/generasi/{generation}', App\Livewire\Public\TVClub\Generations\Show::class)->name('tvclub.generations.show');
Route::get('/tv-club/anggota/{member}', App\Livewire\Public\TVClub\Members\Show::class)->name('tvclub.members.show');
Route::get('/peliputan', App\Livewire\Public\Peliputan\Index::class)->name('peliputan.index');
Route::get('/peliputan/ajukan', Create::class)->name('peliputan.create');
Route::get('/peliputan/berhasil/{code}', Success::class)->name('peliputan.success');
Route::get('/peliputan/status', Status::class)->name('peliputan.status');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'active', 'role:Super Admin,Admin Production,Admin News'])->group(function () {
    Route::get('/', AdminDashboard::class)->name('admin.index');
    Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');

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

    // Banner Management
    Route::get('/banners', App\Livewire\Admin\Banners\Index::class)->name('admin.banners.index');
    Route::get('/banners/create', App\Livewire\Admin\Banners\Form::class)->name('admin.banners.create');
    Route::get('/banners/{banner}/edit', App\Livewire\Admin\Banners\Form::class)->name('admin.banners.edit');

    // Visitor Statistics
    Route::get('/visitor-statistics', App\Livewire\Admin\VisitorStatistics\Index::class)->name('admin.visitor-statistics.index');

    // TV Club Generations Management
    Route::get('/tvclub/generations', App\Livewire\Admin\TVClub\Generations\Index::class)->name('admin.tvclub.generations.index');
    Route::get('/tvclub/generations/create', App\Livewire\Admin\TVClub\Generations\Form::class)->name('admin.tvclub.generations.create');
    Route::get('/tvclub/generations/{generation}/edit', App\Livewire\Admin\TVClub\Generations\Form::class)->name('admin.tvclub.generations.edit');

    // TV Club Members Management
    Route::get('/tvclub/members', App\Livewire\Admin\TVClub\Members\Index::class)->name('admin.tvclub.members.index');
    Route::get('/tvclub/members/create', App\Livewire\Admin\TVClub\Members\Form::class)->name('admin.tvclub.members.create');
    Route::get('/tvclub/members/{member}/edit', App\Livewire\Admin\TVClub\Members\Form::class)->name('admin.tvclub.members.edit');

    // TV Club Groups Management
    Route::get('/tvclub/groups', App\Livewire\Admin\TVClub\Groups\Index::class)->name('admin.tvclub.groups.index');
    Route::get('/tvclub/groups/create', App\Livewire\Admin\TVClub\Groups\Form::class)->name('admin.tvclub.groups.create');
    Route::get('/tvclub/groups/{group}/edit', App\Livewire\Admin\TVClub\Groups\Form::class)->name('admin.tvclub.groups.edit');

    // User Management
    Route::get('/users', App\Livewire\Admin\Users\Index::class)->name('admin.users.index');
    Route::get('/users/create', App\Livewire\Admin\Users\Form::class)->name('admin.users.create');
    Route::get('/users/{user}/edit', App\Livewire\Admin\Users\Form::class)->name('admin.users.edit');

    // Role Management
    Route::get('/roles', App\Livewire\Admin\Roles\Index::class)->name('admin.roles.index');
    Route::get('/roles/create', App\Livewire\Admin\Roles\Form::class)->name('admin.roles.create');
    Route::get('/roles/{role}/edit', App\Livewire\Admin\Roles\Form::class)->name('admin.roles.edit');

    // Peliputan / Coverage Management
    Route::get('/peliputan', App\Livewire\Admin\Peliputan\Index::class)->name('admin.peliputan.index');
    Route::get('/peliputan/create', App\Livewire\Admin\Peliputan\Form::class)->name('admin.peliputan.create');
    Route::get('/peliputan/{coverage}/edit', App\Livewire\Admin\Peliputan\Form::class)->name('admin.peliputan.edit');
});

require __DIR__.'/auth.php';
