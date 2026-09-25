<?php

namespace App\Livewire\Inventory;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BranchInventory extends Component
{
    public string $branchId = '';
    public ?int $productId = null;
    public string $type = StockMovement::TYPE_STOCK_IN;
    public int $quantity = 1;
    public string $reason = '';

    public function boot(): void
    {
        abort_unless(auth()->check() && auth()->user()->isActive()
            && in_array(auth()->user()->role, [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_SUPERVISOR], true), 403);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'branchId' => ['required', 'integer', Rule::exists('branches', 'id')],
            'productId' => ['required', 'integer', Rule::exists('products', 'id')],
            'type' => ['required', Rule::in([StockMovement::TYPE_STOCK_IN, StockMovement::TYPE_STOCK_OUT])],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated): void {
            $branch = Branch::query()->whereKey($validated['branchId'])->lockForUpdate()->firstOrFail();
            abort_unless(auth()->user()->canAccessBranch($branch), 404);

            $originalId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');
            if ($originalId === null || (int) $originalId === $branch->id) {
                $this->addError('branchId', 'Activate the original branch first and use legacy Inventory for its stock.');
                return;
            }

            $product = Product::query()->findOrFail($validated['productId']);
            $balance = BranchProduct::query()->where('branch_id', $branch->id)
                ->where('product_id', $product->id)->lockForUpdate()->first();
            $before = $balance?->on_hand ?? 0;
            $change = $validated['type'] === StockMovement::TYPE_STOCK_IN
                ? $validated['quantity'] : -$validated['quantity'];
            $after = $before + $change;

            if ($after < 0 || $after > 2147483647) {
                $this->addError('quantity', 'This movement would put branch stock outside the allowed range.');
                return;
            }

            if ($balance === null) {
                $balance = BranchProduct::query()->create([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'on_hand' => $after,
                    'reserved' => 0,
                    'reorder_level' => $product->low_stock_level,
                    'is_available' => true,
                ]);
            } else {
                $balance->update(['on_hand' => $after]);
            }

            $movement = StockMovement::query()->create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => $validated['type'],
                'quantity' => $change,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => trim($validated['reason']),
            ]);

            Audit::record('branch.inventory_movement', $movement, 'Branch inventory updated: '.$branch->name, [
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'quantity' => $change,
                'stock_before' => $before,
                'stock_after' => $after,
            ]);
        });

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->productId = null;
        $this->quantity = 1;
        $this->reason = '';
        session()->flash('success', 'Branch stock movement recorded.');
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();
        $originalId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');
        $branches = Branch::query()->where('status', Branch::STATUS_ACTIVE)
            ->when($originalId !== null, fn ($query) => $query->where('id', '!=', $originalId))
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas('users', fn ($members) => $members
                ->whereKey($user->id)->where('branch_user.status', Branch::STATUS_ACTIVE)))
            ->orderBy('name')->get(['id', 'name', 'code']);

        if ($this->branchId !== '') {
            abort_unless(ctype_digit($this->branchId) && $branches->contains('id', (int) $this->branchId), 404);
        }

        $balances = $this->branchId === '' ? collect() : BranchProduct::query()
            ->with('product:id,name,sku')->where('branch_id', (int) $this->branchId)
            ->orderBy('product_id')->get();

        return view('livewire.inventory.branch-inventory', [
            'branches' => $branches,
            'balances' => $balances,
            'ready' => $originalId !== null,
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }
}
