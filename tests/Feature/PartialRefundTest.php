<?php

namespace Tests\Feature;

use App\Livewire\Sales\PartialRefundPanel;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PartialRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_partially_refund_and_restock_an_item(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale, $product] = $this->createSale($admin);
        $item = $sale->items->first();

        Livewire::actingAs($admin)->test(PartialRefundPanel::class, ['sale' => $sale])
            ->set('quantities.'.$item->id, 1)
            ->set('reason', 'Customer returned one sealed unit')
            ->set('authorizationPassword', 'password')
            ->set('confirmed', true)
            ->call('process')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sale_refunds', [
            'sale_id' => $sale->id,
            'refund_amount' => 112,
            'vatable_sales' => 100,
            'vat_amount' => 12,
        ]);
        $this->assertDatabaseHas('sale_refund_items', ['sale_item_id' => $item->id, 'quantity' => 1]);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_REFUND, 'quantity' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.partial_refund', 'auditable_id' => $sale->id]);
    }

    public function test_partial_refund_restock_mirrors_original_branch(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale, $product] = $this->createSale($admin);
        $branch = Branch::factory()->create();
        $balance = BranchProduct::factory()->for($branch)->for($product)->create(['on_hand' => 7]);
        DB::table('original_branch_inventory')->insert(['id' => 1, 'branch_id' => $branch->id, 'activated_at' => now()]);

        $this->actingAs($admin);
        $itemId = $sale->items->firstOrFail()->id;
        app(\App\Support\PartialRefundService::class)->refund($sale, $admin, [$itemId => 1], 'Customer returned sealed unit', true);

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(8, $balance->fresh()->on_hand);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_REFUND, 'branch_id' => $branch->id]);
    }

    public function test_refund_cannot_exceed_the_remaining_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale] = $this->createSale($admin);
        $item = $sale->items->first();

        Livewire::actingAs($admin)->test(PartialRefundPanel::class, ['sale' => $sale])
            ->set('quantities.'.$item->id, 4)
            ->set('reason', 'Attempted excessive item return')
            ->set('authorizationPassword', 'password')
            ->set('confirmed', true)
            ->call('process')
            ->assertHasErrors('quantities.'.$item->id);

        $this->assertDatabaseCount('sale_refunds', 0);
    }

    public function test_full_reversal_is_blocked_after_a_partial_refund(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale] = $this->createSale($admin);
        $item = $sale->items->first();
        $this->actingAs($admin);

        app(\App\Support\PartialRefundService::class)->refund($sale, $admin, [$item->id => 1], 'Customer returned one unit', false);

        $this->expectException(ValidationException::class);
        app(SaleReversalService::class)->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'Attempt full reversal afterward', true);
    }

    public function test_invoice_displays_the_partial_refund_reference(): void
    {
        $admin = User::factory()->admin()->create();
        [$sale] = $this->createSale($admin);
        $item = $sale->items->first();
        $this->actingAs($admin);
        $refund = app(\App\Support\PartialRefundService::class)->refund($sale, $admin, [$item->id => 1], 'Customer returned one unit', false);

        $this->get(route('sales.invoice', $sale, false))
            ->assertOk()
            ->assertSee('PARTIALLY REFUNDED')
            ->assertSee($refund->refund_number);
    }

    /** @return array{Sale, Product} */
    private function createSale(User $user): array
    {
        $category = Category::query()->create(['name' => 'Refund Test', 'status' => Category::STATUS_ACTIVE]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PARTIAL-001',
            'name' => 'Refundable Product',
            'cost_price' => 50,
            'selling_price' => 112,
            'stock_quantity' => 7,
            'low_stock_level' => 1,
            'status' => Product::STATUS_ACTIVE,
            'tax_type' => Product::TAX_VATABLE,
        ]);
        $sale = Sale::query()->create([
            'sale_number' => 'SI-PARTIAL-001',
            'invoice_number' => 'SI-PARTIAL-001',
            'user_id' => $user->id,
            'subtotal' => 336,
            'discount_amount' => 0,
            'total' => 336,
            'cash_received' => 336,
            'change_due' => 0,
            'status' => Sale::STATUS_COMPLETED,
            'completed_at' => now(),
            'tax_type' => 'vat',
            'vatable_sales' => 300,
            'vat_amount' => 36,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 112,
            'quantity' => 3,
            'line_total' => 336,
            'tax_type' => Product::TAX_VATABLE,
            'discount_amount' => 0,
            'vat_amount' => 36,
            'net_total' => 336,
        ]);

        return [$sale->fresh('items'), $product];
    }
}
