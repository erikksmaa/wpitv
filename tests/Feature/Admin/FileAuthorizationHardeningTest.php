<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\News\Form as NewsForm;
use App\Livewire\Admin\News\Index as NewsIndex;
use App\Livewire\Admin\Programs\Form as ProgramForm;
use App\Livewire\Admin\TVClub\Members\Form as MemberForm;
use App\Models\News;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use App\Models\User;
use App\Services\OptimizedImageStorage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Tests\TestCase;

class FileAuthorizationHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;

    private User $adminNews;

    private TVClubGeneration $generation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'Super Admin')->firstOrFail()->id,
            'status' => 1,
        ]);
        $this->adminNews = User::factory()->create([
            'role_id' => Role::where('name', 'Admin News')->firstOrFail()->id,
            'status' => 1,
        ]);
        $this->generation = TVClubGeneration::firstOrCreate(
            ['year' => 2096],
            ['name' => 'Generasi File Security', 'status' => true],
        );
    }

    public function test_client_owned_existing_path_properties_are_removed_and_edit_ids_are_locked(): void
    {
        foreach ([
            [ProgramForm::class, 'programId', 'existingImage'],
            [NewsForm::class, 'newsId', 'existingImage'],
            [MemberForm::class, 'memberId', 'existingPhoto'],
        ] as [$component, $idProperty, $removedProperty]) {
            $reflection = new ReflectionClass($component);

            $this->assertFalse($reflection->hasProperty($removedProperty));
            $this->assertNotEmpty($reflection->getProperty($idProperty)->getAttributes(Locked::class));
        }
    }

    public function test_admin_news_replacement_cannot_delete_program_or_another_news_image(): void
    {
        Storage::disk('public')->put('programs/program-a.webp', 'program');
        Storage::disk('public')->put('news/news-a.webp', 'news-a');
        Storage::disk('public')->put('news/news-b.webp', 'news-b');

        $program = Program::create(['name' => 'Program A', 'slug' => 'program-a-file-security', 'image' => 'program-a.webp', 'status' => true]);
        $newsA = News::create(['title' => 'News A', 'slug' => 'news-a-file-security', 'content' => 'A', 'image' => 'news-a.webp']);
        $newsB = News::create(['title' => 'News B', 'slug' => 'news-b-file-security', 'content' => 'B', 'image' => 'news-b.webp']);

        Livewire::actingAs($this->adminNews)
            ->test(NewsForm::class, ['news' => $newsA])
            ->set('image', UploadedFile::fake()->image('replacement.jpg', 300, 200))
            ->call('save')
            ->assertRedirect(route('admin.news.index'));

        $newsA->refresh();
        $this->assertNotSame('news-a.webp', $newsA->image);
        $this->assertSame('news-b.webp', $newsB->fresh()->image);
        $this->assertSame('program-a.webp', $program->fresh()->image);
        Storage::disk('public')->assertMissing('news/news-a.webp');
        Storage::disk('public')->assertExists('news/'.$newsA->image);
        Storage::disk('public')->assertExists('news/news-b.webp');
        Storage::disk('public')->assertExists('programs/program-a.webp');
    }

    public function test_program_edit_without_upload_retains_server_owned_image(): void
    {
        Storage::disk('public')->put('programs/trusted.webp', 'trusted');
        $program = Program::create(['name' => 'Trusted Program', 'slug' => 'trusted-program', 'image' => 'trusted.webp', 'status' => true]);

        Livewire::actingAs($this->superAdmin)
            ->test(ProgramForm::class, ['program' => $program])
            ->set('name', 'Trusted Program Updated')
            ->call('save');

        $this->assertSame('trusted.webp', $program->fresh()->image);
        Storage::disk('public')->assertExists('programs/trusted.webp');
    }

    public function test_member_replacement_uses_own_photo_and_an_opaque_filename(): void
    {
        Storage::disk('public')->put('tvclub/members/member-a.webp', 'member-a');
        Storage::disk('public')->put('tvclub/members/member-b.webp', 'member-b');
        $memberA = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.991122',
            'name' => 'Sensitive Person',
            'photo' => 'member-a.webp',
            'status' => true,
        ]);
        $memberB = TVClubMember::create([
            'generation_id' => $this->generation->id,
            'student_number' => '21.240.991123',
            'name' => 'Other Person',
            'photo' => 'member-b.webp',
            'status' => true,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(MemberForm::class, ['member' => $memberA])
            ->set('photo', UploadedFile::fake()->image('portrait.png', 200, 200))
            ->call('save');

        $memberA->refresh();
        $this->assertMatchesRegularExpression('/^member-\d+-[a-z0-9]{6}\.webp$/', $memberA->photo);
        $this->assertStringNotContainsString('991122', $memberA->photo);
        $this->assertStringNotContainsString('sensitive', $memberA->photo);
        Storage::disk('public')->assertMissing('tvclub/members/member-a.webp');
        Storage::disk('public')->assertExists('tvclub/members/member-b.webp');
        Storage::disk('public')->assertExists('tvclub/members/'.$memberA->photo);
        $this->assertSame('member-b.webp', $memberB->fresh()->photo);
    }

    #[DataProvider('suspiciousStoredPaths')]
    public function test_owned_delete_rejects_traversal_absolute_and_url_paths(string $maliciousPath): void
    {
        Storage::disk('public')->put('programs/sentinel.webp', 'sentinel');
        Storage::disk('public')->put('news/sentinel.webp', 'sentinel');

        $deleted = app(OptimizedImageStorage::class)->deleteOwned(
            OptimizedImageStorage::NEWS,
            $maliciousPath,
            'News',
            123,
        );

        $this->assertFalse($deleted);
        Storage::disk('public')->assertExists('programs/sentinel.webp');
        Storage::disk('public')->assertExists('news/sentinel.webp');
    }

    public static function suspiciousStoredPaths(): array
    {
        return [
            'parent traversal' => ['../programs/foo.webp'],
            'normalized traversal' => ['news/../programs/foo.webp'],
            'environment traversal' => ['../../.env'],
            'windows drive' => ['C:\\temp\\foo.webp'],
            'absolute unix path' => ['/absolute/path'],
            'URL' => ['https://example.com/file.webp'],
            'backslash traversal' => ['..\\programs\\foo.webp'],
        ];
    }

    public function test_replacement_succeeds_when_trusted_old_file_is_missing(): void
    {
        $news = News::create(['title' => 'Missing Image', 'slug' => 'missing-image-security', 'content' => 'Content', 'image' => 'missing.webp']);

        Livewire::actingAs($this->adminNews)
            ->test(NewsForm::class, ['news' => $news])
            ->set('image', UploadedFile::fake()->image('new.jpg', 300, 200))
            ->call('save');

        $news->refresh();
        $this->assertNotSame('missing.webp', $news->image);
        Storage::disk('public')->assertExists('news/'.$news->image);
    }

    public function test_failed_image_processing_keeps_old_database_value_and_file(): void
    {
        Storage::disk('public')->put('news/original.webp', 'original');
        $news = News::create(['title' => 'Failed Upload', 'slug' => 'failed-upload-security', 'content' => 'Content', 'image' => 'original.webp']);

        try {
            Livewire::actingAs($this->adminNews)
                ->test(NewsForm::class, ['news' => $news])
                ->set('image', UploadedFile::fake()->createWithContent('broken.jpg', 'not an image'))
                ->call('save');

            $this->fail('Expected invalid raster decoding to fail.');
        } catch (\Throwable) {
            // The replacement must fail before changing the database or old file.
        }

        $this->assertSame('original.webp', $news->fresh()->image);
        Storage::disk('public')->assertExists('news/original.webp');
        $this->assertSame(['news/original.webp'], Storage::disk('public')->allFiles('news'));
    }

    public function test_database_failure_keeps_old_file_and_cleans_new_replacement(): void
    {
        Storage::disk('public')->put('programs/original.webp', 'original');
        $program = Program::create(['name' => 'Original', 'slug' => 'original-db-failure', 'image' => 'original.webp', 'status' => true]);

        $storage = new class extends OptimizedImageStorage
        {
            public function store(SymfonyUploadedFile $file, string $directory, string $baseName, int $maxWidth, int $maxHeight, int $quality = 82): string
            {
                $filename = parent::store($file, $directory, $baseName, $maxWidth, $maxHeight, $quality);
                Program::create(['name' => 'Concurrent', 'slug' => 'conflicting-after-validation', 'status' => true]);

                return $filename;
            }
        };
        $this->app->instance(OptimizedImageStorage::class, $storage);

        try {
            Livewire::actingAs($this->superAdmin)
                ->test(ProgramForm::class, ['program' => $program])
                ->set('slug', 'conflicting-after-validation')
                ->set('image', UploadedFile::fake()->image('new.jpg', 300, 200))
                ->call('save');

            $this->fail('Expected the database update to fail.');
        } catch (\Throwable) {
            $this->assertSame('original.webp', $program->fresh()->image);
            Storage::disk('public')->assertExists('programs/original.webp');
            $this->assertSame(['programs/original.webp'], Storage::disk('public')->allFiles('programs'));
        }
    }

    public function test_delete_action_refuses_suspicious_stored_path(): void
    {
        Storage::disk('public')->put('programs/keep.webp', 'keep');
        $news = News::create([
            'title' => 'Suspicious Historical Path',
            'slug' => 'suspicious-historical-path',
            'content' => 'Content',
            'image' => '../programs/keep.webp',
        ]);

        Livewire::actingAs($this->adminNews)
            ->test(NewsIndex::class)
            ->call('delete', $news->id);

        $this->assertDatabaseMissing('ms_news', ['id' => $news->id]);
        Storage::disk('public')->assertExists('programs/keep.webp');
    }

    public function test_image_processing_rejects_excessive_source_dimensions_before_decode(): void
    {
        $file = UploadedFile::fake()->image('too-wide.jpg', 10001, 2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dimensi gambar terlalu besar');

        app(OptimizedImageStorage::class)->store(
            $file,
            OptimizedImageStorage::NEWS,
            'oversized-source',
            1600,
            1000,
        );
    }
}
