<?php

namespace Tests\Feature;

use App\Livewire\Inventory\BranchInventory;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BranchInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_stock_assigned_secondary_branch_without_changing_legacy_stock(): void
    {
        $manager = User::factory()->manager()->create();
        $original = Branch::factory()->create();
        $secondary = Branch::factory()->create(['name' => 'South Branch']);
        $manager->branches()->attach($secondary->id);
        $product = Product::factory()->create(['stock_quantity' => 10, 'low_stock_level' => 2]);
        $this->activate($original);

        Livewire::actingAs($manager)->test(BranchInventory::class)
            ->set('branchId', (string) $secondary->id)
            ->set('productId', $product->id)
            ->set('quantity', 6)
            ->set('reason', 'Opening stock delivery')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('branch_products', [
            'branch_id' => $secondary->id, 'product_id' => $product->id,
            'on_hand' => 6, 'reorder_level' => 2,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $secondary->id, 'product_id' => $product->id,
            'type' => StockMovement::TYPE_STOCK_IN, 'quantity' => 6,
            'stock_before' => 0, 'stock_after' => 6,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.inventory_movement']);

        Livewire::actingAs($manager)->test(BranchInventory::class)
            ->set('branchId', (string) $secondary->id)
            ->set('productId', $product->id)
            ->set('type', StockMovement::TYPE_STOCK_OUT)
            ->set('quantity', 2)
            ->set('reason', 'Damaged units')
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('branch_products', ['branch_id' => $secondary->id, 'on_hand' => 4]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $secondary->id, 'type' => StockMovement::TYPE_STOCK_OUT,
            'quantity' => -2, 'stock_before' => 6, 'stock_after' => 4,
        ]);
    }

    public function test_supervisor_cannot_select_unassigned_or_original_branch(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $original = Branch::factory()->create(['name' => 'Original Branch']);
        $assigned = Branch::factory()->create(['name' => 'Assigned Branch']);
        $other = Branch::factory()->create(['name' => 'Private Branch']);
        $supervisor->branches()->attach($assigned->id);
        $this->activate($original);

        $this->actingAs($supervisor)->get(route('branch-inventory'))->assertOk()
            ->assertSee('Assigned Branch')->assertDontSee('Private Branch')->assertDontSee('Original Branch');
        Livewire::actingAs($supervisor)->test(BranchInventory::class)
            ->set('branchId', (string) $other->id)->assertNotFound();
        Livewire::actingAs($supervisor)->test(BranchInventory::class)
            ->set('branchId', (string) $original->id)->assertNotFound();
    }

    public function test_overdraw_is_rejected_without_movement_or_balance(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create();
        $secondary = Branch::factory()->create();
        $product = Product::factory()->create();
        $this->activate($original);

        Livewire::actingAs($admin)->test(BranchInventory::class)
            ->set('branchId', (string) $secondary->id)
            ->set('productId', $product->id)
            ->set('type', StockMovement::TYPE_STOCK_OUT)
            ->set('quantity', 1)
            ->set('reason', 'Overdraw attempt')
            ->call('save')->assertHasErrors('quantity');

        $this->assertDatabaseCount('branch_products', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cashier_cannot_open_or_call_branch_inventory(): void
    {
        $cashier = User::factory()->create();
        $this->actingAs($cashier)->get(route('branch-inventory'))->assertForbidden();
        Livewire::actingAs($cashier)->test(BranchInventory::class)->assertForbidden();
    }

    private function activate(Branch $branch): void
    {
        DB::table('original_branch_inventory')->insert([
            'id' => 1, 'branch_id' => $branch->id, 'activated_at' => now(),
        ]);
    }
}
