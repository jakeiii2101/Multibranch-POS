<?php

namespace App\Support;

use App\Models\BirSetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleRefund;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartialRefundService
{
    /** @param array<int, int|string> $quantities */
    public function refund(Sale $sale, User $authorizer, array $quantities, string $reason, bool $restock): SaleRefund
    {
        if (! $authorizer->isAdmin() || ! $authorizer->isActive()) {
            throw ValidationException::withMessages(['partialRefund' => 'Only an active administrator may authorize a partial refund.']);
        }

        if ($sale->status !== Sale::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['partialRefund' => 'Only a completed sale may be partially refunded.']);
        }

        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'The refund reason must be at least 10 characters.']);
        }

        return DB::transaction(function () use ($sale, $authorizer, $quantities, $reason, $restock): SaleRefund {
            $lockedSale = Sale::query()->with(['items', 'refunds.items'])->lockForUpdate()->findOrFail($sale->id);
            $secondaryBranchId = app(SaleStockRestock::class)->assertOpen($lockedSale, 'partialRefund');
            if ($lockedSale->adjustment()->exists()) {
                throw ValidationException::withMessages(['partialRefund' => 'A voided or fully refunded sale cannot receive a partial refund.']);
            }

            $selected = [];
            $totals = array_fill_keys([
                'gross_amount', 'discount_amount', 'vat_exemption_amount', 'vatable_sales',
                'vat_amount', 'vat_exempt_sales', 'zero_rated_sales', 'non_vat_sales', 'refund_amount',
            ], 0.0);
            foreach ($lockedSale->items as $item) {
                $quantity = (int) ($quantities[$item->id] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $already = (int) $lockedSale->refunds
                    ->flatMap(fn (SaleRefund $refund) => $refund->items)
                    ->where('sale_item_id', $item->id)
                    ->sum('quantity');
                $remaining = $item->quantity - $already;
                if ($quantity > $remaining) {
                    throw ValidationException::withMessages(["quantities.{$item->id}" => "Only {$remaining} unit(s) remain refundable."]);
                }

                $values = $this->proportionalValues($item, $quantity, $remaining, $lockedSale);
                $selected[] = ['item' => $item, 'quantity' => $quantity] + $values;
                foreach (array_keys($totals) as $key) {
                    $totals[$key] = round($totals[$key] + ($values[$key] ?? 0), 2);
                }
            }

            if ($selected === []) {
                throw ValidationException::withMessages(['partialRefund' => 'Select at least one item quantity to refund.']);
            }

            $refund = SaleRefund::query()->create($totals + [
                'refund_number' => 'RF-'.Str::ulid(),
                'sale_id' => $lockedSale->id,
                'authorized_by' => $authorizer->id,
                'reason' => trim($reason),
                'inventory_restocked' => $restock,
                'processed_at' => now(),
            ]);

            foreach ($selected as $line) {
                /** @var SaleItem $item */
                $item = $line['item'];
                $refund->items()->create([
                    'sale_item_id' => $item->id,
                    'quantity' => $line['quantity'],
                    'gross_amount' => $line['gross_amount'],
                    'discount_amount' => $line['discount_amount'],
                    'vat_exemption_amount' => $line['vat_exemption_amount'],
                    'vat_amount' => $line['vat_amount'],
                    'refund_amount' => $line['refund_amount'],
                ]);

                if ($restock && $item->product_id !== null
                    && ($stock = app(SaleStockRestock::class)->restock($secondaryBranchId, $item->product_id, $line['quantity'])) !== null) {
                    StockMovement::query()->create([
                        'product_id' => $stock['product_id'],
                        'branch_id' => $stock['branch_id'],
                        'user_id' => $authorizer->id,
                        'type' => StockMovement::TYPE_REFUND,
                        'quantity' => $line['quantity'],
                        'stock_before' => $stock['before'],
                        'stock_after' => $stock['after'],
                        'reference' => $refund->refund_number,
                        'reason' => 'Partial refund: '.trim($reason),
                    ]);
                }
            }

            Audit::record('sale.partial_refund', $lockedSale, 'Partial refund recorded: '.$refund->refund_number, [
                'refund_id' => $refund->id,
                'refund_amount' => (string) $refund->refund_amount,
                'reason' => $refund->reason,
                'inventory_restocked' => $refund->inventory_restocked,
            ]);

            return $refund;
        });
    }

    /** @return array<string, float> */
    private function proportionalValues(SaleItem $item, int $quantity, int $remaining, Sale $sale): array
    {
        $prior = $sale->refunds
            ->flatMap(fn (SaleRefund $refund) => $refund->items)
            ->where('sale_item_id', $item->id);
        $value = fn (string $field): float => $quantity === $remaining
            ? round((float) $item->{$field} - (float) $prior->sum($field), 2)
            : round((float) $item->{$field} / $item->quantity * $quantity, 2);
        $gross = $value('line_total');
        $discount = $value('discount_amount');
        $vatExemption = $value('vat_exemption_amount');
        $vat = $value('vat_amount');
        $net = $value('net_total');
        if ($net <= 0 && (float) $item->net_total <= 0) {
            $net = round($gross - $discount - $vatExemption, 2);
        }

        $tax = ['vatable_sales' => 0.0, 'vat_amount' => $vat, 'vat_exempt_sales' => 0.0, 'zero_rated_sales' => 0.0, 'non_vat_sales' => 0.0];
        if ($sale->tax_type === BirSetting::TAX_TYPE_NON_VAT) {
            $tax['non_vat_sales'] = $net;
        } elseif ($vatExemption > 0 || $item->tax_type === Product::TAX_VAT_EXEMPT) {
            $tax['vat_exempt_sales'] = $net;
        } elseif ($item->tax_type === Product::TAX_ZERO_RATED) {
            $tax['zero_rated_sales'] = $net;
        } else {
            $tax['vatable_sales'] = round($net - $vat, 2);
        }

        return [
            'gross_amount' => $gross,
            'discount_amount' => $discount,
            'vat_exemption_amount' => $vatExemption,
            'refund_amount' => $net,
        ] + $tax;
    }
}
