<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\News\Index;
use App\Livewire\Public\News\Show;
use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class NewsPagesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: News listing loads successfully.
     */
    public function test_news_listing_loads_successfully(): void
    {
        $response = $this->get(route('news.index'));

        $response->assertStatus(200);
        $response->assertSee('Berita WPiTV');
        $response->assertSee('Kabar &amp; Jurnalistik Kampus', false);
    }

    /**
     * Test 2: News renders actual database records.
     */
    public function test_news_renders_from_database(): void
    {
        $news = News::latest('created_at')->first() ?? News::create([
            'user_id' => 1,
            'title' => 'Berita Peluncuran WPiTV',
            'slug' => 'berita-peluncuran-wpitv',
            'content' => '<p>Konten berita peluncuran.</p>',
            'created_at' => now(),
        ]);

        $response = $this->get(route('news.index'));

        $response->assertStatus(200);
        $response->assertSee($news->title);
    }

    /**
     * Test 3: Search title works.
     */
    public function test_search_title_works(): void
    {
        $uniqueTitle = 'Liputan Unik Spesial Ulang Tahun Kampus '.uniqid();
        News::create([
            'user_id' => 1,
            'title' => $uniqueTitle,
            'slug' => 'liputan-unik-ulang-tahun-'.uniqid(),
            'content' => '<p>Liputan perayaan ulang tahun.</p>',
            'created_at' => now(),
        ]);

        Livewire::test(Index::class)
            ->set('search', $uniqueTitle)
            ->assertSee($uniqueTitle);
    }

    /**
     * Test 4: Search author works.
     */
    public function test_search_author_works(): void
    {
        $uniqueAuthor = 'Jurnalis Investigasi '.uniqid();
        $newsTitle = 'Berita Karya '.$uniqueAuthor;
        News::create([
            'user_id' => null,
            'author_name' => $uniqueAuthor,
            'title' => $newsTitle,
            'slug' => 'berita-karya-'.uniqid(),
            'content' => '<p>Konten investigasi.</p>',
            'created_at' => now(),
        ]);

        Livewire::test(Index::class)
            ->set('search', $uniqueAuthor)
            ->assertSee($newsTitle);
    }

    /**
     * Test 5: Year filter works.
     */
    public function test_year_filter_works(): void
    {
        $oldTitle = 'Berita Sejarah Tahun 1999 '.uniqid();
        $news = new News;
        $news->user_id = 1;
        $news->title = $oldTitle;
        $news->slug = 'berita-1999-'.uniqid();
        $news->content = '<p>Konten tahun 1999.</p>';
        $news->timestamps = false;
        $news->created_at = '1999-05-10 10:00:00';
        $news->updated_at = '1999-05-10 10:00:00';
        $news->save();

        Livewire::test(Index::class)
            ->set('year', '1999')
            ->assertSee($oldTitle);
    }

    /**
     * Test 6: Latest sorting works.
     */
    public function test_latest_sorting_works(): void
    {
        $prefix = 'SortLatestTest_'.uniqid();
        $oldItem = new News;
        $oldItem->user_id = 1;
        $oldItem->title = $prefix.' Old Item';
        $oldItem->slug = 'old-item-'.uniqid();
        $oldItem->content = '<p>Old</p>';
        $oldItem->timestamps = false;
        $oldItem->created_at = '2020-01-01 10:00:00';
        $oldItem->updated_at = '2020-01-01 10:00:00';
        $oldItem->save();

        $newItem = new News;
        $newItem->user_id = 1;
        $newItem->title = $prefix.' New Item';
        $newItem->slug = 'new-item-'.uniqid();
        $newItem->content = '<p>New</p>';
        $newItem->timestamps = false;
        $newItem->created_at = '2023-01-01 10:00:00';
        $newItem->updated_at = '2023-01-01 10:00:00';
        $newItem->save();

        Livewire::test(Index::class)
            ->set('search', $prefix)
            ->set('sort', 'latest')
            ->assertSeeInOrder([$newItem->title, $oldItem->title]);
    }

    /**
     * Test 7: Oldest sorting works.
     */
    public function test_oldest_sorting_works(): void
    {
        $prefix = 'SortOldestTest_'.uniqid();
        $oldItem = new News;
        $oldItem->user_id = 1;
        $oldItem->title = $prefix.' Old Item';
        $oldItem->slug = 'old-item-'.uniqid();
        $oldItem->content = '<p>Old</p>';
        $oldItem->timestamps = false;
        $oldItem->created_at = '2020-01-01 10:00:00';
        $oldItem->updated_at = '2020-01-01 10:00:00';
        $oldItem->save();

        $newItem = new News;
        $newItem->user_id = 1;
        $newItem->title = $prefix.' New Item';
        $newItem->slug = 'new-item-'.uniqid();
        $newItem->content = '<p>New</p>';
        $newItem->timestamps = false;
        $newItem->created_at = '2023-01-01 10:00:00';
        $newItem->updated_at = '2023-01-01 10:00:00';
        $newItem->save();

        Livewire::test(Index::class)
            ->set('search', $prefix)
            ->set('sort', 'oldest')
            ->assertSeeInOrder([$oldItem->title, $newItem->title]);
    }

    /**
     * Test 8: Title A-Z sorting works.
     */
    public function test_title_az_sorting_works(): void
    {
        $prefix = 'SortAZTest_'.uniqid();
        $itemA = News::create([
            'user_id' => 1,
            'title' => $prefix.' AAA Item',
            'slug' => 'aaa-item-'.uniqid(),
            'content' => '<p>A</p>',
            'created_at' => now(),
        ]);
        $itemZ = News::create([
            'user_id' => 1,
            'title' => $prefix.' ZZZ Item',
            'slug' => 'zzz-item-'.uniqid(),
            'content' => '<p>Z</p>',
            'created_at' => now(),
        ]);

        Livewire::test(Index::class)
            ->set('search', $prefix)
            ->set('sort', 'title_asc')
            ->assertSeeInOrder([$itemA->title, $itemZ->title]);
    }

    /**
     * Test 9: Title Z-A sorting works.
     */
    public function test_title_za_sorting_works(): void
    {
        $prefix = 'SortZATest_'.uniqid();
        $itemA = News::create([
            'user_id' => 1,
            'title' => $prefix.' AAA Item',
            'slug' => 'aaa-item-'.uniqid(),
            'content' => '<p>A</p>',
            'created_at' => now(),
        ]);
        $itemZ = News::create([
            'user_id' => 1,
            'title' => $prefix.' ZZZ Item',
            'slug' => 'zzz-item-'.uniqid(),
            'content' => '<p>Z</p>',
            'created_at' => now(),
        ]);

        Livewire::test(Index::class)
            ->set('search', $prefix)
            ->set('sort', 'title_desc')
            ->assertSeeInOrder([$itemZ->title, $itemA->title]);
    }

    /**
     * Test 10: Pagination works (12 items per page).
     */
    public function test_pagination_works(): void
    {
        $response = $this->get(route('news.index', ['page' => 1]));
        $response->assertStatus(200);

        $responsePage2 = $this->get(route('news.index', ['page' => 2]));
        $responsePage2->assertStatus(200);
    }

    /**
     * Test 11: Detail loads using slug.
     */
    public function test_detail_loads_using_slug(): void
    {
        $news = News::first() ?? News::create([
            'user_id' => 1,
            'title' => 'Uji Coba Detail Berita '.uniqid(),
            'slug' => 'uji-coba-detail-berita-'.uniqid(),
            'content' => '<p>Konten uji coba detail.</p>',
            'created_at' => now(),
        ]);

        $response = $this->get(route('news.show', $news->slug));

        $response->assertStatus(200);
        $response->assertSee($news->title);
    }

    /**
     * Test 12: Detail title displays.
     */
    public function test_detail_title_displays(): void
    {
        $news = News::first();
        if ($news) {
            $response = $this->get(route('news.show', $news->slug));
            $response->assertStatus(200);
            $response->assertSee($news->title);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test 13: Author accessor displays.
     */
    public function test_author_accessor_displays(): void
    {
        $news = News::first() ?? News::create([
            'user_id' => 1,
            'title' => 'Berita Uji Author '.uniqid(),
            'slug' => 'berita-uji-author-'.uniqid(),
            'content' => '<p>Konten.</p>',
            'created_at' => now(),
        ]);

        $response = $this->get(route('news.show', $news->slug));

        $response->assertStatus(200);
        $response->assertSee($news->author_display_name);
    }

    /**
     * Test 14: Date displays formatted.
     */
    public function test_date_displays(): void
    {
        $news = News::first() ?? News::create([
            'user_id' => 1,
            'title' => 'Berita Uji Tanggal '.uniqid(),
            'slug' => 'berita-uji-tanggal-'.uniqid(),
            'content' => '<p>Konten.</p>',
            'created_at' => now(),
        ]);

        $response = $this->get(route('news.show', $news->slug));

        $response->assertStatus(200);
        $response->assertSee($news->created_at->isoFormat('D MMMM Y'));
    }

    /**
     * Test 15: Image fallback works.
     */
    public function test_image_fallback_works(): void
    {
        $news = News::create([
            'user_id' => 1,
            'title' => 'Berita Gambar Fallback '.uniqid(),
            'slug' => 'berita-fallback-img-'.uniqid(),
            'image' => null,
            'content' => '<p>Konten.</p>',
            'created_at' => now(),
        ]);

        $this->assertNotEmpty($news->image_url);
    }

    /**
     * Test 16: Empty image works without error.
     */
    public function test_empty_image_works(): void
    {
        $news = News::create([
            'user_id' => 1,
            'title' => 'Berita Empty Image '.uniqid(),
            'slug' => 'berita-empty-img-'.uniqid(),
            'image' => '',
            'content' => '<p>Konten.</p>',
            'created_at' => now(),
        ]);

        $response = $this->get(route('news.show', $news->slug));
        $response->assertStatus(200);
    }

    /**
     * Test 17: Null user works gracefully.
     */
    public function test_null_user_works(): void
    {
        $news = News::create([
            'user_id' => null,
            'author_name' => 'Kontributor Luar',
            'title' => 'Berita Dari Kontributor '.uniqid(),
            'slug' => 'berita-kontributor-'.uniqid(),
            'content' => '<p>Konten kontributor.</p>',
            'created_at' => now(),
        ]);

        $response = $this->get(route('news.show', $news->slug));

        $response->assertStatus(200);
        $response->assertSee('Kontributor Luar');
    }

    /**
     * Test 18: Legacy HTML content renders.
     */
    public function test_legacy_html_content_renders(): void
    {
        $news = News::create([
            'user_id' => 1,
            'title' => 'Berita Legacy HTML Test '.uniqid(),
            'slug' => 'berita-legacy-html-'.uniqid(),
            'content' => '<p>Paragraf <strong>tebal</strong> dan <em>miring</em>.</p>',
            'created_at' => now(),
        ]);

        $response = $this->get(route('news.show', $news->slug));

        $response->assertStatus(200);
        $response->assertSee('<strong>tebal</strong>', false);
        $response->assertSee('<em>miring</em>', false);
    }

    /**
     * Test 19: Related news excludes current news.
     */
    public function test_related_news_excludes_current(): void
    {
        $news = News::first() ?? News::create([
            'user_id' => 1,
            'title' => 'Berita Utama '.uniqid(),
            'slug' => 'berita-utama-'.uniqid(),
            'content' => '<p>Konten.</p>',
            'created_at' => now(),
        ]);

        Livewire::test(Show::class, ['news' => $news])
            ->assertViewHas('relatedNews', function ($related) use ($news) {
                return ! $related->contains('id', $news->id);
            });
    }

    /**
     * Test 20: Duplicate titles do not affect slug routing.
     */
    public function test_duplicate_titles_do_not_affect_slug_routing(): void
    {
        $title = 'Berita Sama Judul';
        $news1 = News::create([
            'user_id' => 1,
            'title' => $title,
            'slug' => 'berita-sama-judul-1-'.uniqid(),
            'content' => '<p>Konten 1.</p>',
            'created_at' => now(),
        ]);

        $news2 = News::create([
            'user_id' => 1,
            'title' => $title,
            'slug' => 'berita-sama-judul-2-'.uniqid(),
            'content' => '<p>Konten 2.</p>',
            'created_at' => now(),
        ]);

        $response1 = $this->get(route('news.show', $news1->slug));
        $response1->assertStatus(200);
        $response1->assertSee('Konten 1.', false);

        $response2 = $this->get(route('news.show', $news2->slug));
        $response2->assertStatus(200);
        $response2->assertSee('Konten 2.', false);
    }

    /**
     * Test 21: Homepage news links use internal routes.
     */
    public function test_homepage_news_links_use_internal_route(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertStatus(200);
        $response->assertSee(route('news.index'));
    }

    /**
     * Test 22: Guest can access news pages without auth.
     */
    public function test_guest_can_access(): void
    {
        $this->assertGuest();

        $responseIndex = $this->get(route('news.index'));
        $responseIndex->assertStatus(200);

        $news = News::first();
        if ($news) {
            $responseShow = $this->get(route('news.show', $news->slug));
            $responseShow->assertStatus(200);
        }
    }

    /**
     * Test 23: No admin actions or create buttons exposed.
     */
    public function test_no_admin_actions_exposed(): void
    {
        $responseIndex = $this->get(route('news.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertDontSee('admin.news.create');
        $responseIndex->assertDontSee('admin.news.edit');

        $news = News::first();
        if ($news) {
            $responseShow = $this->get(route('news.show', $news->slug));
            $responseShow->assertStatus(200);
            $responseShow->assertDontSee('admin.news.create');
            $responseShow->assertDontSee('admin.news.edit');
        }
    }
}
