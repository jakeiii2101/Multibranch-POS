<?php

namespace App\Livewire\Branches;

use App\Models\Branch;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BranchManagement extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public string $address = '';
    public string $status = Branch::STATUS_ACTIVE;
    public bool $showForm = false;
    public ?int $userId = null;
    public ?int $branchId = null;

    public function boot(): void
    {
        abort_unless(auth()->check() && auth()->user()->isActive() && auth()->user()->isAdmin(), 403);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->editingId = $branch->id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->address = $branch->address ?? '';
        $this->status = $branch->status;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', Rule::unique('branches', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Branch::STATUS_ACTIVE, Branch::STATUS_INACTIVE])],
        ]);

        $branch = $this->editingId === null
            ? Branch::query()->create($validated)
            : Branch::query()->findOrFail($this->editingId);

        if ($this->editingId !== null) {
            $branch->update($validated);
        }

        Audit::record($this->editingId === null ? 'branch.created' : 'branch.updated', $branch, 'Branch saved: '.$branch->name, [
            'code' => $branch->code,
            'status' => $branch->status,
        ]);

        $this->resetForm();
        session()->flash('success', 'Branch saved.');
    }

    public function assign(): void
    {
        $validated = $this->validate([
            'userId' => ['required', 'integer', Rule::exists('users', 'id')],
            'branchId' => ['required', 'integer', Rule::exists('branches', 'id')],
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::query()->lockForUpdate()->findOrFail($validated['userId']);
            $branch = Branch::query()->findOrFail($validated['branchId']);

            if (! $user->isActive() || ! in_array($user->role, [User::ROLE_MANAGER, User::ROLE_SUPERVISOR, User::ROLE_CASHIER], true)) {
                $this->addError('userId', 'Select an active manager, supervisor, or cashier.');
                return;
            }

            if (! $branch->isActive()) {
                $this->addError('branchId', 'Activate the branch before assigning staff.');
                return;
            }

            if ($user->role !== User::ROLE_MANAGER && $user->branches()
                ->where('branches.id', '!=', $branch->id)
                ->wherePivot('status', Branch::STATUS_ACTIVE)
                ->exists()) {
                $this->addError('userId', 'Supervisors and cashiers may have only one active branch.');
                return;
            }

            if ($user->branches()->whereKey($branch->id)->exists()) {
                $user->branches()->updateExistingPivot($branch->id, ['status' => Branch::STATUS_ACTIVE]);
            } else {
                $user->branches()->attach($branch->id, ['status' => Branch::STATUS_ACTIVE]);
            }

            Audit::record('branch.user_assigned', $branch, 'Assigned '.$user->name.' to '.$branch->name, ['assigned_user_id' => $user->id]);
        });

        if ($this->getErrorBag()->isEmpty()) {
            $this->userId = null;
            $this->branchId = null;
            session()->flash('success', 'Branch assignment saved.');
        }
    }

    public function revoke(int $userId, int $branchId): void
    {
        DB::transaction(function () use ($userId, $branchId): void {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $branch = Branch::query()->findOrFail($branchId);
            abort_unless($user->branches()->whereKey($branchId)->wherePivot('status', Branch::STATUS_ACTIVE)->exists(), 404);

            $user->branches()->updateExistingPivot($branchId, ['status' => Branch::STATUS_INACTIVE]);
            Audit::record('branch.user_revoked', $branch, 'Revoked '.$user->name.' from '.$branch->name, ['assigned_user_id' => $user->id]);
        });

        session()->flash('success', 'Assignment revoked.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->address = '';
        $this->status = Branch::STATUS_ACTIVE;
        $this->showForm = false;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.branches.branch-management', [
            'branches' => Branch::query()->with(['users' => fn ($query) => $query->wherePivot('status', Branch::STATUS_ACTIVE)])->orderBy('name')->get(),
            'staff' => User::query()->where('status', User::STATUS_ACTIVE)
                ->whereIn('role', [User::ROLE_MANAGER, User::ROLE_SUPERVISOR, User::ROLE_CASHIER])
                ->orderBy('name')->get(),
        ]);
    }
}
