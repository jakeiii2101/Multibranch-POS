<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReversalService
{
    public function reverse(Sale $sale, User $authorizer, string $type, string $reason, bool $restock): SaleAdjustment
    {
        if (! $authorizer->isAdmin() || ! $authorizer->isActive()) {
            throw ValidationException::withMessages(['reversal' => 'Only an administrator may authorize this action.']);
        }

        if ($sale->status !== Sale::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['reversal' => 'Only a completed sale may be voided or refunded.']);
        }

        if (! in_array($type, [SaleAdjustment::TYPE_VOID, SaleAdjustment::TYPE_REFUND], true)) {
            throw ValidationException::withMessages(['reversalType' => 'Invalid reversal type.']);
        }

        if ($type === SaleAdjustment::TYPE_VOID && ! $sale->completed_at->isToday()) {
            throw ValidationException::withMessages(['reversalType' => 'A void is limited to the original business date. Use a refund instead.']);
        }

        return DB::transaction(function () use ($sale, $authorizer, $type, $reason, $restock): SaleAdjustment {
            $lockedSale = Sale::query()->with('items')->lockForUpdate()->findOrFail($sale->id);
            $secondaryBranchId = app(SaleStockRestock::class)->assertOpen($lockedSale, 'reversal');

            if ($lockedSale->adjustment()->exists()) {
                throw ValidationException::withMessages(['reversal' => 'This sale has already been voided or refunded.']);
            }

            if ($lockedSale->refunds()->exists()) {
                throw ValidationException::withMessages(['reversal' => 'A sale with partial refunds cannot be voided or fully refunded.']);
            }

            $shouldRestock = $type === SaleAdjustment::TYPE_VOID || $restock;
            $adjustment = SaleAdjustment::query()->create([
                'sale_id' => $lockedSale->id,
                'authorized_by' => $authorizer->id,
                'type' => $type,
                'amount' => $lockedSale->total,
                'reason' => trim($reason),
                'inventory_restocked' => $shouldRestock,
                'processed_at' => now(),
            ]);

            if ($shouldRestock) {
                foreach ($lockedSale->items as $item) {
                    if ($item->product_id === null) {
                        continue;
                    }

                    $stock = app(SaleStockRestock::class)->restock($secondaryBranchId, $item->product_id, $item->quantity);
                    if ($stock === null) {
                        continue;
                    }

                    StockMovement::query()->create([
                        'product_id' => $stock['product_id'],
                        'branch_id' => $stock['branch_id'],
                        'user_id' => $authorizer->id,
                        'type' => $type === SaleAdjustment::TYPE_VOID ? StockMovement::TYPE_VOID : StockMovement::TYPE_REFUND,
                        'quantity' => $item->quantity,
                        'stock_before' => $stock['before'],
                        'stock_after' => $stock['after'],
                        'reference' => $lockedSale->invoice_number ?? $lockedSale->sale_number,
                        'reason' => ucfirst($type).': '.trim($reason),
                    ]);
                }
            }

            Audit::record('sale.'.$type, $lockedSale, 'Sale '.ucfirst($type).': '.$lockedSale->sale_number, [
                'adjustment_id' => $adjustment->id,
                'amount' => (string) $adjustment->amount,
                'reason' => $adjustment->reason,
                'inventory_restocked' => $adjustment->inventory_restocked,
                'authorized_by' => $authorizer->id,
            ]);

            return $adjustment;
        });
    }
}
