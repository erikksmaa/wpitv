<?php

namespace App\Livewire\Admin\Programs;

use App\Models\Program;
use App\Services\OptimizedImageStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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

    public ?Program $program = null;

    public ?int $programId = null;

    public string $name = '';

    public string $slug = '';

    public ?string $description = '';

    public bool $status = true;

    public $image = null;

    public ?string $existingImage = null;

    public ?string $existingImageUrl = null;

    public bool $slugManuallyEdited = false;

    // Read-only metadata on edit
    public int $videosCount = 0;

    public int $tvClubGroupsCount = 0;

    public ?string $createdAtFormatted = null;

    public ?string $updatedAtFormatted = null;

    public function mount(?Program $program = null): void
    {
        if ($program && $program->exists) {
            $this->authorize('update', $program);

            $this->program = $program;
            $this->programId = $program->id;
            $this->name = $program->name;
            $this->slug = $program->slug;
            $this->description = $program->description ?? '';
            $this->status = (bool) $program->status;
            $this->existingImage = $program->image;
            $this->existingImageUrl = $program->image_url;
            $this->slugManuallyEdited = true; // Keep existing slug intact on edit

            $this->videosCount = $program->videos()->count();
            $this->tvClubGroupsCount = $program->tvClubGroups()->count();
            $this->createdAtFormatted = $program->created_at ? $program->created_at->format('d M Y H:i') : '-';
            $this->updatedAtFormatted = $program->updated_at ? $program->updated_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', Program::class);
            $this->status = true;
        }
    }

    public function updatedName(string $value): void
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
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique('ms_program', 'slug')->ignore($this->programId),
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'status' => ['required', 'boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Nama Program',
            'slug' => 'Slug URL',
            'description' => 'Deskripsi Program',
            'image' => 'Cover Gambar',
            'status' => 'Status Program',
        ];
    }

    public function save(OptimizedImageStorage $imageStorage)
    {
        if ($this->programId) {
            $this->authorize('update', $this->program);
        } else {
            $this->authorize('create', Program::class);
        }

        $validated = $this->validate();

        // Handle Image Upload safely
        if ($this->image) {
            $safeSlug = Str::slug($this->slug ?: $this->name);
            $newFilename = $imageStorage->store($this->image, 'programs', $safeSlug, 900, 1200, 82);
            $validated['image'] = $newFilename;

            // Safe replacement: Delete old image if it was managed locally in storage
            if ($this->existingImage && Storage::disk('public')->exists('programs/'.$this->existingImage)) {
                Storage::disk('public')->delete('programs/'.$this->existingImage);
            }
        } else {
            // Keep existing image on edit, or null on create
            $validated['image'] = $this->existingImage;
        }

        if ($this->programId) {
            $this->program->update($validated);
            session()->flash('success', "Program \"{$this->name}\" berhasil diperbarui.");
        } else {
            Program::create($validated);
            session()->flash('success', "Program \"{$this->name}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.programs.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->programId;

        return view('livewire.admin.programs.form', [
            'isEdit' => $isEdit,
        ])->title(($isEdit ? 'Edit Program: '.$this->name : 'Tambah Program Baru').' - Admin WPiTV');
    }
}
