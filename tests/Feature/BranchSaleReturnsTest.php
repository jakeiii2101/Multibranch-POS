<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchDailyClosing;
use App\Models\BranchProduct;
use App\Models\DailyClosing;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\PartialRefundService;
use App\Support\SaleReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class BranchSaleReturnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_reversal_restocks_only_the_sale_branch(): void
    {
        [$admin, $original, $secondary, $product, $sale] = $this->fixture();

        app(SaleReversalService::class)->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'Duplicate branch sale', true);

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $original->id, 'product_id' => $product->id, 'on_hand' => 10]);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $secondary->id, 'product_id' => $product->id, 'on_hand' => 6]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $secondary->id, 'type' => StockMovement::TYPE_VOID,
            'quantity' => 2, 'stock_before' => 4, 'stock_after' => 6,
        ]);
    }

    public function test_partial_refund_restocks_only_secondary_branch_and_non_restockable_refund_does_not_change_it(): void
    {
        [$admin, $original, $secondary, $product, $sale] = $this->fixture();
        $item = $sale->items()->firstOrFail();

        app(PartialRefundService::class)->refund($sale, $admin, [$item->id => 1], 'Returned sealed unit', true);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $secondary->id, 'on_hand' => 5]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $secondary->id, 'type' => StockMovement::TYPE_REFUND,
            'quantity' => 1, 'stock_before' => 4, 'stock_after' => 5,
        ]);

        app(PartialRefundService::class)->refund($sale, $admin, [$item->id => 1], 'Damaged return no stock', false);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $secondary->id, 'on_hand' => 5]);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $original->id, 'on_hand' => 10]);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_branch_closing_blocks_its_return_but_global_closing_does_not(): void
    {
        [$admin, , $secondary, , $sale] = $this->fixture();
        DailyClosing::query()->create([
            'business_date' => now()->toDateString(), 'reading_number' => 'Z-'.now()->format('Ymd'),
            'closed_by' => $admin->id, 'closed_at' => now(), 'snapshot' => [],
        ]);

        app(SaleReversalService::class)->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'Branch open despite global closing', false);
        $this->assertDatabaseCount('sale_adjustments', 1);

        [$adminTwo, , $otherBranch, , $otherSale] = $this->fixture('OTHER');
        BranchDailyClosing::query()->create([
            'branch_id' => $otherBranch->id, 'business_date' => now()->toDateString(),
            'reading_number' => 'Z-'.$otherBranch->code.'-'.now()->format('Ymd'),
            'closed_by' => $adminTwo->id, 'closed_at' => now(), 'snapshot' => [],
        ]);

        try {
            app(SaleReversalService::class)->reverse($otherSale, $adminTwo, SaleAdjustment::TYPE_VOID, 'Closed branch attempt', true);
            $this->fail('Expected closed branch rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reversal', $exception->errors());
        }
        $this->assertDatabaseCount('sale_adjustments', 1);
    }

    public function test_missing_secondary_balance_rolls_back_financial_reversal(): void
    {
        [$admin, , $secondary, $product, $sale] = $this->fixture();
        BranchProduct::query()->where('branch_id', $secondary->id)->where('product_id', $product->id)->delete();

        $this->expectException(LogicException::class);
        try {
            app(SaleReversalService::class)->reverse($sale, $admin, SaleAdjustment::TYPE_VOID, 'Missing branch balance', true);
        } finally {
            $this->assertDatabaseCount('sale_adjustments', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_partial_refund_respects_secondary_branch_closing(): void
    {
        [$admin, , $secondary, , $sale] = $this->fixture();
        BranchDailyClosing::query()->create([
            'branch_id' => $secondary->id, 'business_date' => now()->toDateString(),
            'reading_number' => 'Z-'.$secondary->code.'-'.now()->format('Ymd'),
            'closed_by' => $admin->id, 'closed_at' => now(), 'snapshot' => [],
        ]);

        try {
            app(PartialRefundService::class)->refund($sale, $admin, [
                $sale->items()->firstOrFail()->id => 1,
            ], 'Return after branch close', true);
            $this->fail('Expected closed branch rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('partialRefund', $exception->errors());
        }

        $this->assertDatabaseCount('sale_refunds', 0);
    }

    /** @return array{User, Branch, Branch, Product, Sale} */
    private function fixture(string $suffix = 'BASE'): array
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create();
        $secondary = Branch::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        BranchProduct::factory()->for($original)->for($product)->create(['on_hand' => 10]);
        BranchProduct::factory()->for($secondary)->for($product)->create(['on_hand' => 4]);
        if (! DB::table('original_branch_inventory')->exists()) {
            DB::table('original_branch_inventory')->insert([
                'id' => 1, 'branch_id' => $original->id, 'activated_at' => now(),
            ]);
        }

        $sale = Sale::query()->create([
            'sale_number' => 'SI-'.$suffix,
            'invoice_number' => 'SI-'.$suffix,
            'branch_id' => $secondary->id,
            'user_id' => $admin->id,
            'subtotal' => 200, 'total' => 200,
            'cash_received' => 200, 'change_due' => 0,
            'status' => Sale::STATUS_COMPLETED, 'completed_at' => now(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'unit_price' => 100,
            'quantity' => 2, 'line_total' => 200, 'net_total' => 200,
        ]);

        return [$admin, $original, $secondary, $product, $sale];
    }
}
