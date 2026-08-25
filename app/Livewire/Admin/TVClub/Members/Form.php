<?php

namespace App\Livewire\Admin\TVClub\Members;

use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
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

    public ?TVClubMember $member = null;

    public ?int $memberId = null;

    public ?int $generation_id = null;

    public string $student_number = '';

    public string $name = '';

    public $photo = null;

    public ?string $existingPhoto = null;

    public bool $status = true;

    // Read-only info on edit
    public int $groupsCount = 0;

    public ?string $createdAtFormatted = null;

    public ?string $updatedAtFormatted = null;

    public function mount(?TVClubMember $member = null): void
    {
        if ($member && $member->exists) {
            $this->authorize('update', $member);

            $this->member = $member;
            $this->memberId = $member->id;
            $this->generation_id = (int) $member->generation_id;
            $this->student_number = $member->student_number;
            $this->name = $member->name;
            $this->existingPhoto = $member->photo;
            $this->status = (bool) $member->status;

            $this->groupsCount = $member->groups()->count();
            $this->createdAtFormatted = $member->created_at ? $member->created_at->format('d M Y H:i') : '-';
            $this->updatedAtFormatted = $member->updated_at ? $member->updated_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', TVClubMember::class);

            // Default to latest active generation if available
            $latestGen = TVClubGeneration::where('status', 1)->orderBy('year', 'desc')->first()
                ?? TVClubGeneration::orderBy('year', 'desc')->first();
            $this->generation_id = $latestGen ? $latestGen->id : null;
            $this->status = true;
        }
    }

    protected function rules(): array
    {
        return [
            'generation_id' => ['required', 'integer', 'exists:tvclub_generations,id'],
            'student_number' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'], // Max 2MB (JPG, PNG, WEBP, etc.)
            'status' => ['required', 'boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'generation_id' => 'Generasi Angkatan',
            'student_number' => 'Nomor Induk Mahasiswa (NIM)',
            'name' => 'Nama Lengkap',
            'photo' => 'Foto Profil',
            'status' => 'Status Keanggotaan',
        ];
    }

    public function save(OptimizedImageStorage $imageStorage)
    {
        if ($this->memberId) {
            $this->authorize('update', $this->member);
        } else {
            $this->authorize('create', TVClubMember::class);
        }

        $validated = $this->validate();

        $photoFilename = $this->existingPhoto;

        if ($this->photo) {
            $slugPrefix = Str::slug($this->student_number.'-'.$this->name);
            $photoFilename = $imageStorage->store($this->photo, 'tvclub/members', $slugPrefix, 600, 600, 82);

            // Ensure destination directory exists on public disk
            if (! Storage::disk('public')->exists('tvclub/members')) {
                Storage::disk('public')->makeDirectory('tvclub/members');
            }

            // Delete old photo if exists and replaced
            if ($this->existingPhoto && Storage::disk('public')->exists('tvclub/members/'.$this->existingPhoto)) {
                Storage::disk('public')->delete('tvclub/members/'.$this->existingPhoto);
            }
        }

        $payload = [
            'generation_id' => $validated['generation_id'],
            'student_number' => $validated['student_number'],
            'name' => $validated['name'],
            'photo' => $photoFilename,
            'status' => $validated['status'],
        ];

        if ($this->memberId) {
            $this->member->update($payload);
            session()->flash('success', "Data anggota \"{$this->name}\" berhasil diperbarui.");
        } else {
            TVClubMember::create($payload);
            session()->flash('success', "Anggota \"{$this->name}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.tvclub.members.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->memberId;
        $generations = TVClubGeneration::orderBy('year', 'desc')->get();

        return view('livewire.admin.tvclub.members.form', [
            'isEdit' => $isEdit,
            'generations' => $generations,
        ])->title(($isEdit ? 'Edit Anggota: '.$this->name : 'Tambah Anggota TV Club').' - Admin WPiTV');
    }
}
