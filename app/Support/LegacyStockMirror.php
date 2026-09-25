<?php

namespace App\Support;

use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use LogicException;

class LegacyStockMirror
{
    public function sync(Product $product, ?int $previousStock): ?int
    {
        $branchId = $this->activeBranchId();
        if ($branchId === null) {
            return null;
        }

        $this->requireTransaction();
        $balance = BranchProduct::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($previousStock === null) {
            if ($balance !== null) {
                throw new LogicException('A newly created product already has an original branch balance.');
            }

            BranchProduct::query()->create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'on_hand' => $product->stock_quantity,
                'reserved' => 0,
                'reorder_level' => $product->low_stock_level,
                'is_available' => true,
            ]);

            return $branchId;
        }

        if ($balance === null || $balance->on_hand !== $previousStock) {
            throw new LogicException('Original branch stock differs from legacy stock. Stop writes and reconcile before continuing.');
        }

        $balance->update([
            'on_hand' => $product->stock_quantity,
            'reorder_level' => $product->low_stock_level,
        ]);

        return $branchId;
    }

    public function remove(Product $product): void
    {
        $branchId = $this->activeBranchId();
        if ($branchId === null) {
            return;
        }

        $this->requireTransaction();
        $balance = BranchProduct::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($balance === null || $balance->on_hand !== $product->stock_quantity) {
            throw new LogicException('Original branch stock differs from legacy stock. Stop writes and reconcile before continuing.');
        }

        $balance->delete();
    }

    public function activeBranchId(): ?int
    {
        $branchId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');

        return $branchId === null ? null : (int) $branchId;
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Original branch stock writes require a database transaction.');
        }
    }
}
