<?php

namespace App\Livewire\Admin\Programs;

use App\Models\Program;
use App\Services\OptimizedImageStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?Program $program = null;

    #[Locked]
    public ?int $programId = null;

    public string $name = '';

    public string $slug = '';

    public ?string $description = '';

    public bool $status = true;

    public $image = null;

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
        $program = null;
        if ($this->programId !== null) {
            $program = Program::findOrFail($this->programId);
            $this->authorize('update', $program);
        } else {
            $this->authorize('create', Program::class);
        }

        $validated = $this->validate();
        $oldFilename = $program?->image;
        $newFilename = null;

        try {
            if ($this->image) {
                $safeSlug = Str::slug($this->slug ?: $this->name);
                $newFilename = $imageStorage->store($this->image, OptimizedImageStorage::PROGRAMS, $safeSlug, 900, 1200, 82);
            }

            $validated['image'] = $newFilename ?? $oldFilename;

            DB::transaction(function () use ($program, $validated): void {
                if ($program) {
                    $program->update($validated);
                } else {
                    Program::create($validated);
                }
            });
        } catch (Throwable $exception) {
            if ($newFilename !== null) {
                $imageStorage->deleteOwned(OptimizedImageStorage::PROGRAMS, $newFilename, 'Program', $program?->id);
            }

            throw $exception;
        }

        if ($newFilename !== null) {
            $imageStorage->deleteOwned(OptimizedImageStorage::PROGRAMS, $oldFilename, 'Program', $program?->id);
        }

        if ($program) {
            session()->flash('success', "Program \"{$this->name}\" berhasil diperbarui.");
        } else {
            session()->flash('success', "Program \"{$this->name}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.programs.index');
    }

    public function render()
    {
        $this->authorize($this->program?->exists ? 'update' : 'create', $this->program?->exists ? $this->program : Program::class);

        $isEdit = (bool) $this->programId;

        return view('livewire.admin.programs.form', [
            'isEdit' => $isEdit,
        ])->title(($isEdit ? 'Edit Program: '.$this->name : 'Tambah Program Baru').' - Admin WPiTV');
    }
}
