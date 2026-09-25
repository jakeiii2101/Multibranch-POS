<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;

class BranchInventoryAudit extends Command
{
    protected $signature = 'inventory:branch-audit {branchCode : Internal code of the original branch} {--strict : Fail if any product balance is missing or differs from legacy stock}';

    protected $description = 'Compare original single-store product stock with one branch without changing data';

    public function handle(): int
    {
        $branch = Branch::query()->where('code', $this->argument('branchCode'))->first();

        if ($branch === null) {
            $this->components->error('Branch code not found. Create and verify the original branch first.');

            return self::FAILURE;
        }

        $summary = Product::query()
            ->leftJoin('branch_products as branch_balance', function (JoinClause $join) use ($branch): void {
                $join->on('branch_balance.product_id', '=', 'products.id')
                    ->where('branch_balance.branch_id', '=', $branch->id);
            })
            ->selectRaw('COUNT(products.id) as product_count')
            ->selectRaw('COALESCE(SUM(products.stock_quantity), 0) as legacy_units')
            ->selectRaw('COALESCE(SUM(branch_balance.on_hand), 0) as branch_units')
            ->selectRaw('COALESCE(SUM(CASE WHEN branch_balance.id IS NULL THEN 1 ELSE 0 END), 0) as missing_balances')
            ->selectRaw('COALESCE(SUM(CASE WHEN branch_balance.id IS NOT NULL AND branch_balance.on_hand != products.stock_quantity THEN 1 ELSE 0 END), 0) as different_balances')
            ->firstOrFail();

        $unassignedMovements = StockMovement::query()->whereNull('branch_id')->count();

        $this->components->info('Read-only branch inventory comparison for '.$branch->name.' ('.$branch->code.')');
        $this->table(['Measure', 'Count'], [
            ['Products', $summary->product_count],
            ['Legacy product units', $summary->legacy_units],
            ['Branch balance units', $summary->branch_units],
            ['Products without branch balance', $summary->missing_balances],
            ['Products with different balances', $summary->different_balances],
            ['Historical movements without branch', $unassignedMovements],
        ]);
        $this->warn('This is a snapshot. It does not backfill stock, attribute historical movements, or make legacy checkout branch aware.');

        $ready = (int) $summary->product_count > 0
            && (int) $summary->missing_balances === 0
            && (int) $summary->different_balances === 0;

        return $this->option('strict') && ! $ready ? self::FAILURE : self::SUCCESS;
    }
}
