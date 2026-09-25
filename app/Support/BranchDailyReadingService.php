<?php

namespace App\Support;

use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\BranchDailyClosing;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchDailyReadingService
{
    /** @return array<string, mixed> */
    public function snapshot(Branch $branch, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $allSales = Sale::query()->where('branch_id', $branch->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$start, $end]);
        $activeSales = (clone $allSales)->whereDoesntHave('adjustment');

        $totals = (clone $activeSales)->selectRaw(
            'COUNT(*) as transaction_count, COALESCE(SUM(subtotal), 0) as gross_sales, '
            .'COALESCE(SUM(discount_amount), 0) as discounts, COALESCE(SUM(vat_exemption_amount), 0) as vat_exemptions, '
            .'COALESCE(SUM(total), 0) as net_sales, COALESCE(SUM(vatable_sales), 0) as vatable_sales, '
            .'COALESCE(SUM(vat_amount), 0) as vat_amount, COALESCE(SUM(vat_exempt_sales), 0) as vat_exempt_sales, '
            .'COALESCE(SUM(zero_rated_sales), 0) as zero_rated_sales, COALESCE(SUM(non_vat_sales), 0) as non_vat_sales'
        )->first();
        $invoiceRange = (clone $allSales)->selectRaw(
            'COUNT(*) as issued_count, MIN(COALESCE(invoice_number, sale_number)) as first_invoice, '
            .'MAX(COALESCE(invoice_number, sale_number)) as last_invoice'
        )->first();
        $payments = (clone $activeSales)->leftJoin('payments', 'payments.sale_id', '=', 'sales.id')
            ->selectRaw("COALESCE(payments.method, 'cash') as method, COUNT(*) as transaction_count, "
                .'COALESCE(SUM(sales.total), 0) as amount')
            ->groupBy(DB::raw("COALESCE(payments.method, 'cash')"))->get()
            ->mapWithKeys(fn ($row) => [$row->method => [
                'transaction_count' => (int) $row->transaction_count,
                'amount' => round((float) $row->amount, 2),
            ]])->all();
        $refunds = app(RefundReconciliation::class)->between($start, $end, $branch->id);
        $reversals = SaleAdjustment::query()->whereHas('sale', fn ($sales) => $sales->where('branch_id', $branch->id))
            ->whereBetween('processed_at', [$start, $end])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')->first();

        return [
            'branch_id' => $branch->id,
            'business_date' => $date->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'seller' => BirSetting::query()->where('branch_id', $branch->id)
                ->where('is_active', true)->first()?->invoiceSnapshot() ?? [],
            'invoice_range' => [
                'first' => $invoiceRange->first_invoice,
                'last' => $invoiceRange->last_invoice,
                'issued_count' => (int) $invoiceRange->issued_count,
            ],
            'sales' => [
                'transaction_count' => (int) $totals->transaction_count,
                'gross_sales' => round((float) $totals->gross_sales - $refunds['gross_amount'], 2),
                'discounts' => round((float) $totals->discounts - $refunds['discount_amount'], 2),
                'net_sales' => round((float) $totals->net_sales - $refunds['refund_amount'], 2),
                'vat_amount' => round((float) $totals->vat_amount - $refunds['vat_amount'], 2),
            ],
            'payments' => $payments,
            'reversals' => [
                'count' => (int) $reversals->count + $refunds['refund_count'],
                'amount' => round((float) $reversals->amount + $refunds['refund_amount'], 2),
                'partial_refund_count' => $refunds['refund_count'],
            ],
        ];
    }

    public function close(Branch $branch, Carbon $date, User $user, ?string $notes): BranchDailyClosing
    {
        if (! $user->isAdmin() || ! $user->isActive()) {
            throw ValidationException::withMessages(['closing' => 'Only an active administrator may close a branch day.']);
        }

        if ($date->isFuture()) {
            throw ValidationException::withMessages(['businessDate' => 'A future business date cannot be closed.']);
        }

        return DB::transaction(function () use ($branch, $date, $user, $notes): BranchDailyClosing {
            $locked = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $originalId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');
            if (! $locked->isActive() || $originalId === null || $locked->id === (int) $originalId) {
                throw ValidationException::withMessages(['branchId' => 'Select an active additional branch.']);
            }

            if (! BirSetting::query()->where('branch_id', $locked->id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['closing' => 'Activate invoice settings for this branch first.']);
            }

            if (BranchDailyClosing::query()->where('branch_id', $locked->id)
                ->whereDate('business_date', $date)->exists()) {
                throw ValidationException::withMessages(['closing' => 'This branch business date is already closed.']);
            }

            $closing = BranchDailyClosing::query()->create([
                'branch_id' => $locked->id,
                'business_date' => $date->toDateString(),
                'reading_number' => 'Z-'.$locked->code.'-'.$date->format('Ymd'),
                'closed_by' => $user->id,
                'closed_at' => now(),
                'snapshot' => $this->snapshot($locked, $date),
                'notes' => filled($notes) ? trim($notes) : null,
            ]);

            Audit::record('branch.daily_closing_created', $closing, 'Branch Z-reading created: '.$closing->reading_number, [
                'branch_id' => $locked->id,
                'business_date' => $date->toDateString(),
                'net_sales' => $closing->snapshot['sales']['net_sales'],
            ]);

            return $closing;
        });
    }
}
