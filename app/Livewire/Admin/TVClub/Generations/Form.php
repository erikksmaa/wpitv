<?php

namespace App\Livewire\Admin\TVClub\Generations;

use App\Models\TVClubGeneration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?TVClubGeneration $generation = null;

    public ?int $generationId = null;

    public string $name = '';

    public ?int $year = null;

    public ?string $description = '';

    public bool $status = true;

    // Read-only stats on edit
    public int $membersCount = 0;

    public int $groupsCount = 0;

    public ?string $createdAtFormatted = null;

    public ?string $updatedAtFormatted = null;

    public function mount(?TVClubGeneration $generation = null): void
    {
        if ($generation && $generation->exists) {
            $this->authorize('update', $generation);

            $this->generation = $generation;
            $this->generationId = $generation->id;
            $this->name = $generation->name;
            $this->year = (int) $generation->year;
            $this->description = $generation->description ?? '';
            $this->status = (bool) $generation->status;

            $this->membersCount = $generation->members()->count();
            $this->groupsCount = $generation->groups()->count();
            $this->createdAtFormatted = $generation->created_at ? $generation->created_at->format('d M Y H:i') : '-';
            $this->updatedAtFormatted = $generation->updated_at ? $generation->updated_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', TVClubGeneration::class);
            $this->year = (int) date('Y');
            $this->status = true;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'year' => [
                'required',
                'integer',
                'min:1900',
                'max:2100',
                Rule::unique('tvclub_generations', 'year')->ignore($this->generationId),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Nama Generasi',
            'year' => 'Tahun Angkatan',
            'description' => 'Deskripsi',
            'status' => 'Status Aktif',
        ];
    }

    public function save()
    {
        if ($this->generationId) {
            $this->authorize('update', $this->generation);
        } else {
            $this->authorize('create', TVClubGeneration::class);
        }

        $validated = $this->validate();

        if ($this->generationId) {
            $this->generation->update($validated);
            session()->flash('success', "Generasi \"{$this->name}\" berhasil diperbarui.");
        } else {
            TVClubGeneration::create($validated);
            session()->flash('success', "Generasi \"{$this->name}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.tvclub.generations.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->generationId;

        return view('livewire.admin.tvclub.generations.form', [
            'isEdit' => $isEdit,
        ])->title(($isEdit ? 'Edit Generasi: '.$this->name : 'Tambah Generasi Baru').' - Admin WPiTV');
    }
}
