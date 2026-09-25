<?php

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Audit;
use App\Support\LegacyStockMirror;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class InventoryList extends Component
{
    public ?int $productId = null;

    public string $type = StockMovement::TYPE_STOCK_IN;

    public int $quantity = 1;

    public string $reference = '';

    public string $reason = '';

    public bool $showForm = false;

    public function boot(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->isActive() && auth()->user()->isAdmin(),
            403,
        );
    }

    public function createMovement(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'productId' => ['required', 'integer', Rule::exists('products', 'id')],
            'type' => ['required', Rule::in([
                StockMovement::TYPE_STOCK_IN,
                StockMovement::TYPE_STOCK_OUT,
                StockMovement::TYPE_ADJUSTMENT,
            ])],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($validated['type'] !== StockMovement::TYPE_ADJUSTMENT && $validated['quantity'] < 1) {
            $this->addError('quantity', 'Quantity must be at least 1 for Stock In and Stock Out.');
            return;
        }

        DB::transaction(function () use ($validated): void {
            $product = Product::query()->lockForUpdate()->findOrFail($validated['productId']);
            $before = $product->stock_quantity;

            $change = match ($validated['type']) {
                StockMovement::TYPE_STOCK_IN => abs($validated['quantity']),
                StockMovement::TYPE_STOCK_OUT => -abs($validated['quantity']),
                StockMovement::TYPE_ADJUSTMENT => $validated['quantity'],
            };

            $after = $before + $change;

            if ($after < 0) {
                $this->addError('quantity', 'This movement would make stock negative.');
                return;
            }

            $product->update(['stock_quantity' => $after]);
            $branchId = app(LegacyStockMirror::class)->sync($product, $before);

            $movement = StockMovement::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'user_id' => auth()->id(),
                'type' => $validated['type'],
                'quantity' => $change,
                'stock_before' => $before,
                'stock_after' => $after,
                'reference' => filled($validated['reference']) ? trim($validated['reference']) : null,
                'reason' => trim($validated['reason']),
            ]);

            Audit::record(
                'inventory.movement',
                $movement,
                'Manual inventory movement recorded for '.$product->name,
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'movement_type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                ],
            );
        });

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        session()->flash('success', 'Stock movement recorded successfully.');
        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->productId = null;
        $this->type = StockMovement::TYPE_STOCK_IN;
        $this->quantity = 1;
        $this->reference = '';
        $this->reason = '';
        $this->showForm = false;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.inventory.inventory-list', [
            'products' => Product::query()->orderBy('name')->get(),
            'movements' => StockMovement::query()
                ->with(['product', 'user'])
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }
}
