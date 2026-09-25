<?php

namespace Tests\Feature;

use App\Livewire\Inventory\InventoryList;
use App\Livewire\Products\ProductList;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class LegacyStockMirrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_inventory_write_is_mirrored_only_after_activation(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $branch = Branch::factory()->create();
        $balance = BranchProduct::factory()->for($branch)->for($product)->create(['on_hand' => 10]);

        Livewire::actingAs($admin)->test(InventoryList::class)
            ->set('productId', $product->id)->set('type', StockMovement::TYPE_STOCK_IN)
            ->set('quantity', 2)->set('reason', 'Legacy delivery')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(12, $product->fresh()->stock_quantity);
        $this->assertSame(10, $balance->fresh()->on_hand);
        $this->assertNull(StockMovement::query()->firstOrFail()->branch_id);

        $balance->update(['on_hand' => 12]);
        $this->activate($branch);

        Livewire::actingAs($admin)->test(InventoryList::class)
            ->set('productId', $product->id)->set('type', StockMovement::TYPE_STOCK_IN)
            ->set('quantity', 3)->set('reason', 'Mirrored delivery')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(15, $product->fresh()->stock_quantity);
        $this->assertSame(15, $balance->fresh()->on_hand);
        $this->assertDatabaseHas('stock_movements', ['reason' => 'Mirrored delivery', 'branch_id' => $branch->id]);
    }

    public function test_drift_throws_and_rolls_back_both_legacy_update_and_movement(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $branch = Branch::factory()->create();
        BranchProduct::factory()->for($branch)->for($product)->create(['on_hand' => 9]);
        $this->activate($branch);

        try {
            Livewire::actingAs($admin)->test(InventoryList::class)
                ->set('productId', $product->id)->set('type', StockMovement::TYPE_STOCK_IN)
                ->set('quantity', 2)->set('reason', 'Should roll back')
                ->call('save');
            $this->fail('Expected a stock drift exception.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('differs from legacy stock', $exception->getMessage());
        }

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_new_product_gets_original_branch_balance_inside_the_same_transaction(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->admin()->create();
        $category = \App\Models\Category::factory()->create();
        $this->activate($branch);

        Livewire::actingAs($admin)->test(ProductList::class)
            ->set('categoryId', $category->id)
            ->set('sku', 'NEW-MIRROR-001')
            ->set('name', 'Mirrored Product')
            ->set('costPrice', '10.00')
            ->set('sellingPrice', '15.00')
            ->set('stockQuantity', 4)
            ->set('lowStockLevel', 2)
            ->call('save')->assertHasNoErrors();

        $product = Product::query()->where('sku', 'NEW-MIRROR-001')->firstOrFail();
        $this->assertDatabaseHas('branch_products', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'on_hand' => 4,
            'reorder_level' => 2,
        ]);
    }

    private function activate(Branch $branch): void
    {
        DB::table('original_branch_inventory')->insert([
            'id' => 1,
            'branch_id' => $branch->id,
            'activated_at' => now(),
        ]);
    }
}
