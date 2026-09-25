<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BranchInventoryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_missing_balance_without_creating_any_data(): void
    {
        $branch = Branch::factory()->create(['code' => 'ORIGINAL']);
        $product = Product::factory()->create(['stock_quantity' => 7]);

        $exitCode = Artisan::call('inventory:branch-audit', ['branchCode' => $branch->code, '--strict' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Products without branch balance', Artisan::output());
        $this->assertDatabaseCount('branch_products', 0);
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_detects_a_different_branch_balance_and_ignores_other_branches(): void
    {
        $original = Branch::factory()->create(['code' => 'ORIGINAL']);
        $other = Branch::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        BranchProduct::factory()->for($original)->for($product)->create(['on_hand' => 9]);
        BranchProduct::factory()->for($other)->for($product)->create(['on_hand' => 50]);

        $exitCode = Artisan::call('inventory:branch-audit', ['branchCode' => $original->code, '--strict' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Products with different balances', Artisan::output());
        $this->assertDatabaseHas('branch_products', ['branch_id' => $other->id, 'product_id' => $product->id, 'on_hand' => 50]);
    }

    public function test_matching_snapshot_passes_and_reports_unassigned_history(): void
    {
        $branch = Branch::factory()->create(['code' => 'ORIGINAL']);
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $admin = User::factory()->admin()->create();
        BranchProduct::factory()->for($branch)->for($product)->create(['on_hand' => 5]);
        StockMovement::query()->create([
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'type' => StockMovement::TYPE_STOCK_IN,
            'quantity' => 5,
            'stock_before' => 0,
            'stock_after' => 5,
            'reason' => 'Historical entry',
        ]);

        $exitCode = Artisan::call('inventory:branch-audit', ['branchCode' => $branch->code, '--strict' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Historical movements without branch', Artisan::output());
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'branch_id' => null]);
    }

    public function test_unknown_branch_fails_without_writing(): void
    {
        $exitCode = Artisan::call('inventory:branch-audit', ['branchCode' => 'UNKNOWN']);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('branches', 0);
    }
}
