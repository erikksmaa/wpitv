<?php

namespace App\Livewire\Admin\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Pengguna - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $roleFilter = '';

    #[Url(history: true)]
    public string $statusFilter = '';

    #[Url(history: true)]
    public string $sortField = 'name';

    #[Url(history: true)]
    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = ['name', 'username', 'role', 'status', 'created_at'];
        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['created_at'], true) ? 'desc' : 'asc';
        }
    }

    public function delete(int $id): void
    {
        $user = User::with('role')->findOrFail($id);

        $this->authorize('delete', $user);

        // Protection 1: Cannot delete currently logged in user
        if ($user->id === Auth::id()) {
            session()->flash('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');

            return;
        }

        // Protection 2: Cannot delete the last active Super Admin
        if ($user->isSuperAdmin()) {
            $activeSuperAdminsCount = User::whereHas('role', function ($q) {
                $q->where('name', 'Super Admin');
            })->where('status', 1)->count();

            if ($activeSuperAdminsCount <= 1) {
                session()->flash('error', 'Sistem harus mempertahankan minimal satu Super Admin yang aktif.');

                return;
            }
        }

        $userName = $user->name ?: $user->username;
        $user->delete();

        session()->flash('success', "User \"{$userName}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', User::class);

        $allowedSortFields = ['name', 'username', 'role', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';

        // Summary Aggregations
        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 1)->count(),
            'inactive' => User::where('status', 0)->count(),
            'roles_count' => Role::count(),
        ];

        // Roles list for filter
        $roles = Role::orderBy('name', 'asc')->get();

        // Main Query
        $users = User::query()
            ->with('role')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('username', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->roleFilter !== '', function ($query) {
                $query->where('role_id', (int) $this->roleFilter);
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('status', (int) $this->statusFilter);
            })
            ->when($sortField === 'role', function ($query) use ($sortDirection) {
                $query->join('roles', 'users.role_id', '=', 'roles.id')
                    ->orderBy('roles.name', $sortDirection)
                    ->select('users.*');
            }, function ($query) use ($sortField, $sortDirection) {
                $query->orderBy('users.'.$sortField, $sortDirection);
            })
            ->paginate($this->perPage);

        return view('livewire.admin.users.index', [
            'users' => $users,
            'stats' => $stats,
            'roles' => $roles,
        ]);
    }
}
