<?php

namespace App\Livewire\Admin\Roles;

use App\Models\Role;
use App\Models\User;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Peran - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortField = 'name';

    #[Url(history: true)]
    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = ['name', 'description', 'created_at'];
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
        $role = Role::withCount('users')->findOrFail($id);

        $this->authorize('delete', $role);

        // Core system roles cannot be deleted
        if ($role->isCore()) {
            session()->flash('error', "Role '{$role->name}' adalah role inti sistem dan tidak dapat dihapus.");

            return;
        }

        // Delete Protection: Role currently assigned to users cannot be deleted
        if ($role->users_count > 0) {
            session()->flash('error', 'Role cannot be deleted because it is currently assigned to one or more users.');

            return;
        }

        $roleName = $role->name;
        $role->delete();

        session()->flash('success', "Role \"{$roleName}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', Role::class);
        $this->search = InputBounds::search($this->search);
        $this->perPage = InputBounds::perPage($this->perPage);

        $allowedSortFields = ['name', 'description', 'created_at'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';

        // Summary Aggregations
        $stats = [
            'total_roles' => Role::count(),
            'roles_in_use' => Role::has('users')->count(),
            'total_users' => User::count(),
        ];

        // Main Query
        $roles = Role::query()
            ->withCount('users')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.roles.index', [
            'roles' => $roles,
            'stats' => $stats,
        ]);
    }
}
