<?php

namespace App\Livewire\Admin\TVClub\Members;

use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use App\Services\OptimizedImageStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
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

    public ?TVClubMember $member = null;

    #[Locked]
    public ?int $memberId = null;

    public ?int $generation_id = null;

    public string $student_number = '';

    public string $name = '';

    public $photo = null;

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
        $member = null;
        if ($this->memberId !== null) {
            $member = TVClubMember::findOrFail($this->memberId);
            $this->authorize('update', $member);
        } else {
            $this->authorize('create', TVClubMember::class);
        }

        $validated = $this->validate();
        $oldFilename = $member?->photo;
        $newFilename = null;

        $payload = [
            'generation_id' => $validated['generation_id'],
            'student_number' => $validated['student_number'],
            'name' => $validated['name'],
            'status' => $validated['status'],
        ];

        try {
            if ($this->photo) {
                $newFilename = $imageStorage->store($this->photo, OptimizedImageStorage::TVCLUB_MEMBERS, 'member', 600, 600, 82);
            }

            $payload['photo'] = $newFilename ?? $oldFilename;

            DB::transaction(function () use ($member, $payload): void {
                if ($member) {
                    $member->update($payload);
                } else {
                    TVClubMember::create($payload);
                }
            });
        } catch (Throwable $exception) {
            if ($newFilename !== null) {
                $imageStorage->deleteOwned(OptimizedImageStorage::TVCLUB_MEMBERS, $newFilename, 'TVClubMember', $member?->id);
            }

            throw $exception;
        }

        if ($newFilename !== null) {
            $imageStorage->deleteOwned(OptimizedImageStorage::TVCLUB_MEMBERS, $oldFilename, 'TVClubMember', $member?->id);
        }

        if ($member) {
            session()->flash('success', "Data anggota \"{$this->name}\" berhasil diperbarui.");
        } else {
            session()->flash('success', "Anggota \"{$this->name}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.tvclub.members.index');
    }

    public function render()
    {
        $this->authorize($this->member?->exists ? 'update' : 'create', $this->member?->exists ? $this->member : TVClubMember::class);

        $isEdit = (bool) $this->memberId;
        $generations = TVClubGeneration::orderBy('year', 'desc')->get();

        return view('livewire.admin.tvclub.members.form', [
            'isEdit' => $isEdit,
            'generations' => $generations,
        ])->title(($isEdit ? 'Edit Anggota: '.$this->name : 'Tambah Anggota TV Club').' - Admin WPiTV');
    }
}
