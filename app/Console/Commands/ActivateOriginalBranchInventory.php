<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Support\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ActivateOriginalBranchInventory extends Command
{
    protected $signature = 'inventory:activate-original-branch
        {branchCode : Internal code of the original branch}
        {--apply : Back up, copy balances, and activate the stock mirror}
        {--confirm-code= : Repeat the branch code to authorize the cutover}';

    protected $description = 'Preview or safely activate the original branch stock mirror';

    public function handle(DatabaseBackupService $backups): int
    {
        $code = (string) $this->argument('branchCode');
        $branch = Branch::query()->where('code', $code)->first();

        if ($branch === null || ! $branch->isActive()) {
            $this->components->error('The original branch must exist and be active.');

            return self::FAILURE;
        }

        if (DB::table('original_branch_inventory')->exists()) {
            $this->components->error('Original branch inventory is already active. A second cutover is not allowed.');

            return self::FAILURE;
        }

        $products = Product::query()->count();
        $units = Product::query()->sum('stock_quantity');
        $existing = BranchProduct::query()->where('branch_id', $branch->id)->count();

        $this->table(['Measure', 'Value'], [
            ['Original branch', $branch->name.' ('.$branch->code.')'],
            ['Products to copy', $products],
            ['Legacy units to copy', $units],
            ['Existing original branch balances', $existing],
        ]);

        if ($products === 0 || $existing !== 0) {
            $this->components->error('Cutover requires products and an empty original branch inventory. No balances were changed.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->components->info('Preview only. Put the application in maintenance mode, then rerun with --apply --confirm-code='.$code.'.');

            return self::SUCCESS;
        }

        if (! app()->isDownForMaintenance() || $this->option('confirm-code') !== $code) {
            $this->components->error('Activation requires maintenance mode and --confirm-code matching the branch code exactly.');

            return self::FAILURE;
        }

        try {
            $filename = $backups->create();
            $this->components->info('Database backup created and checksummed: storage/app/private/backups/'.$filename);

            DB::transaction(function () use ($branch, $products, $units): void {
                $locked = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isActive() || DB::table('original_branch_inventory')->exists()
                    || BranchProduct::query()->where('branch_id', $branch->id)->exists()) {
                    throw new RuntimeException('Cutover state changed. No balances were activated.');
                }

                if (Product::query()->count() !== $products || Product::query()->sum('stock_quantity') != $units) {
                    throw new RuntimeException('Legacy stock changed since the preview. No balances were activated.');
                }

                Product::query()->orderBy('id')->chunkById(250, function ($batch) use ($branch): void {
                    $now = now();
                    $rows = $batch->map(fn (Product $product): array => [
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                        'on_hand' => $product->stock_quantity,
                        'reserved' => 0,
                        'reorder_level' => $product->low_stock_level,
                        'is_available' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    BranchProduct::query()->insert($rows);
                });

                $copied = BranchProduct::query()->where('branch_id', $branch->id);
                if ($copied->count() !== $products || $copied->sum('on_hand') != $units) {
                    throw new RuntimeException('Copied balances failed verification. No balances were activated.');
                }

                DB::table('original_branch_inventory')->insert([
                    'id' => 1,
                    'branch_id' => $branch->id,
                    'activated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->components->error('Activation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Original branch stock mirror activated. Run inventory:branch-audit '.$code.' --strict before bringing the application back up.');

        return self::SUCCESS;
    }
}
