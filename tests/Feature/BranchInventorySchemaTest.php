<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchInventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_balances_for_the_same_product_are_independent(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 12]);
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $a = BranchProduct::factory()->for($branchA)->for($product)->create(['on_hand' => 8]);
        $b = BranchProduct::factory()->for($branchB)->for($product)->create(['on_hand' => 4]);

        $a->update(['on_hand' => 6]);

        $this->assertSame(6, $a->fresh()->on_hand);
        $this->assertSame(4, $b->fresh()->on_hand);
        $this->assertSame(12, $product->fresh()->stock_quantity);
    }

    public function test_schema_does_not_automatically_copy_legacy_stock_to_a_branch(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 9]);
        Branch::factory()->create();

        $this->assertDatabaseCount('branch_products', 0);
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_one_branch_product_pair_has_only_one_balance(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        BranchProduct::factory()->for($branch)->for($product)->create();

        $this->expectException(QueryException::class);
        BranchProduct::factory()->for($branch)->for($product)->create();
    }

    public function test_historical_movements_can_remain_unassigned_until_cutover(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $admin = User::factory()->admin()->create();

        $movement = StockMovement::query()->create([
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'type' => StockMovement::TYPE_STOCK_IN,
            'quantity' => 5,
            'stock_before' => 0,
            'stock_after' => 5,
            'reason' => 'Existing single-store entry',
        ]);

        $this->assertNull($movement->fresh()->branch_id);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }
}
