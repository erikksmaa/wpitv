<?php

namespace App\Livewire\Admin\Roles;

use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Role $role = null;

    public ?int $roleId = null;

    public string $name = '';

    public ?string $description = '';

    // Read-only info on edit
    public int $usersCount = 0;

    public ?string $createdAtFormatted = null;

    public function mount(?Role $role = null): void
    {
        if ($role && $role->exists) {
            $this->authorize('update', $role);

            $this->role = $role;
            $this->roleId = $role->id;
            $this->name = $role->name;
            $this->description = $role->description ?? '';

            $this->usersCount = $role->users()->count();
            $this->createdAtFormatted = $role->created_at ? $role->created_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', Role::class);
        }
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'name')->ignore($this->roleId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Nama Role',
            'description' => 'Deskripsi Role',
        ];
    }

    public function save()
    {
        if ($this->roleId) {
            $this->authorize('update', $this->role);
        } else {
            $this->authorize('create', Role::class);
        }

        $validated = $this->validate();

        if ($this->roleId) {
            $this->role->update($validated);
            session()->flash('success', "Role \"{$this->name}\" berhasil diperbarui.");
        } else {
            Role::create($validated);
            session()->flash('success', "Role \"{$this->name}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.roles.index');
    }

    public function render()
    {
        $isEdit = (bool) $this->roleId;

        return view('livewire.admin.roles.form', [
            'isEdit' => $isEdit,
        ])->title(($isEdit ? 'Edit Peran: '.$this->name : 'Tambah Peran Baru').' - Admin WPiTV');
    }
}
