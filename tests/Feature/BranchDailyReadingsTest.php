<?php

namespace Tests\Feature;

use App\Livewire\Reports\BranchDailyReadings;
use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\BranchDailyClosing;
use App\Models\DailyClosing;
use App\Models\Sale;
use App\Models\SaleRefund;
use App\Models\User;
use App\Support\BranchDailyReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class BranchDailyReadingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_preview_excludes_other_branches_and_legacy_sales_and_refunds(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create();
        $south = Branch::factory()->create(['name' => 'South Store']);
        $north = Branch::factory()->create(['name' => 'North Store']);
        $this->activate($original);
        $southSale = $this->sale($admin, $south, 'SI-SOUTH-001', 112);
        $northSale = $this->sale($admin, $north, 'SI-NORTH-001', 999);
        $this->sale($admin, null, 'SI-LEGACY-001', 777);
        $this->refund($admin, $southSale, 'RF-SOUTH-001', 56);
        $this->refund($admin, $northSale, 'RF-NORTH-001', 400);

        Livewire::actingAs($admin)->test(BranchDailyReadings::class)
            ->set('branchId', (string) $south->id)
            ->assertViewHas('snapshot', fn (array $snapshot): bool =>
                $snapshot['invoice_range']['issued_count'] === 1
                && $snapshot['invoice_range']['first'] === 'SI-SOUTH-001'
                && $snapshot['sales']['net_sales'] === 56.0
                && $snapshot['reversals']['partial_refund_count'] === 1)
            ->assertSee('South Store')->assertDontSee('SI-NORTH-001');
    }

    public function test_branch_closing_is_independent_from_original_and_another_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create();
        $south = Branch::factory()->create();
        $north = Branch::factory()->create();
        $this->activate($original);
        $this->configure($south);
        $this->configure($north);
        $this->sale($admin, $south, 'SI-SOUTH-001', 112);
        DailyClosing::query()->create([
            'business_date' => now()->toDateString(), 'reading_number' => 'Z-'.now()->format('Ymd'),
            'closed_by' => $admin->id, 'closed_at' => now(), 'snapshot' => [],
        ]);

        $service = app(BranchDailyReadingService::class);
        $southClosing = $service->close($south, now(), $admin, 'South closing');
        $northClosing = $service->close($north, now(), $admin, 'North closing');

        $this->assertEquals(112.0, $southClosing->snapshot['sales']['net_sales']);
        $this->assertEquals(0.0, $northClosing->snapshot['sales']['net_sales']);
        $this->assertDatabaseCount('branch_daily_closings', 2);
        $this->assertDatabaseCount('daily_closings', 1);
        $this->expectException(LogicException::class);
        $southClosing->update(['notes' => 'Attempted rewrite']);
    }

    public function test_duplicate_and_original_branch_closing_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create();
        $south = Branch::factory()->create();
        $this->activate($original);
        $this->configure($south);

        $service = app(BranchDailyReadingService::class);
        $service->close($south, now(), $admin, null);

        Livewire::actingAs($admin)->test(BranchDailyReadings::class)
            ->set('branchId', (string) $south->id)
            ->set('authorizationPassword', 'password')
            ->set('confirmed', true)
            ->call('createZReading')->assertHasErrors('closing');
        Livewire::actingAs($admin)->test(BranchDailyReadings::class)
            ->set('branchId', (string) $original->id)->assertNotFound();
        $this->assertDatabaseCount('branch_daily_closings', 1);
    }

    public function test_admin_password_is_required_and_non_admin_cannot_open_readings(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $original = Branch::factory()->create();
        $south = Branch::factory()->create();
        $this->activate($original);

        Livewire::actingAs($admin)->test(BranchDailyReadings::class)
            ->set('branchId', (string) $south->id)
            ->set('authorizationPassword', 'wrong')
            ->call('createZReading')->assertHasErrors(['authorizationPassword', 'confirmed']);
        $this->actingAs($manager)->get(route('branch-daily-readings'))->assertForbidden();
        $this->assertDatabaseCount('branch_daily_closings', 0);
    }

    private function activate(Branch $branch): void
    {
        DB::table('original_branch_inventory')->insert([
            'id' => 1, 'branch_id' => $branch->id, 'activated_at' => now(),
        ]);
    }

    private function configure(Branch $branch): void
    {
        BirSetting::query()->create([
            'branch_id' => $branch->id, 'registered_name' => $branch->name,
            'tin' => '123-456-789-00001', 'branch_code' => '00002',
            'registered_address' => 'General Santos City',
            'tax_type' => BirSetting::TAX_TYPE_VAT, 'vat_rate' => 12, 'is_active' => true,
        ]);
    }

    private function sale(User $user, ?Branch $branch, string $invoice, int $total): Sale
    {
        return Sale::query()->create([
            'sale_number' => $invoice, 'invoice_number' => $invoice,
            'branch_id' => $branch?->id, 'user_id' => $user->id,
            'subtotal' => $total, 'total' => $total,
            'cash_received' => $total, 'change_due' => 0,
            'status' => Sale::STATUS_COMPLETED, 'completed_at' => now(),
        ]);
    }

    private function refund(User $user, Sale $sale, string $number, int $amount): void
    {
        SaleRefund::query()->create([
            'refund_number' => $number, 'sale_id' => $sale->id,
            'authorized_by' => $user->id, 'gross_amount' => $amount,
            'refund_amount' => $amount, 'reason' => 'Branch reading test',
            'processed_at' => now(),
        ]);
    }
}
