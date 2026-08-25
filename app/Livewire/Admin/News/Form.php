<?php

namespace App\Livewire\Admin\News;

use App\Models\News;
use App\Services\OptimizedImageStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?News $news = null;

    public ?int $newsId = null;

    public string $title = '';

    public string $slug = '';

    public string $author_name = '';

    public string $content = '';

    public $image = null;

    public ?string $existingImage = null;

    public ?string $existingImageUrl = null;

    public bool $slugManuallyEdited = false;

    // Read-only metadata on edit
    public ?string $uploaderName = null;

    public ?string $createdAtFormatted = null;

    public ?string $updatedAtFormatted = null;

    public function mount(?News $news = null): void
    {
        if ($news && $news->exists) {
            $this->authorize('update', $news);

            $this->news = $news;
            $this->newsId = $news->id;
            $this->title = $news->title;
            $this->slug = $news->slug;
            $this->author_name = $news->author_name ?? '';
            $this->content = $news->content;
            $this->existingImage = $news->image;
            $this->existingImageUrl = $news->image_url;
            $this->slugManuallyEdited = true; // Keep existing slug intact on edit

            $this->uploaderName = $news->user ? $news->user->name : ($news->author_name ?: 'Legacy / System');
            $this->createdAtFormatted = $news->created_at ? $news->created_at->format('d M Y H:i') : '-';
            $this->updatedAtFormatted = $news->updated_at ? $news->updated_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', News::class);

            $this->author_name = Auth::user()->name ?? 'Redaksi WPiTV';
        }
    }

    public function updatedTitle(string $value): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugManuallyEdited = true;
        $this->slug = Str::slug($value);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:220',
                Rule::unique('ms_news', 'slug')->ignore($this->newsId),
            ],
            'author_name' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'title' => 'Judul Berita',
            'slug' => 'Slug URL',
            'author_name' => 'Nama Penulis',
            'content' => 'Isi Konten Berita',
            'image' => 'Cover Gambar',
        ];
    }

    public function save(OptimizedImageStorage $imageStorage)
    {
        if ($this->newsId) {
            $this->authorize('update', $this->news);
        } else {
            $this->authorize('create', News::class);
        }

        $validated = $this->validate();

        // Handle Image Upload safely
        if ($this->image) {
            $safeSlug = Str::slug($this->slug ?: $this->title);
            $newFilename = $imageStorage->store($this->image, 'news', $safeSlug, 1600, 1000, 82);
            $validated['image'] = $newFilename;

            // Safe replacement: Delete old image if it was managed locally in storage
            if ($this->existingImage && Storage::disk('public')->exists('news/'.$this->existingImage)) {
                Storage::disk('public')->delete('news/'.$this->existingImage);
            }
        } else {
            $validated['image'] = $this->existingImage;
        }

        if ($this->newsId) {
            // Preserve original user_id on edit
            $this->news->update($validated);
            session()->flash('success', "Artikel berita \"{$this->title}\" berhasil diperbarui.");
        } else {
            $validated['user_id'] = Auth::id();
            News::create($validated);
            session()->flash('success', "Artikel berita \"{$this->title}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.news.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->newsId;

        return view('livewire.admin.news.form', [
            'isEdit' => $isEdit,
        ])->title(($isEdit ? 'Edit Berita: '.$this->title : 'Tambah Berita Baru').' - WPiTV Admin');
    }
}
