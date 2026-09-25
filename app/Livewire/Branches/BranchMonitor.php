<?php

namespace App\Livewire\Branches;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BranchMonitor extends Component
{
    public string $branchId = '';

    public function boot(): void
    {
        abort_unless(auth()->check() && auth()->user()->isActive()
            && in_array(auth()->user()->role, [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_SUPERVISOR], true), 403);
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        $branches = Branch::query()->where('status', Branch::STATUS_ACTIVE)
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas('users', fn ($members) => $members
                ->whereKey($user->id)
                ->where('branch_user.status', Branch::STATUS_ACTIVE)))
            ->orderBy('name')->get(['id', 'code', 'name']);

        $choices = $branches;

        if ($this->branchId !== '') {
            abort_unless(ctype_digit($this->branchId) && $branches->contains('id', (int) $this->branchId), 404);
            $branches = $branches->where('id', (int) $this->branchId);
        }

        $ids = $branches->pluck('id')->all();
        $sales = Sale::query()->select('branch_id')
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(total), 0) as sales_total')
            ->whereIn('branch_id', $ids)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDoesntHave('adjustment')
            ->whereBetween('completed_at', [now()->startOfDay(), now()->endOfDay()])
            ->groupBy('branch_id')->get()->keyBy('branch_id');

        $inventory = DB::table('branch_products')->select('branch_id')
            ->selectRaw('COALESCE(SUM(on_hand), 0) as units, COUNT(*) as products, COALESCE(SUM(CASE WHEN on_hand <= reorder_level THEN 1 ELSE 0 END), 0) as low_stock')
            ->whereIn('branch_id', $ids)->groupBy('branch_id')->get()->keyBy('branch_id');

        return view('livewire.branches.branch-monitor', compact('branches', 'choices', 'sales', 'inventory'));
    }
}
