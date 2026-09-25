<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\BranchDailyClosing;
use App\Models\BranchProduct;
use App\Models\DailyClosing;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;
use LogicException;

class SaleStockRestock
{
    /** Return the secondary branch ID, or null for a legacy/original branch sale. Must run in a transaction. */
    public function assertOpen(Sale $sale, string $errorKey): ?int
    {
        $originalId = app(LegacyStockMirror::class)->activeBranchId();
        if ($sale->branch_id === null || $sale->branch_id === $originalId) {
            if (DailyClosing::query()->whereDate('business_date', now())->exists()) {
                throw ValidationException::withMessages([$errorKey => 'Today already has a Z-reading. Process returns on the next open business date.']);
            }

            return null;
        }

        if ($originalId === null) {
            throw new LogicException('A branch sale has no active original branch mapping.');
        }

        $branch = Branch::query()->whereKey($sale->branch_id)->lockForUpdate()->firstOrFail();
        if (BranchDailyClosing::query()->where('branch_id', $branch->id)
            ->whereDate('business_date', now())->exists()) {
            throw ValidationException::withMessages([$errorKey => 'This branch already has a Z-reading today. Process returns on the next open business date.']);
        }

        return $branch->id;
    }

    /** @return array{product_id:int,branch_id:?int,before:int,after:int}|null */
    public function restock(?int $secondaryBranchId, int $productId, int $quantity): ?array
    {
        if ($secondaryBranchId !== null) {
            $product = Product::query()->find($productId);
            if ($product === null) {
                return null;
            }

            $balance = BranchProduct::query()->where('branch_id', $secondaryBranchId)
                ->where('product_id', $productId)->lockForUpdate()->first();
            if ($balance === null || $balance->on_hand + $quantity > 2147483647) {
                throw new LogicException('The sale branch balance is missing or cannot accept the returned stock.');
            }

            $before = $balance->on_hand;
            $after = $before + $quantity;
            $balance->update(['on_hand' => $after]);

            return ['product_id' => $productId, 'branch_id' => $secondaryBranchId, 'before' => $before, 'after' => $after];
        }

        $product = Product::query()->lockForUpdate()->find($productId);
        if ($product === null) {
            return null;
        }

        $before = $product->stock_quantity;
        $after = $before + $quantity;
        $product->update(['stock_quantity' => $after]);
        $branchId = app(LegacyStockMirror::class)->sync($product, $before);

        return ['product_id' => $productId, 'branch_id' => $branchId, 'before' => $before, 'after' => $after];
    }
}
