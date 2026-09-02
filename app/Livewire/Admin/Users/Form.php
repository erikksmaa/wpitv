<?php

namespace App\Livewire\Admin\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?User $user = null;

    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $username = '';

    public ?int $role_id = null;

    public bool $status = true;

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $createdAtFormatted = null;

    public function mount(?User $user = null): void
    {
        if ($user && $user->exists) {
            $this->authorize('update', $user);

            $this->user = $user;
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->username = $user->username;
            $this->role_id = (int) $user->role_id;
            $this->status = (bool) $user->status;
            $this->createdAtFormatted = $user->created_at ? $user->created_at->format('d M Y H:i') : '-';
        } else {
            $this->authorize('create', User::class);

            $defaultRole = Role::first();
            $this->role_id = $defaultRole ? $defaultRole->id : null;
            $this->status = true;
        }
    }

    protected function rules(): array
    {
        $passwordRules = $this->userId
            ? ['nullable', 'string', Password::min(8), 'confirmed']
            : ['required', 'string', Password::min(8), 'confirmed'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->userId),
            ],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['required', 'boolean'],
            'password' => $passwordRules,
            'password_confirmation' => $this->userId ? ['nullable'] : ['required'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Nama Pengguna',
            'username' => 'Username',
            'role_id' => 'Role Hak Akses',
            'status' => 'Status Akun',
            'password' => 'Kata Sandi',
            'password_confirmation' => 'Konfirmasi Kata Sandi',
        ];
    }

    public function save()
    {
        if ($this->userId) {
            $this->authorize('update', $this->user);
        } else {
            $this->authorize('create', User::class);
        }

        $validated = $this->validate();

        $payload = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'role_id' => $validated['role_id'],
            'status' => $validated['status'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        if ($this->userId) {
            DB::transaction(function () use ($payload): void {
                $superAdminRole = Role::where('name', User::ROLE_SUPER_ADMIN)->lockForUpdate()->firstOrFail();
                $user = User::lockForUpdate()->findOrFail($this->userId);
                $removesActiveSuperAdmin = (int) $user->role_id === (int) $superAdminRole->id
                    && $user->status
                    && ((int) $payload['role_id'] !== (int) $superAdminRole->id || ! $payload['status']);

                if ($removesActiveSuperAdmin) {
                    $activeSuperAdmins = User::where('role_id', $superAdminRole->id)
                        ->where('status', true)
                        ->lockForUpdate()
                        ->count();

                    if ($activeSuperAdmins <= 1) {
                        throw ValidationException::withMessages([
                            'status' => 'Sistem harus mempertahankan minimal satu Super Admin yang aktif.',
                        ]);
                    }
                }

                $user->update($payload);
                $this->user = $user->fresh();
            });
            session()->flash('success', "Akun user \"{$this->name}\" berhasil diperbarui.");
        } else {
            User::create($payload);
            session()->flash('success', "Akun user \"{$this->name}\" berhasil ditambahkan.");
        }

        return redirect()->route('admin.users.index');
    }

    public function render()
    {
        $this->authorize($this->user?->exists ? 'update' : 'create', $this->user?->exists ? $this->user : User::class);

        $isEdit = (bool) $this->userId;
        $roles = Role::orderBy('name', 'asc')->get();

        return view('livewire.admin.users.form', [
            'isEdit' => $isEdit,
            'roles' => $roles,
        ])->title(($isEdit ? 'Edit Pengguna: '.$this->name : 'Tambah Pengguna Baru').' - Admin WPiTV');
    }
}
