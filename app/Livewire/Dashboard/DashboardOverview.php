<?php

namespace App\Livewire\Dashboard;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleRefundItem;
use App\Models\User;
use App\Support\RefundReconciliation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DashboardOverview extends Component
{
    public function boot(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->isActive()
                && (auth()->user()->isAdmin() || auth()->user()->isCashier()),
            403,
        );

        abort_if(auth()->user()->role === User::ROLE_CASHIER
            && DB::table('original_branch_inventory')->exists(), 403);
    }

    public function render()
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todayQuery = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDoesntHave('adjustment')
            ->whereBetween('completed_at', [$todayStart, $todayEnd]);

        $today = (clone $todayQuery)
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(subtotal), 0) as gross_sales, COALESCE(SUM(discount_amount), 0) as discounts, COALESCE(SUM(total), 0) as net_sales')
            ->first();
        $refundsToday = app(RefundReconciliation::class)->between($todayStart, $todayEnd);

        $itemsSoldToday = (int) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->leftJoin('sale_adjustments', 'sale_adjustments.sale_id', '=', 'sales.id')
            ->whereNull('sale_adjustments.id')
            ->whereBetween('sales.completed_at', [$todayStart, $todayEnd])
            ->sum('sale_items.quantity');
        $itemsReturnedToday = (int) SaleRefundItem::query()
            ->whereHas('refund', fn ($query) => $query->whereBetween('processed_at', [$todayStart, $todayEnd]))
            ->sum('quantity');

        $lowStockCount = Product::query()
            ->whereColumn('stock_quantity', '<=', 'low_stock_level')
            ->count();

        $recentSales = Sale::query()
            ->with(['user', 'payment', 'adjustment', 'refunds'])
            ->where('status', Sale::STATUS_COMPLETED)
            ->latest('completed_at')
            ->limit(6)
            ->get();

        $topProductsToday = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->leftJoin('sale_adjustments', 'sale_adjustments.sale_id', '=', 'sales.id')
            ->whereNull('sale_adjustments.id')
            ->whereBetween('sales.completed_at', [$todayStart, $todayEnd])
            ->select(
                'sale_items.product_name',
                'sale_items.sku',
                DB::raw('SUM(sale_items.quantity) as quantity_sold'),
            )
            ->groupBy('sale_items.product_name', 'sale_items.sku')
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get();

        return view('livewire.dashboard.dashboard-overview', [
            'salesToday' => (float) ($today->net_sales ?? 0) - $refundsToday['refund_amount'],
            'grossSalesToday' => (float) ($today->gross_sales ?? 0) - $refundsToday['gross_amount'],
            'discountsToday' => (float) ($today->discounts ?? 0) - $refundsToday['discount_amount'],
            'transactionsToday' => (int) ($today->transactions ?? 0),
            'itemsSoldToday' => $itemsSoldToday - $itemsReturnedToday,
            'lowStockCount' => $lowStockCount,
            'recentSales' => $recentSales,
            'topProductsToday' => $topProductsToday,
        ]);
    }
}
