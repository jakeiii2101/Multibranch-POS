<?php

namespace App\Support;

use App\Models\SaleRefund;
use Illuminate\Support\Carbon;

class RefundReconciliation
{
    /** @return array<string, float|int> */
    public function between(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        $totals = SaleRefund::query()
            ->whereBetween('processed_at', [$start, $end])
            ->when($branchId !== null, fn ($query) => $query->whereHas('sale', fn ($sales) => $sales->where('branch_id', $branchId)))
            ->selectRaw(
                'COUNT(*) as refund_count, COALESCE(SUM(gross_amount), 0) as gross_amount, '
                .'COALESCE(SUM(discount_amount), 0) as discount_amount, COALESCE(SUM(vat_exemption_amount), 0) as vat_exemption_amount, '
                .'COALESCE(SUM(vatable_sales), 0) as vatable_sales, COALESCE(SUM(vat_amount), 0) as vat_amount, '
                .'COALESCE(SUM(vat_exempt_sales), 0) as vat_exempt_sales, COALESCE(SUM(zero_rated_sales), 0) as zero_rated_sales, '
                .'COALESCE(SUM(non_vat_sales), 0) as non_vat_sales, COALESCE(SUM(refund_amount), 0) as refund_amount'
            )->first();

        return [
            'refund_count' => (int) $totals->refund_count,
            'gross_amount' => (float) $totals->gross_amount,
            'discount_amount' => (float) $totals->discount_amount,
            'vat_exemption_amount' => (float) $totals->vat_exemption_amount,
            'vatable_sales' => (float) $totals->vatable_sales,
            'vat_amount' => (float) $totals->vat_amount,
            'vat_exempt_sales' => (float) $totals->vat_exempt_sales,
            'zero_rated_sales' => (float) $totals->zero_rated_sales,
            'non_vat_sales' => (float) $totals->non_vat_sales,
            'refund_amount' => (float) $totals->refund_amount,
        ];
    }
}
