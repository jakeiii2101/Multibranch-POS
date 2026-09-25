<?php

namespace App\Support;

use App\Models\BirSetting;
use App\Models\DailyClosing;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyReadingService
{
    /** @return array<string, mixed> */
    public function snapshot(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $originalId = app(LegacyStockMirror::class)->activeBranchId();
        $scopeSales = fn ($query) => $query->whereNull('branch_id')
            ->when($originalId !== null, fn ($query) => $query->orWhere('branch_id', $originalId));
        $allSales = Sale::query()->where($scopeSales)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$start, $end]);
        $activeSales = (clone $allSales)->whereDoesntHave('adjustment');

        $totals = (clone $activeSales)->selectRaw(
            'COUNT(*) as transaction_count, COALESCE(SUM(subtotal), 0) as gross_sales, COALESCE(SUM(discount_amount), 0) as discounts, COALESCE(SUM(vat_exemption_amount), 0) as vat_exemptions, COALESCE(SUM(total), 0) as net_sales, COALESCE(SUM(vatable_sales), 0) as vatable_sales, COALESCE(SUM(vat_amount), 0) as vat_amount, COALESCE(SUM(vat_exempt_sales), 0) as vat_exempt_sales, COALESCE(SUM(zero_rated_sales), 0) as zero_rated_sales, COALESCE(SUM(non_vat_sales), 0) as non_vat_sales'
        )->first();
        $invoiceRange = (clone $allSales)->selectRaw('COUNT(*) as issued_count, MIN(COALESCE(invoice_number, sale_number)) as first_invoice, MAX(COALESCE(invoice_number, sale_number)) as last_invoice')->first();
        $payments = (clone $activeSales)
            ->leftJoin('payments', 'payments.sale_id', '=', 'sales.id')
            ->selectRaw("COALESCE(payments.method, 'cash') as method, COUNT(*) as transaction_count, COALESCE(SUM(sales.total), 0) as amount, COALESCE(SUM(payments.amount_tendered), 0) as tendered, COALESCE(SUM(payments.change_due), 0) as change_due")
            ->groupBy(DB::raw("COALESCE(payments.method, 'cash')"))
            ->get()
            ->mapWithKeys(fn ($row) => [$row->method => [
                'transaction_count' => (int) $row->transaction_count,
                'amount' => round((float) $row->amount, 2),
                'tendered' => round((float) $row->tendered, 2),
                'change_due' => round((float) $row->change_due, 2),
            ]])->all();
        $reversals = SaleAdjustment::query()->whereHas('sale', $scopeSales)
            ->whereBetween('processed_at', [$start, $end])
            ->selectRaw("COUNT(*) as count, COALESCE(SUM(amount), 0) as amount, COALESCE(SUM(CASE WHEN type = 'void' THEN amount ELSE 0 END), 0) as void_amount, COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0) as refund_amount")
            ->first();
        $partialRefunds = app(RefundReconciliation::class)->between($start, $end, $originalId, true);

        return [
            'business_date' => $date->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'seller' => BirSetting::query()->whereNull('branch_id')->where('is_active', true)->first()?->invoiceSnapshot() ?? [],
            'invoice_range' => [
                'first' => $invoiceRange->first_invoice,
                'last' => $invoiceRange->last_invoice,
                'issued_count' => (int) $invoiceRange->issued_count,
            ],
            'sales' => [
                'transaction_count' => (int) $totals->transaction_count,
                'gross_sales' => round((float) $totals->gross_sales - $partialRefunds['gross_amount'], 2),
                'discounts' => round((float) $totals->discounts - $partialRefunds['discount_amount'], 2),
                'vat_exemptions' => round((float) $totals->vat_exemptions - $partialRefunds['vat_exemption_amount'], 2),
                'net_sales' => round((float) $totals->net_sales - $partialRefunds['refund_amount'], 2),
            ],
            'tax' => [
                'vatable_sales' => round((float) $totals->vatable_sales - $partialRefunds['vatable_sales'], 2),
                'vat_amount' => round((float) $totals->vat_amount - $partialRefunds['vat_amount'], 2),
                'vat_exempt_sales' => round((float) $totals->vat_exempt_sales - $partialRefunds['vat_exempt_sales'], 2),
                'zero_rated_sales' => round((float) $totals->zero_rated_sales - $partialRefunds['zero_rated_sales'], 2),
                'non_vat_sales' => round((float) $totals->non_vat_sales - $partialRefunds['non_vat_sales'], 2),
            ],
            'payments' => $payments,
            'reversals' => [
                'count' => (int) $reversals->count + $partialRefunds['refund_count'],
                'amount' => round((float) $reversals->amount + $partialRefunds['refund_amount'], 2),
                'void_amount' => round((float) $reversals->void_amount, 2),
                'refund_amount' => round((float) $reversals->refund_amount + $partialRefunds['refund_amount'], 2),
                'partial_refund_count' => $partialRefunds['refund_count'],
                'partial_refund_amount' => round($partialRefunds['refund_amount'], 2),
            ],
        ];
    }

    public function close(Carbon $date, User $user, ?string $notes): DailyClosing
    {
        if (! $user->isAdmin() || ! $user->isActive()) {
            throw ValidationException::withMessages(['closing' => 'Only an active administrator may create a Z-reading.']);
        }

        if ($date->isFuture()) {
            throw ValidationException::withMessages(['businessDate' => 'A future business date cannot be closed.']);
        }

        return DB::transaction(function () use ($date, $user, $notes): DailyClosing {
            if (DailyClosing::query()->whereDate('business_date', $date)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['closing' => 'This business date already has a Z-reading.']);
            }

            $closing = DailyClosing::query()->create([
                'business_date' => $date->toDateString(),
                'reading_number' => 'Z-'.$date->format('Ymd'),
                'closed_by' => $user->id,
                'closed_at' => now(),
                'snapshot' => $this->snapshot($date),
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);

            Audit::record('daily_closing.created', $closing, 'Z-reading created: '.$closing->reading_number, [
                'business_date' => $closing->business_date->toDateString(),
                'net_sales' => $closing->snapshot['sales']['net_sales'],
            ]);

            return $closing;
        });
    }
}
