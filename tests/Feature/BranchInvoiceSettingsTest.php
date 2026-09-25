<?php

namespace Tests\Feature;

use App\Livewire\Settings\BranchInvoiceSettings;
use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\InvoiceSequence;
use App\Models\User;
use App\Support\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BranchInvoiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_an_additional_branch_without_changing_legacy_invoices(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create();
        $secondary = Branch::factory()->create(['name' => 'South Store']);
        $this->activate($original);
        BirSetting::query()->create([
            'registered_name' => 'Original Store', 'tin' => '123-456-789-00000',
            'branch_code' => '00000', 'registered_address' => 'Original Address',
            'tax_type' => BirSetting::TAX_TYPE_VAT, 'vat_rate' => 12, 'is_active' => true,
        ]);
        InvoiceSequence::query()->create([
            'document_type' => InvoiceSequence::TYPE_SALES_INVOICE, 'branch_code' => '00000',
            'prefix' => 'SI-ORIG-', 'current_number' => 5, 'starting_number' => 1, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)->test(BranchInvoiceSettings::class)
            ->set('branchId', (string) $secondary->id)
            ->set('registeredName', 'South Store Registered')
            ->set('tin', '123-456-789-00001')
            ->set('branchCode', '00002')
            ->set('registeredAddress', 'South Address')
            ->set('invoicePrefix', 'SI-SOUTH-')
            ->set('startingNumber', '100')
            ->set('isActive', true)
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('bir_settings', [
            'branch_id' => $secondary->id, 'branch_code' => '00002', 'is_active' => true,
        ]);
        $this->assertDatabaseHas('invoice_sequences', [
            'branch_id' => $secondary->id, 'branch_code' => '00002',
            'prefix' => 'SI-SOUTH-', 'current_number' => 99,
        ]);
        $this->assertDatabaseHas('invoice_sequences', [
            'branch_id' => null, 'branch_code' => '00000', 'current_number' => 5,
        ]);
        $invoice = DB::transaction(fn (): array => app(InvoiceNumberService::class)->next($secondary));
        $this->assertSame('SI-SOUTH-000000000100', $invoice['invoice_number']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.invoice_settings_updated']);
    }

    public function test_issued_branch_numbers_and_identifiers_cannot_be_reset(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create();
        $secondary = Branch::factory()->create();
        $this->activate($original);
        $this->configureBranch($secondary, '00002', 'SI-SOUTH-', 14);

        Livewire::actingAs($admin)->test(BranchInvoiceSettings::class)
            ->set('branchId', (string) $secondary->id)
            ->set('invoicePrefix', 'SI-RESET-')
            ->call('save')->assertHasErrors('invoicePrefix');

        $this->assertDatabaseHas('invoice_sequences', [
            'branch_id' => $secondary->id, 'prefix' => 'SI-SOUTH-', 'current_number' => 14,
        ]);
    }

    public function test_duplicate_invoice_prefix_is_rejected_and_original_branch_is_excluded(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Branch::factory()->create(['name' => 'Original Store']);
        $secondary = Branch::factory()->create();
        $this->activate($original);
        InvoiceSequence::query()->create([
            'document_type' => InvoiceSequence::TYPE_SALES_INVOICE, 'branch_code' => '00000',
            'prefix' => 'SI-ORIG-', 'current_number' => 0, 'starting_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('settings.branch-invoices'))->assertOk()->assertDontSee('Original Store');
        Livewire::actingAs($admin)->test(BranchInvoiceSettings::class)
            ->set('branchId', (string) $original->id)->assertNotFound();

        Livewire::actingAs($admin)->test(BranchInvoiceSettings::class)
            ->set('branchId', (string) $secondary->id)
            ->set('registeredName', 'South Store')
            ->set('tin', '123-456-789-00001')
            ->set('branchCode', '00002')
            ->set('registeredAddress', 'South Address')
            ->set('invoicePrefix', 'SI-ORIG-')
            ->call('save')->assertHasErrors('invoicePrefix');

        $this->assertDatabaseCount('bir_settings', 0);
    }

    public function test_manager_and_cashier_cannot_access_branch_invoice_settings(): void
    {
        foreach ([User::factory()->manager()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('settings.branch-invoices'))->assertForbidden();
            Livewire::actingAs($user)->test(BranchInvoiceSettings::class)->assertForbidden();
        }
    }

    private function activate(Branch $branch): void
    {
        DB::table('original_branch_inventory')->insert([
            'id' => 1, 'branch_id' => $branch->id, 'activated_at' => now(),
        ]);
    }

    private function configureBranch(Branch $branch, string $code, string $prefix, int $current): void
    {
        BirSetting::query()->create([
            'branch_id' => $branch->id, 'registered_name' => 'South Store',
            'tin' => '123-456-789-00001', 'branch_code' => $code,
            'registered_address' => 'South Address', 'tax_type' => BirSetting::TAX_TYPE_NON_VAT,
            'vat_rate' => 12, 'is_active' => true,
        ]);
        InvoiceSequence::query()->create([
            'branch_id' => $branch->id, 'document_type' => InvoiceSequence::TYPE_SALES_INVOICE,
            'branch_code' => $code, 'prefix' => $prefix,
            'current_number' => $current, 'starting_number' => 1, 'is_active' => true,
        ]);
    }
}
