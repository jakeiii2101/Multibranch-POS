<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Support\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OriginalBranchCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_copy_stock_or_activate_mirror(): void
    {
        $branch = Branch::factory()->create(['code' => 'ORIGINAL']);
        Product::factory()->create(['stock_quantity' => 7]);

        $this->assertSame(0, Artisan::call('inventory:activate-original-branch', ['branchCode' => $branch->code]));
        $this->assertDatabaseCount('branch_products', 0);
        $this->assertDatabaseCount('original_branch_inventory', 0);
    }

    public function test_apply_requires_maintenance_and_exact_confirmation_before_backup(): void
    {
        $branch = Branch::factory()->create(['code' => 'ORIGINAL']);
        Product::factory()->create();
        $backups = $this->mock(DatabaseBackupService::class);
        $backups->shouldNotReceive('create');

        $this->assertSame(1, Artisan::call('inventory:activate-original-branch', [
            'branchCode' => $branch->code, '--apply' => true, '--confirm-code' => $branch->code,
        ]));
        $this->assertDatabaseCount('original_branch_inventory', 0);
    }

    public function test_apply_copies_stock_once_and_keeps_other_branch_balances(): void
    {
        $original = Branch::factory()->create(['code' => 'ORIGINAL']);
        $other = Branch::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 9, 'low_stock_level' => 3]);
        BranchProduct::factory()->for($other)->for($product)->create(['on_hand' => 42]);
        $this->mock(DatabaseBackupService::class)->shouldReceive('create')->once()->andReturn('test.sqlite');

        Artisan::call('down');
        try {
            $result = Artisan::call('inventory:activate-original-branch', [
                'branchCode' => $original->code, '--apply' => true, '--confirm-code' => $original->code,
            ]);
        } finally {
            Artisan::call('up');
        }

        $this->assertSame(0, $result, Artisan::output());
        $this->assertDatabaseHas('branch_products', [
            'branch_id' => $original->id, 'product_id' => $product->id,
            'on_hand' => 9, 'reserved' => 0, 'reorder_level' => 3,
        ]);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $other->id, 'on_hand' => 42]);
        $this->assertDatabaseHas('original_branch_inventory', ['id' => 1, 'branch_id' => $original->id]);
        $this->assertSame(1, Artisan::call('inventory:activate-original-branch', ['branchCode' => $original->code]));
        $this->assertDatabaseCount('branch_products', 2);
    }

    public function test_existing_original_branch_balance_blocks_cutover(): void
    {
        $branch = Branch::factory()->create(['code' => 'ORIGINAL']);
        $product = Product::factory()->create(['stock_quantity' => 9]);
        BranchProduct::factory()->for($branch)->for($product)->create(['on_hand' => 3]);
        $this->mock(DatabaseBackupService::class)->shouldNotReceive('create');

        $this->assertSame(1, Artisan::call('inventory:activate-original-branch', ['branchCode' => $branch->code, '--apply' => true]));
        $this->assertDatabaseCount('original_branch_inventory', 0);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $branch->id, 'on_hand' => 3]);
    }

    public function test_backup_failure_leaves_mirror_inactive_and_balances_untouched(): void
    {
        $branch = Branch::factory()->create(['code' => 'ORIGINAL']);
        Product::factory()->create(['stock_quantity' => 9]);
        $this->mock(DatabaseBackupService::class)->shouldReceive('create')->once()
            ->andThrow(new \RuntimeException('Backup unavailable'));

        Artisan::call('down');
        try {
            $result = Artisan::call('inventory:activate-original-branch', [
                'branchCode' => $branch->code, '--apply' => true, '--confirm-code' => $branch->code,
            ]);
        } finally {
            Artisan::call('up');
        }

        $this->assertSame(1, $result);
        $this->assertDatabaseCount('original_branch_inventory', 0);
        $this->assertDatabaseCount('branch_products', 0);
    }
}
