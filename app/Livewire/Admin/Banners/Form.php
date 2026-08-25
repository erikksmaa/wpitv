<?php

namespace App\Livewire\Admin\Banners;

use App\Models\Banner;
use App\Services\OptimizedImageStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?Banner $banner = null;

    public ?int $bannerId = null;

    public string $title = '';

    public ?string $content = '';

    public $image = null;

    public ?string $existingImage = null;

    public ?string $existingImageUrl = null;

    // Read-only metadata on edit
    public ?string $createdAtFormatted = null;

    public ?string $updatedAtFormatted = null;

    public function mount(?Banner $banner = null): void
    {
        if ($banner && $banner->exists) {
            $this->authorize('update', $banner);

            $this->banner = $banner;
            $this->bannerId = $banner->id;
            $this->title = $banner->title;
            $this->content = $banner->content ?? '';
            $this->existingImage = $banner->image;
            $this->existingImageUrl = $banner->image_url;

            $this->createdAtFormatted = $banner->created_at ? $banner->created_at->format('d M Y H:i') : '-';
            $this->updatedAtFormatted = $banner->updated_at ? $banner->updated_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', Banner::class);
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'title' => 'Judul Banner',
            'content' => 'Deskripsi / Konten Banner',
            'image' => 'File Gambar Banner',
        ];
    }

    public function save(OptimizedImageStorage $imageStorage)
    {
        if ($this->bannerId) {
            $this->authorize('update', $this->banner);
        } else {
            $this->authorize('create', Banner::class);
        }

        $validated = $this->validate();

        // Handle Image Upload safely
        if ($this->image) {
            $safeSlug = Str::slug($this->title);
            $newFilename = $imageStorage->store($this->image, 'banners', $safeSlug, 1920, 1080, 82);
            $validated['image'] = $newFilename;

            // Safe replacement: Delete old image if it was managed locally in storage
            if ($this->existingImage && Storage::disk('public')->exists('banners/'.$this->existingImage)) {
                Storage::disk('public')->delete('banners/'.$this->existingImage);
            }
        } else {
            $validated['image'] = $this->existingImage;
        }

        if ($this->bannerId) {
            $this->banner->update($validated);
            session()->flash('success', "Banner \"{$this->title}\" berhasil diperbarui.");
        } else {
            Banner::create($validated);
            session()->flash('success', "Banner \"{$this->title}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.banners.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->bannerId;

        return view('livewire.admin.banners.form', [
            'isEdit' => $isEdit,
        ])->title(($isEdit ? 'Edit Banner: '.$this->title : 'Tambah Banner Baru').' - WPiTV Admin');
    }
}
