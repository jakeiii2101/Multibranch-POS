<?php

namespace Tests\Feature;

use App\Livewire\Sales\SaleAdjustmentPanel;
use App\Livewire\Sales\SalesHistory;
use App\Models\Category;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\SaleReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SaleAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_void_same_day_sale_and_inventory_is_restored(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale, $product] = $this->createSale($admin, now());

        Livewire::actingAs($admin)->test(SaleAdjustmentPanel::class, ['sale' => $sale])
            ->set('reversalType', SaleAdjustment::TYPE_VOID)
            ->set('reason', 'Duplicate cashier transaction')
            ->set('authorizationPassword', 'password')
            ->set('confirmed', true)
            ->call('process')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $sale->id,
            'authorized_by' => $admin->id,
            'type' => SaleAdjustment::TYPE_VOID,
            'amount' => 100,
            'inventory_restocked' => true,
        ]);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_VOID,
            'quantity' => 2,
            'stock_before' => 8,
            'stock_after' => 10,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.void', 'auditable_id' => $sale->id]);
    }

    public function test_full_reversal_restock_mirrors_original_branch(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale, $product] = $this->createSale($admin, now());
        $branch = Branch::factory()->create();
        $balance = BranchProduct::factory()->for($branch)->for($product)->create(['on_hand' => 8]);
        DB::table('original_branch_inventory')->insert(['id' => 1, 'branch_id' => $branch->id, 'activated_at' => now()]);

        $this->actingAs($admin);
        app(SaleReversalService::class)->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'Duplicate sale reversed', true);

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(10, $balance->fresh()->on_hand);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_VOID, 'branch_id' => $branch->id]);
    }

    public function test_refund_can_record_non_restockable_items(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale, $product] = $this->createSale($admin, now()->subDay());

        Livewire::actingAs($admin)->test(SaleAdjustmentPanel::class, ['sale' => $sale])
            ->assertSet('reversalType', SaleAdjustment::TYPE_REFUND)
            ->set('reason', 'Customer returned damaged goods')
            ->set('restockInventory', false)
            ->set('authorizationPassword', 'password')
            ->set('confirmed', true)
            ->call('process')
            ->assertHasNoErrors();

        $this->assertFalse($sale->fresh()->adjustment->inventory_restocked);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertDatabaseMissing('stock_movements', ['type' => StockMovement::TYPE_REFUND]);
    }

    public function test_void_rejects_a_sale_from_an_earlier_business_date(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale] = $this->createSale($admin, now()->subDay());

        Livewire::actingAs($admin)->test(SaleAdjustmentPanel::class, ['sale' => $sale])
            ->set('reversalType', SaleAdjustment::TYPE_VOID)
            ->set('reason', 'Attempt to void old transaction')
            ->set('authorizationPassword', 'password')
            ->set('confirmed', true)
            ->call('process')
            ->assertHasErrors('reversalType');

        $this->assertDatabaseCount('sale_adjustments', 0);
    }

    public function test_duplicate_reversal_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale] = $this->createSale($admin, now());
        $service = app(SaleReversalService::class);

        $this->actingAs($admin);
        $service->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'First valid reversal', true);

        $this->expectException(ValidationException::class);
        $service->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'Second invalid reversal', true);
    }

    public function test_authorization_requires_current_admin_password_and_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale] = $this->createSale($admin, now());

        Livewire::actingAs($admin)->test(SaleAdjustmentPanel::class, ['sale' => $sale])
            ->set('reason', 'Duplicate cashier transaction')
            ->set('authorizationPassword', 'incorrect')
            ->call('process')
            ->assertHasErrors(['authorizationPassword', 'confirmed']);

        $this->assertDatabaseCount('sale_adjustments', 0);
    }

    public function test_reversed_sales_remain_visible_but_are_excluded_from_net_summary(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale] = $this->createSale($admin, now());
        $this->actingAs($admin);
        app(SaleReversalService::class)->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'Exclude from active net sales', true);

        Livewire::actingAs($admin)->test(SalesHistory::class)
            ->assertSee($sale->sale_number)
            ->assertSee('VOID')
            ->assertViewHas('transactionCount', 0)
            ->assertViewHas('netSales', 0.0);
    }

    /** @return array{Sale, Product} */
    private function createSale(User $user, Carbon $completedAt): array
    {
        $category = Category::query()->create(['name' => 'Reversal Test', 'status' => Category::STATUS_ACTIVE]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'REV-'.$completedAt->timestamp,
            'name' => 'Reversal Product',
            'cost_price' => 20,
            'selling_price' => 50,
            'stock_quantity' => 8,
            'low_stock_level' => 1,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $sale = Sale::query()->create([
            'sale_number' => 'SI-'.$completedAt->timestamp,
            'invoice_number' => 'SI-'.$completedAt->timestamp,
            'user_id' => $user->id,
            'subtotal' => 100,
            'discount_amount' => 0,
            'total' => 100,
            'cash_received' => 100,
            'change_due' => 0,
            'status' => Sale::STATUS_COMPLETED,
            'completed_at' => $completedAt,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 50,
            'quantity' => 2,
            'line_total' => 100,
            'net_total' => 100,
        ]);

        return [$sale, $product];
    }
}
