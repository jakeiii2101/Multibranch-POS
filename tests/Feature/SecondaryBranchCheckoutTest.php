<?php

namespace Tests\Feature;

use App\Livewire\Pos\BranchSaleTerminal;
use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\BranchDailyClosing;
use App\Models\BranchProduct;
use App\Models\InvoiceSequence;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\DailyReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SecondaryBranchCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setupRegister(): array
    {
        $original = Branch::factory()->create();
        $secondary = Branch::factory()->create(['code' => '002']);
        $cashier = User::factory()->create();
        $secondary->users()->attach($cashier->id, ['status' => Branch::STATUS_ACTIVE]);
        DB::table('original_branch_inventory')->insert(['id' => 1, 'branch_id' => $original->id, 'activated_at' => now()]);
        $product = Product::factory()->create(['stock_quantity' => 0, 'selling_price' => 100]);
        $balance = BranchProduct::factory()->for($secondary)->for($product)->create([
            'on_hand' => 5, 'reserved' => 1, 'price_override' => 125,
        ]);
        BirSetting::query()->create([
            'branch_id' => $secondary->id, 'registered_name' => 'Second Shop',
            'tin' => '123-456-789-00000', 'branch_code' => '002',
            'registered_address' => 'Test Address', 'tax_type' => BirSetting::TAX_TYPE_VAT,
            'vat_rate' => 12, 'is_active' => true,
        ]);
        $sequence = InvoiceSequence::query()->create([
            'branch_id' => $secondary->id, 'document_type' => InvoiceSequence::TYPE_SALES_INVOICE,
            'branch_code' => '002', 'prefix' => 'B2-', 'current_number' => 0,
            'starting_number' => 1, 'is_active' => true,
        ]);

        return compact('original', 'secondary', 'cashier', 'product', 'balance', 'sequence');
    }

    public function test_secondary_checkout_uses_its_stock_price_and_invoice_without_changing_legacy_stock(): void
    {
        extract($this->setupRegister());
        $this->actingAs($cashier)->get(route('pos.branch', $secondary))->assertOk();
        Livewire::actingAs($cashier)->test(BranchSaleTerminal::class, ['branch' => $secondary])
            ->call('addProduct', $product->id)->call('increase', $product->id)
            ->set('cashReceived', '300')->call('completeSale')->assertHasNoErrors();
        $sale = Sale::query()->firstOrFail();
        $this->assertSame($secondary->id, $sale->branch_id);
        $this->assertSame('B2-000000000001', $sale->invoice_number);
        $this->assertSame('Second Shop', $sale->seller_snapshot['registered_name']);
        $this->assertSame('250.00', $sale->total);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(3, $balance->fresh()->on_hand);
        $this->assertDatabaseHas('stock_movements', ['branch_id' => $secondary->id,
            'product_id' => $product->id, 'stock_before' => 5, 'stock_after' => 3]);
        $this->assertSame(1, $sequence->fresh()->current_number);
        $this->assertSame(0, app(DailyReadingService::class)->snapshot(now())['sales']['transaction_count']);
    }

    public function test_unassigned_cashier_cannot_open_secondary_register(): void
    {
        extract($this->setupRegister());
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('pos.branch', $secondary))->assertForbidden();
        $this->actingAs($other)->get(route('pos.select'))->assertDontSee($secondary->name);
    }

    public function test_closed_branch_rejects_checkout_without_consuming_stock_or_invoice(): void
    {
        extract($this->setupRegister());
        BranchDailyClosing::query()->create([
            'branch_id' => $secondary->id, 'business_date' => now()->toDateString(),
            'reading_number' => 'Z-TEST', 'closed_by' => User::factory()->admin()->create()->id,
            'closed_at' => now(), 'snapshot' => [],
        ]);
        Livewire::actingAs($cashier)->test(BranchSaleTerminal::class, ['branch' => $secondary])
            ->call('addProduct', $product->id)->set('cashReceived', '200')
            ->call('completeSale')->assertHasErrors('cart');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $balance->fresh()->on_hand);
        $this->assertSame(0, $sequence->fresh()->current_number);
    }
}
